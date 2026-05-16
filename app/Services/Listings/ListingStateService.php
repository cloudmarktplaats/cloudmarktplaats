<?php

declare(strict_types=1);

namespace App\Services\Listings;

use App\Events\Listings\ListingArchived;
use App\Events\Listings\ListingPublished;
use App\Events\Listings\ListingRejected;
use App\Events\Listings\ListingSold;
use App\Models\Listing;

/**
 * Authoritative state machine for the listing lifecycle.
 *
 * Allowed transitions:
 *   draft           → pending_review | archived
 *   pending_review  → published | rejected | draft
 *   published       → sold | archived
 *   sold            → archived
 *   rejected        → draft
 *   archived        → (terminal)
 *
 * Every transition is gated by {@see TRANSITIONS} so callers (the listing
 * wizard, admin moderation panel, scheduled archive jobs) cannot move a
 * listing into a state inconsistent with its history. Successful
 * transitions dispatch domain events so cross-cutting concerns
 * (search indexing, reputation, DAC7 transaction recording) can hook in
 * without touching this class.
 */
class ListingStateService
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'draft' => ['pending_review', 'archived'],
        'pending_review' => ['published', 'rejected', 'draft'],
        'published' => ['sold', 'archived'],
        'sold' => ['archived'],
        'rejected' => ['draft'],
        'archived' => [],
    ];

    public function transition(Listing $listing, string $to, ?string $note = null): void
    {
        $from = (string) $listing->state;

        if (! array_key_exists($from, self::TRANSITIONS)
            || ! in_array($to, self::TRANSITIONS[$from], true)
        ) {
            throw new InvalidStateTransition(
                "Cannot move listing from '{$from}' to '{$to}'."
            );
        }

        $listing->state = $to;

        if ($to === 'published') {
            $listing->forceFill(['published_at' => now()]);
        }
        if ($to === 'sold') {
            $listing->forceFill(['sold_at' => now()]);
        }
        if ($to === 'rejected' && $note !== null) {
            $listing->moderation_notes = $note;
        }

        $listing->save();

        match ($to) {
            'published' => event(new ListingPublished($listing)),
            'sold' => event(new ListingSold($listing)),
            'rejected' => event(new ListingRejected($listing, $note)),
            'archived' => event(new ListingArchived($listing)),
            default => null,
        };
    }
}
