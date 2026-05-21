<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;

class RecentListings extends Component
{
    public int $limit = 6;

    /**
     * @return Collection<int, Listing>
     */
    public function listings(): Collection
    {
        return Listing::query()
            ->where('state', 'published')
            ->with(['photos' => fn ($q) => $q->orderBy('position')->limit(1)])
            ->orderByDesc('published_at')
            ->limit($this->limit)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.recent-listings', [
            'listings' => $this->listings(),
        ]);
    }
}
