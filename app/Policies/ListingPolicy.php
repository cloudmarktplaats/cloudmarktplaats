<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

/**
 * Authorization for listings, expressed against the three personas the app
 * knows ({@see User::hasRole()} → `user` / `moderator` / `admin`):
 *
 *   - the **public** (guests + any signed-in user): may browse the index and
 *     view published listings;
 *   - the **owner** (`listing.user_id === user.id`): fully manages their own
 *     listing — preview it while it is still a draft / in moderation, edit it,
 *     and mark it sold;
 *   - **staff** (`moderator` or `admin`): may preview, edit and delete any
 *     listing as part of moderation.
 *
 * Deliberately there is NO `before()` admin bypass (mirroring
 * {@see UserPolicy}): abilities are granted per-method so a staff member does
 * not silently inherit owner-only actions. `markSold` in particular is the
 * seller's gamified action and stays owner-only — staff moderate, they don't
 * close someone else's sale.
 *
 * Laravel 11 auto-discovers this policy (App\Models\Listing → this class), so
 * both the Livewire flows (via `Gate`/`->can`) and Filament's ListingResource
 * consult it without explicit registration.
 */
class ListingPolicy
{
    /** Anyone may browse the (published) catalogue — it's a public marketplace. */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published listings are public. A listing that is not yet public
     * (draft / pending_review / rejected / sold / archived) may only be
     * previewed by its owner or by staff; everyone else is denied so the
     * listing's existence isn't disclosed before it's published.
     */
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->state === 'published') {
            return true;
        }

        return $user !== null && ($this->owns($user, $listing) || $this->isStaff($user));
    }

    /** Any authenticated user may create a listing (route middleware adds verified + legal). */
    public function create(?User $user): bool
    {
        return $user !== null;
    }

    /** The owner edits their own listing; staff may edit any for moderation. */
    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) || $this->isStaff($user);
    }

    /** Hard-deleting a listing is a staff-only moderation action. */
    public function delete(User $user, Listing $listing): bool
    {
        return $this->isStaff($user);
    }

    /** Marking a listing sold is the seller's own action — owner only. */
    public function markSold(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    private function owns(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id;
    }

    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin', 'moderator');
    }
}
