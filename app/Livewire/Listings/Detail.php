<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Jobs\Listings\IncrementViewJob;
use App\Livewire\ContactSeller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public detail view for a single published listing.
 *
 * The route is `/listings/{ulid}-{slug}`; the slug exists for SEO and is
 * not load-bearing — only the ulid identifies the listing. Anonymous
 * users see the full listing (per `anonymous_browse` feature flag) and
 * can reach the seller through the one-way contact relay
 * ({@see ContactSeller}) — no account required.
 *
 * View counting is throttled per IP via {@see IncrementViewJob}; we
 * dispatch after the response so it doesn't slow down rendering.
 */
#[Layout('components.layouts.marketing')]
class Detail extends Component
{
    public Listing $listing;

    public function mount(string $ulid, string $slug): void
    {
        $listing = Listing::query()
            ->where('ulid', $ulid)
            ->where('state', 'published')
            ->first();

        if ($listing === null) {
            abort(404);
        }

        // Canonicalize the URL. The ulid alone identifies the listing, so
        // anyone can craft `/listings/{ulid}-anything` and still land on
        // the right detail page. That's bad for SEO (duplicate-content
        // signals) and lets people share misleading slugs ("...-broken-
        // worthless"). Permanent-redirect to the slug we own so canonical
        // signals point at one URL.
        if ($slug !== $listing->slug) {
            abort(new RedirectResponse("/listings/{$listing->ulid}-{$listing->slug}", 301));
        }

        $this->listing = $listing;

        Bus::dispatchAfterResponse(new IncrementViewJob(
            $listing->id,
            hash('sha256', (string) request()->ip().(string) config('app.key')),
        ));
    }

    public function render(): View
    {
        return view('livewire.listings.detail');
    }
}
