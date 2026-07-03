<?php

declare(strict_types=1);

namespace App\Listeners\Gamification;

use App\Events\Listings\ListingPublished;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;

class AwardInviteKarmaOnFirstListing
{
    public function __construct(private readonly KarmaService $karma) {}

    public function handle(ListingPublished $event): void
    {
        $owner = $event->listing->user;
        if (! $owner instanceof User || $owner->invited_by === null) {
            return;
        }

        // First published listing only. Rather than counting total
        // published listings (which breaks if events are replayed or
        // fired out of creation order), check whether an *earlier*
        // published listing already exists for this owner.
        $earlierPublishedExists = Listing::query()
            ->where('user_id', $owner->id)
            ->where('state', 'published')
            ->where('id', '<', $event->listing->id)
            ->exists();
        if ($earlierPublishedExists) {
            return;
        }

        $inviter = User::query()->find($owner->invited_by);
        if (! $inviter instanceof User) {
            return;
        }

        if ($inviter->is_banned) {
            return;
        }

        // Idempotency: never award twice for the same invitee.
        $already = $inviter->karmaEvents()
            ->where('type', 'invite_activation')
            ->where('source_type', $owner->getMorphClass())
            ->where('source_id', $owner->id)
            ->exists();
        if ($already) {
            return;
        }

        $this->karma->award(
            $inviter,
            'invite_activation',
            (int) config('cloudmarktplaats.gamification.karma_invite_activation'),
            $owner,
        );
    }
}
