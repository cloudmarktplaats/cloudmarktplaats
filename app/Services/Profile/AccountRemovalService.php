<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\Listing;
use App\Models\User;
use App\Services\Listings\ListingRemovalService;
use App\Services\Storage\PhotoFileEraser;
use Illuminate\Support\Facades\DB;

/**
 * Erases a member: their listings (rows and photo files), their homelab
 * posts, their login methods, and the account row itself.
 *
 * Nearly everything hanging off `users` has ON DELETE CASCADE — listings,
 * homelab_posts, user_identities, karma_events, legal_acceptances,
 * transactions, invite_codes.inviter_user_id — so the database does the bulk
 * of the work in one statement. Two things it cannot do:
 *
 *   1. **Photo files.** Those live on disk, not in Postgres, so every listing
 *      goes through {@see ListingRemovalService} first.
 *   2. **A soft delete.** `User` uses SoftDeletes, and a soft delete leaves
 *      the row — email address and all — in place while the cascades never
 *      fire, so the member's listings would stay published with an owner who
 *      believes they are gone. `forceDelete()` is what erasure means.
 *
 * What deliberately survives: `reports.reporter_user_id` and
 * `resolved_by_user_id` are ON DELETE SET NULL, so a moderation history stays
 * intact but anonymous. That is a record about a listing, not a profile.
 */
class AccountRemovalService
{
    public function __construct(
        private ListingRemovalService $listings,
        private PhotoFileEraser $files,
    ) {}

    public function remove(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $listings = Listing::withTrashed()->where('user_id', $user->id)->with('photos')->get();

            foreach ($listings as $listing) {
                $this->listings->remove($listing);
            }

            $this->eraseHomelabPhotos($user);

            $user->forceDelete();
        });
    }

    /**
     * Homelab photos hang off `homelab_posts`, not off `listings`, so the
     * cascade below removes their rows and would leave the blobs behind — an
     * erasure that looks complete from every screen while the images are still
     * being served. Same three-variant layout as listing photos, one directory
     * up: `homelabs/{post_ulid}/{position}/`.
     */
    private function eraseHomelabPhotos(User $user): void
    {
        $photos = HomelabPhoto::query()
            ->whereIn('homelab_post_id', HomelabPost::query()->where('user_id', $user->id)->select('id'))
            ->get();

        foreach ($photos as $photo) {
            $this->files->erase($photo->disk, (string) $photo->path);
        }
    }
}
