<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Models\Listing;
use App\Models\Transaction;
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

        // Zonder deze markering raakt de claim-link kwijt: de advertentie staat
        // op 'sold' en de verkoper heeft geen reden meer om de detailpagina te
        // openen, terwijl de koper daar nog op wacht.
        //
        // Alleen draaien als de vlag aan staat: uit betekent dat #deal-panel
        // niet meer rendert, dus een link ernaartoe zou dood zijn.
        $openClaimListingIds = config('cloudmarktplaats.features.deals')
            ? Transaction::query()
                ->whereIn('listing_id', $listings->pluck('id'))
                ->where('status', 'pending')
                ->whereNull('buyer_user_id')
                ->pluck('listing_id')
                ->all()
            : [];

        return view('livewire.listings.mine', [
            'listings' => $listings,
            'openClaimListingIds' => $openClaimListingIds,
        ]);
    }
}
