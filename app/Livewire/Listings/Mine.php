<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mijn advertenties" — the owner-facing management overview.
 *
 * The public {@see Browse} grid only ever shows `published` listings and has
 * no owner filter, so before this page the only way to reach the edit form
 * ({@see Wizard}) was to already hold the listing's own detail URL. This
 * lists every listing the current user owns — drafts, pending, published,
 * sold and archived alike — each with a link into the edit wizard.
 */
#[Layout('components.layouts.marketing', ['title' => 'Mijn advertenties — Cloudmarktplaats'])]
class Mine extends Component
{
    public function render(): View
    {
        /** @var Collection<int, Listing> $listings */
        $listings = Listing::query()
            ->where('user_id', auth()->id())
            ->with('photos')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.listings.mine', [
            'listings' => $listings,
        ]);
    }
}
