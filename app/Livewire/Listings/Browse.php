<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public "snuffel" grid — the heart of the marketplace.
 *
 *   - `/listings`          → mounted with no filter, shows everything published
 *   - `/c/{categoryPath}`  → mounted with an ltree prefix, scopes via Postgres
 *                             ltree containment (`path <@ ?::ltree`) so all
 *                             descendant categories are included automatically.
 *
 * The grid is browse-first, not search-first: people wander in without a
 * goal. Two orderings are offered — `recent` (default) and `shuffle`
 * ("Verras me"). Infinite scroll is driven from the front-end via an
 * Alpine IntersectionObserver that calls {@see loadMore()}; a visible
 * "Meer laden" button is the fallback. Anonymous browsing is enabled by
 * default (`cloudmarktplaats.features.anonymous_browse`).
 *
 * Seeded shuffle: `ORDER BY random()` alone gives duplicates and gaps as
 * the window grows, because every request re-rolls the dice. We instead
 * pin a per-session integer `seed` (kept in the querystring so a wandering
 * session — and any deep-link a user shares — stays consistent) and feed
 * it to Postgres `setseed()` right before the random ordering. setseed
 * resets the PRNG deterministically, so the first N rows are a stable
 * prefix that only grows as `perPage` grows.
 *
 * PERF: at launch volume this full re-scan per request is negligible.
 * It does NOT scale — `setseed()`+`ORDER BY random()` re-sorts the whole
 * table every load. When the catalogue grows this must move to a
 * materialized shuffle (e.g. a per-session shuffled id list cached in
 * Redis, or a precomputed `shuffle_bucket` column). Tracked for later.
 */
#[Layout('components.layouts.marketing', ['title' => 'Snuffelen — Cloudmarktplaats'])]
class Browse extends Component
{
    public ?string $categoryPath = null;

    /** Ordering mode: `recent` (default) or `shuffle` ("Verras me"). */
    #[Url]
    public string $sort = 'recent';

    /**
     * Per-session shuffle seed. Null in recent mode. Lives in the
     * querystring so doorscrollen — and shared deep-links — keep the same
     * random order within one session.
     */
    #[Url]
    public ?int $seed = null;

    /** How many cards are currently revealed; grows by 12 per loadMore(). */
    #[Locked]
    public int $perPage = 12;

    public function mount(?string $categoryPath = null): void
    {
        $this->categoryPath = $categoryPath;

        // Arriving with ?sort=shuffle but no seed (e.g. a bare shared link)
        // still needs a stable seed to wander against.
        if ($this->sort === 'shuffle' && $this->seed === null) {
            $this->seed = random_int(1, 1_000_000);
        }
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort === 'shuffle' ? 'shuffle' : 'recent';
        $this->seed = $this->sort === 'shuffle' ? random_int(1, 1_000_000) : null;
        $this->perPage = 12;
    }

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    public function render(): View
    {
        $base = Listing::query()
            ->with('photos')
            ->where('state', 'published');

        if ($this->categoryPath !== null && $this->categoryPath !== '') {
            $base->whereHas('category', function ($q): void {
                $q->whereRaw('path <@ ?::ltree', [$this->categoryPath]);
            });
        }

        $total = (clone $base)->count();

        if ($this->sort === 'shuffle' && $this->seed !== null) {
            // setseed() wants a float in [-1, 1]; map the integer seed in.
            DB::select('select setseed(?)', [($this->seed % 1_000) / 1_000]);
            $base->orderByRaw('random()');
        } else {
            $base->orderByDesc('published_at');
        }

        /** @var Collection<int, Listing> $listings */
        $listings = $base->limit($this->perPage)->get();

        return view('livewire.listings.browse', [
            'listings' => $listings,
            'hasMore' => $total > $this->perPage,
        ]);
    }
}
