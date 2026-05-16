<?php

declare(strict_types=1);

namespace App\Events\Listings;

use App\Models\Listing;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a listing transitions to `sold`. Listeners (reputation,
 * DAC7 transaction record) attach in later sub-projects.
 */
class ListingSold
{
    use Dispatchable;

    public function __construct(public Listing $listing) {}
}
