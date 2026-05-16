<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marketplace search abstraction.
 *
 * Foundation ships {@see PostgresSearchService} (Postgres FTS via the
 * `search_vector` STORED column on `listings`). A later sub-project
 * can swap in a {@see MeilisearchSearchService} without touching any
 * callers because every search-route controller depends on this
 * interface, not on a concrete implementation.
 */
interface SearchInterface
{
    /**
     * Build a query that returns published listings matching `$query`.
     * Returning a Builder rather than a Collection lets callers chain
     * pagination, additional filters, or eager loads.
     *
     * @return Builder<Listing>
     */
    public function listings(string $query): Builder;
}
