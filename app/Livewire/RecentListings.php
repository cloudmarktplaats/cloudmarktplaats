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
     * Hoogstens zoveel advertenties van dezelfde verkoper op de voorpagina.
     *
     * Op 19-08 vulde één verkoper zes van de twaalf kaarten met vier identieke
     * mini-PC's en twee identieke servers. Geen kwade wil — hij had gewoon
     * meerdere exemplaren — maar de voorpagina werd zijn etalage en de man met
     * één Juniper-switch verdween eronder.
     *
     * Deze grens geldt voor iedereen, particulier én zakelijk, en staat er
     * bewust vóórdat er een bedrijf betaalt voor zichtbaarheid. Zolang deze
     * regel er is, kan geld hier geen positie kopen.
     */
    public int $maxPerSeller = 2;

    /**
     * @return Collection<int, Listing>
     */
    public function listings(): Collection
    {
        // Ruim ophalen en daarna afromen: welke advertenties afvallen hangt van
        // de verkoper af, dus dat is niet in één query te doen zonder een
        // window-functie die de rest van deze code niet waard is.
        $candidates = Listing::query()
            ->where('state', 'published')
            ->with(['photos' => fn ($q) => $q->orderBy('position')->limit(1)])
            ->orderByDesc('published_at')
            ->limit($this->limit * $this->maxPerSeller * 4)
            ->get();

        $perSeller = [];

        return $candidates
            ->filter(function (Listing $listing) use (&$perSeller): bool {
                $seen = $perSeller[$listing->user_id] ?? 0;
                if ($seen >= $this->maxPerSeller) {
                    return false;
                }
                $perSeller[$listing->user_id] = $seen + 1;

                return true;
            })
            ->take($this->limit)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.recent-listings', [
            'listings' => $this->listings(),
        ]);
    }
}
