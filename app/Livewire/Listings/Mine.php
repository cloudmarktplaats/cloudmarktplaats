<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Models\Listing;
use App\Models\Transaction;
use App\Services\Listings\ListingRemovalService;
use App\Services\Listings\ListingStateService;
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
    /**
     * The listing the seller has clicked "Verwijderen" on but not yet
     * confirmed. Held server-side rather than behind a `wire:confirm`
     * browser dialog so the two-step nature is testable, and so the warning
     * can spell out what "definitief" means in the seller's own words.
     */
    public ?int $confirmingDeleteId = null;

    /** Take a listing offline: out of search, out of the public grid, kept. */
    public function archive(int $listingId): void
    {
        $listing = $this->authorizedListing($listingId, 'archive');

        app(ListingStateService::class)->transition($listing, 'archived');
    }

    /** Put an archived listing back as a draft, ready to be resubmitted. */
    public function restore(int $listingId): void
    {
        $listing = $this->authorizedListing($listingId, 'archive');

        app(ListingStateService::class)->transition($listing, 'draft');
    }

    public function confirmDelete(int $listingId): void
    {
        $this->confirmingDeleteId = $listingId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Named `destroyListing` rather than `delete`: Livewire reserves a few
     * method names on the component itself, and a plain `delete()` reads
     * ambiguously next to the soft-delete Eloquent already gives us.
     */
    public function destroyListing(int $listingId): void
    {
        $listing = $this->authorizedListing($listingId, 'delete');

        // Zonder deze regel wist één misklik op een verstuurde Livewire-actie
        // de advertentie alsnog: de bevestigingsstap zit in de view, en de
        // view is niet de plek waar een onomkeerbare actie bewaakt hoort.
        if ($this->confirmingDeleteId !== $listingId) {
            return;
        }

        app(ListingRemovalService::class)->remove($listing);
        $this->confirmingDeleteId = null;

        session()->flash('listing_deleted', true);
    }

    /**
     * Fetch a listing and check the ability against it, aborting with 403
     * rather than 404 when it belongs to someone else — matching how
     * {@see Detail} guards the seller's own actions.
     */
    private function authorizedListing(int $listingId, string $ability): Listing
    {
        /** @var Listing $listing */
        $listing = Listing::query()->findOrFail($listingId);

        abort_unless(auth()->user()?->can($ability, $listing) ?? false, 403);

        return $listing;
    }

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
                ->unclaimed()
                ->whereIn('listing_id', $listings->pluck('id'))
                ->pluck('listing_id')
                ->all()
            : [];

        return view('livewire.listings.mine', [
            'listings' => $listings,
            'openClaimListingIds' => $openClaimListingIds,
        ]);
    }
}
