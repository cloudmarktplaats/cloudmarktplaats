<?php

declare(strict_types=1);

namespace App\Events\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a listing is archived (terminal state). Search
 * de-indexers and notification listeners hook in here.
 */
class ListingArchived
{
    use Dispatchable;

    public function __construct(public Listing $listing) {}
}
