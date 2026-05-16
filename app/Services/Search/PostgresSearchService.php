<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Postgres full-text search backend.
 *
 * The `listings` table carries a STORED `search_vector tsvector`
 * column (built in migration B4 from `title` and `description` with
 * the `dutch` text-search configuration) plus a GIN index on that
 * column. Queries are matched via `plainto_tsquery` so user input
 * does not need to be escaped — anything illegal becomes whitespace.
 *
 * Results are ranked by `ts_rank` so the most relevant matches come
 * first; ties fall back to the order Postgres feels like returning
 * them in (good enough for Foundation; later we can add a
 * recency boost).
 */
class PostgresSearchService implements SearchInterface
{
    public function listings(string $query): Builder
    {
        return Listing::query()
            ->where('state', 'published')
            ->whereRaw("search_vector @@ plainto_tsquery('dutch', ?)", [$query])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('dutch', ?)) DESC", [$query]);
    }
}
