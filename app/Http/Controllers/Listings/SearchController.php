<?php

declare(strict_types=1);

namespace App\Http\Controllers\Listings;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public marketplace search endpoint.
 *
 * Thin invokable controller — all matching logic lives in the bound
 * {@see SearchInterface} implementation so swapping backends
 * (Postgres FTS → Meilisearch) is a one-line provider change.
 */
class SearchController extends Controller
{
    public function __construct(private SearchInterface $search) {}

    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $results = $q === ''
            ? collect()
            : $this->search->listings($q)->paginate(20)->appends(['q' => $q]);

        return view('listings.search', [
            'q' => $q,
            'results' => $results,
        ]);
    }
}
