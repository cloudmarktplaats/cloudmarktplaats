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
 *   pending_review  → published | rejected | draft | archived
 *   published       → sold | archived
 *   sold            → archived
 *   rejected        → draft | archived
 *   archived        → draft
 *
 * `archived` was terminal and unreachable: no caller anywhere moved a
 * listing into it, so a seller whose item sold elsewhere had no way to take
 * their advertisement down — reported in issues #9 and #10, and the reason one
 * member asked for their account to be deleted. Two consequences of that:
 * every state a seller can be stuck in now has an exit to `archived`
 * (moderation queue very much included — that is exactly where the wait
 * hurts), and `archived` leads back to `draft` so "offline halen" is
 * something you can undo. Returning as a draft rather than straight to
 * `published` keeps moderation binding: re-publishing goes through the
 * queue again like any other submission.
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
        'pending_review' => ['published', 'rejected', 'draft', 'archived'],
        'published' => ['sold', 'archived'],
        'sold' => ['archived'],
        'rejected' => ['draft', 'archived'],
        'archived' => ['draft'],
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
