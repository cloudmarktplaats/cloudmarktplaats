<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public listing grid.
 *
 *   - `/listings`          → mounted with no filter, shows everything published
 *   - `/c/{categoryPath}`  → mounted with an ltree prefix, scopes via Postgres
 *                             ltree containment (`path <@ ?::ltree`) so all
 *                             descendant categories are included automatically.
 *
 * Renders 20 per page; pagination is provided by Livewire's WithPagination
 * trait. Anonymous browsing is enabled by default
 * (`cloudmarktplaats.features.anonymous_browse`).
 */
#[Layout('layouts.app')]
class Browse extends Component
{
    use WithPagination;

    public ?string $categoryPath = null;

    public function mount(?string $categoryPath = null): void
    {
        $this->categoryPath = $categoryPath;
    }

    /**
     * @return LengthAwarePaginator<Listing>
     */
    public function listings(): LengthAwarePaginator
    {
        $query = Listing::query()
            ->where('state', 'published')
            ->orderByDesc('published_at');

        if ($this->categoryPath !== null && $this->categoryPath !== '') {
            $query->whereHas('category', function ($q): void {
                $q->whereRaw('path <@ ?::ltree', [$this->categoryPath]);
            });
        }

        return $query->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.listings.browse', [
            'listings' => $this->listings(),
        ]);
    }
}
