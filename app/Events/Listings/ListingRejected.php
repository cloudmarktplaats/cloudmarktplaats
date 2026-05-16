<?php

declare(strict_types=1);

namespace App\Events\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a moderator rejects a pending listing. The optional
 * note carries the rejection reason for downstream notification listeners.
 */
class ListingRejected
{
    use Dispatchable;

    public function __construct(public Listing $listing, public ?string $note = null) {}
}
