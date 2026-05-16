<?php

declare(strict_types=1);

namespace App\Events\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a listing is moved into the `published` state.
 * Listeners (search indexing, reputation, DAC7 reporting) attach in
 * later sub-projects without modifying the listing core.
 */
class ListingPublished
{
    use Dispatchable;

    public function __construct(public Listing $listing) {}
}
