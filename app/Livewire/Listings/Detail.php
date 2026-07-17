<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Exceptions\DealException;
use App\Jobs\Listings\IncrementViewJob;
use App\Livewire\ContactSeller;
use App\Models\Listing;
use App\Services\Gamification\DealService;
use App\Support\ListingJsonLd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

    public string $buyerUsername = '';

    public function markSold(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 403);

        $user = auth()->user();
        abort_unless($user?->can('markSold', $this->listing) ?? false, 403);

        try {
            app(DealService::class)->markSold(
                $this->listing,
                $user,
                $this->buyerUsername !== '' ? $this->buyerUsername : null,
            );
        } catch (DealException $e) {
            $this->addError('buyerUsername', $e->getMessage());

            return;
        }

        $this->listing->refresh();
    }

    public function mount(string $ulid, string $slug): void
    {
        $listing = Listing::query()
            ->where('ulid', $ulid)
            ->first();

        if ($listing === null) {
            abort(404);
        }

        // Non-published listings (draft / pending_review / rejected / sold /
        // archived) are hidden from the public, but the owner must be able
        // to preview their own submission — otherwise "plaats advertentie"
        // lands on a 404 while moderation is pending. Staff moderators can
        // preview too (they approve from here). The `view` ability encodes
        // exactly that; a denied preview is a 404 (not 403) so the listing's
        // existence isn't disclosed before it's published.
        if (! Gate::allows('view', $listing)) {
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

        // Only count views on the public, published page — a seller
        // refreshing their own pending preview shouldn't inflate the counter.
        if ($listing->state === 'published') {
            Bus::dispatchAfterResponse(new IncrementViewJob(
                $listing->id,
                hash('sha256', (string) request()->ip().(string) config('app.key')),
            ));
        }
    }

    public function render(): View
    {
        $view = view('livewire.listings.detail');

        // Only a published listing gets its own OG tags. For draft /
        // pending_review / rejected the layout defaults stay, so a listing
        // that isn't public yet cannot leak its title, photo or price through
        // meta tags — the owner and staff can still preview the page itself.
        if ($this->listing->state !== 'published') {
            return $view;
        }

        return $view->layoutData([
            'title' => $this->listing->title.' — Cloudmarktplaats',
            'description' => $this->ogDescription(),
            'ogImage' => $this->ogImageUrl(),
            'canonical' => route('listings.detail', [
                'ulid' => $this->listing->ulid,
                'slug' => $this->listing->slug,
            ]),
            'jsonLd' => app(ListingJsonLd::class)->toJson($this->listing),
        ]);
    }

    /**
     * og:image must be the `original` variant: LinkedIn's crawler is unreliable
     * with WebP, and `card` is a 600x600 WebP crop.
     *
     * But the wizard accepts webp uploads too, and StoreListingPhotoJob encodes
     * a webp source *as* webp — so `original` is only jpg/png when the source
     * was. For a webp original we return null and let the layout fall back to
     * og-default.png: a branded image beats the blank card LinkedIn renders
     * when it can't decode ours.
     */
    private function ogImageUrl(): ?string
    {
        $photo = $this->listing->photos->first();

        if ($photo === null || ! in_array($photo->mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return $photo->urlFor('original');
    }

    /**
     * `description` is nullable. An empty og:description makes LinkedIn render
     * a bare link, so fall back to the facts we always have.
     */
    private function ogDescription(): string
    {
        $description = trim((string) $this->listing->description);

        if ($description !== '') {
            return Str::limit($description, 155);
        }

        return sprintf(
            '%s — %s — € %s',
            (string) $this->listing->category?->name,
            $this->listing->conditionLabel(),
            number_format($this->listing->price_cents / 100, 2, ',', '.'),
        );
    }
}
