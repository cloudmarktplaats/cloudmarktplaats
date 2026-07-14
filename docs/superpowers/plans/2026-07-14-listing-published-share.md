# Delen na goedkeuring — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een verkoper krijgt bij goedkeuring van zijn advertentie een mail die doorlinkt naar een share-paneel (LinkedIn + MainDeck + kopieerknop), met UTM's per kanaal.

**Architecture:** Vier losse stukken. `ShareLinkBuilder` (plain PHP klasse) is de enige plek waar UTM-strings bestaan. `Detail::render()` geeft per-listing OG-data door aan de bestaande marketing-layout — dat is het fundament, want LinkedIn haalt alles uit de OG-tags. Het share-paneel is een anonymous Blade-component (geen server-state). De mail hangt als extra listener aan het bestaande `ListingPublished`-event.

**Tech Stack:** Laravel 11, Livewire 3 (bundelt Alpine, geladen via `@livewireScripts`), Filament 3, Pest, Tailwind met `cmp-*` tokens.

**Spec:** `docs/superpowers/specs/2026-07-14-listing-published-share-design.md`

## Global Constraints

- **Testen draait als www-data:** `docker compose exec -T -u www-data php-fpm php artisan test`. Artisan als root chownt `storage/logs` naar root en 500't daarna de web-worker.
- **Pint/PHPStan draaien als uid 1000:** `docker compose exec -T -u 1000 -e TMPDIR=/tmp/pstan php-fpm sh -c 'mkdir -p /tmp/pstan && ./vendor/bin/phpstan analyse --no-progress'`.
- **UI-strings Nederlands, code-comments Engels.** Alle user-facing tekst door `__()`.
- **Huisstijl is LICHT** (datasheet): `cmp-bg #F5F6F6`, `cmp-surface #FFFFFF`, `cmp-ink #17191B`, één accent `cmp-signal #D9480F`. Bestaande classes hergebruiken: `cmp-btn`, `cmp-btn-primary`, `cmp-btn-secondary`, `cmp-btn-ghost`, `cmp-section-label`. Geen nieuwe kleuren.
- **`declare(strict_types=1);`** in elk PHP-bestand.
- **Prijsformat overal identiek:** `number_format($listing->price_cents / 100, 2, ',', '.')`.
- **Geen Google-diensten / externe assets.**

---

### Task 1: ShareLinkBuilder

De enige plek waar UTM-strings en share-URL's bestaan.

**Files:**
- Create: `app/Support/ShareLinkBuilder.php`
- Test: `tests/Feature/Listings/ShareLinkBuilderTest.php`

**Interfaces:**
- Consumes: `App\Models\Listing` (`ulid`, `slug`, `title`, `price_cents`), route `listings.detail`.
- Produces:
  - `listingUrl(Listing $listing, string $source, string $medium, string $campaign): string`
  - `linkedIn(Listing $listing): string`
  - `mainDeckUrl(): string`
  - `emailUrl(Listing $listing): string`
  - `copyUrl(Listing $listing): string`
  - `shareText(Listing $listing): string`
  - Constanten `CAMPAIGN_SHARE = 'seller_share'`, `CAMPAIGN_PUBLISHED = 'listing_published'`

**Let op — afwijking van de spec:** de spec schrijft `mainDeck(Listing $l)`, maar v1 gebruikt de listing niet (geen bevestigde prefill). Een ongebruikte parameter is dode code, dus de signature is `mainDeckUrl(): string`. Zodra prefill bevestigd is, wordt het `mainDeckUrl(Listing $listing)`.

**Ook nieuw t.o.v. de spec:** de spec-tabel dekte de gekopieerde link niet. Die krijgt `utm_source=copy`, `utm_medium=social`, `utm_campaign=seller_share` — zo zie je waar geplakte links landen.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Listings/ShareLinkBuilderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Support\ShareLinkBuilder;

it('builds a listing url with utm params on the canonical route', function () {
    $listing = Listing::factory()->create(['slug' => 'cisco-6509']);

    $url = app(ShareLinkBuilder::class)
        ->listingUrl($listing, 'linkedin', 'social', 'seller_share');

    expect($url)
        ->toContain("/listings/{$listing->ulid}-cisco-6509")
        ->toContain('utm_source=linkedin')
        ->toContain('utm_medium=social')
        ->toContain('utm_campaign=seller_share');
});

it('wraps the listing url in the linkedin share-offsite endpoint, url-encoded', function () {
    $listing = Listing::factory()->create();

    $url = app(ShareLinkBuilder::class)->linkedIn($listing);

    expect($url)->toStartWith('https://www.linkedin.com/sharing/share-offsite/?url=')
        // The nested url must be encoded, otherwise LinkedIn truncates at the first &
        ->toContain(urlencode('utm_source=linkedin'))
        ->not->toContain('&utm_medium');
});

it('tags the email link as email/listing_published', function () {
    $listing = Listing::factory()->create();

    expect(app(ShareLinkBuilder::class)->emailUrl($listing))
        ->toContain('utm_source=email')
        ->toContain('utm_medium=email')
        ->toContain('utm_campaign=listing_published');
});

it('tags the copyable link as copy/seller_share', function () {
    $listing = Listing::factory()->create();

    expect(app(ShareLinkBuilder::class)->copyUrl($listing))
        ->toContain('utm_source=copy')
        ->toContain('utm_campaign=seller_share');
});

it('builds share text with the dutch price format and the tagged url', function () {
    $listing = Listing::factory()->create([
        'title' => '2 x Cisco 6509',
        'price_cents' => 45000,
    ]);

    $text = app(ShareLinkBuilder::class)->shareText($listing);

    expect($text)
        ->toContain('2 x Cisco 6509')
        ->toContain('€ 450,00')
        ->toContain('utm_source=copy');
});

it('links to maindeck without prefill (v1 — no confirmed share intent)', function () {
    expect(app(ShareLinkBuilder::class)->mainDeckUrl())->toBe('https://maindeck.eu/');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ShareLinkBuilderTest`
Expected: FAIL — `Class "App\Support\ShareLinkBuilder" not found`

- [ ] **Step 3: Write minimal implementation**

`app/Support/ShareLinkBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Listing;

/**
 * Single source of truth for share URLs and their UTM tagging.
 *
 * UTM parameters measure where traffic *comes from*, and they belong on the
 * destination — our own listing URL. Tagging our own link with
 * `utm_source=cloudmarktplaats` would measure itself; the source is the
 * platform the visitor clicked *from*.
 *
 * Every share URL is built on the canonical `listings.detail` route with the
 * listing's current slug. Detail::mount() permanently redirects a mismatched
 * slug, and a crawler is not guaranteed to follow that hop — so a stale slug
 * silently costs us the preview.
 */
class ShareLinkBuilder
{
    public const CAMPAIGN_SHARE = 'seller_share';

    public const CAMPAIGN_PUBLISHED = 'listing_published';

    public function listingUrl(Listing $listing, string $source, string $medium, string $campaign): string
    {
        $url = route('listings.detail', [
            'ulid' => $listing->ulid,
            'slug' => $listing->slug,
        ]);

        return $url.'?'.http_build_query([
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
        ]);
    }

    public function linkedIn(Listing $listing): string
    {
        $target = $this->listingUrl($listing, 'linkedin', 'social', self::CAMPAIGN_SHARE);

        // LinkedIn ignores title/summary/text on share-offsite (since ~2021) —
        // the post is rendered entirely from the target page's OG tags.
        return 'https://www.linkedin.com/sharing/share-offsite/?url='.urlencode($target);
    }

    /**
     * MainDeck has no confirmed share-intent endpoint: /share and /compose exist
     * but sit behind /login, and the login redirect drops the query string. v1
     * links to the site; shareText() carries the copyable text.
     */
    public function mainDeckUrl(): string
    {
        return 'https://maindeck.eu/';
    }

    public function emailUrl(Listing $listing): string
    {
        return $this->listingUrl($listing, 'email', 'email', self::CAMPAIGN_PUBLISHED);
    }

    public function copyUrl(Listing $listing): string
    {
        return $this->listingUrl($listing, 'copy', 'social', self::CAMPAIGN_SHARE);
    }

    public function shareText(Listing $listing): string
    {
        return sprintf(
            '%s — € %s op Cloudmarktplaats: %s',
            $listing->title,
            number_format($listing->price_cents / 100, 2, ',', '.'),
            $this->copyUrl($listing),
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ShareLinkBuilderTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Support/ShareLinkBuilder.php tests/Feature/Listings/ShareLinkBuilderTest.php
git commit -m "ShareLinkBuilder: één plek voor share-URLs en UTM-tagging"
```

---

### Task 2: OG-tags per listing

Het fundament. Zonder dit toont elke gedeelde advertentie op LinkedIn de generieke homepage-titel met `og-default.png`.

**Files:**
- Modify: `app/Livewire/Listings/Detail.php` (`render()`, regel ~100)
- Test: `tests/Feature/Listings/ListingOgTagsTest.php`

**Interfaces:**
- Consumes: `components.layouts.marketing` `@props`: `title`, `description`, `canonical`, `ogImage`. `ListingPhoto::urlFor(string $variant)`. `Listing::conditionLabel()`, `Listing::category->name`.
- Produces: niets voor latere taken.

**Waarom `original` en niet `card`:** de varianten uit `StoreListingPhotoJob` zijn `original` (max 2000px, bron-mime), `card` (600×600 webp) en `thumb` (200×200 webp). LinkedIn's crawler is onbetrouwbaar met WebP en wil ~1.91:1; `card` is vierkant én webp. `original` is de enige die zeker werkt.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Listings/ListingOgTagsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Listing;

it('renders listing-specific og tags on a published listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'published',
        'title' => 'Cisco 6509',
        'description' => 'Twee chassis, compleet met supervisors.',
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Cisco 6509 — Cloudmarktplaats', false)
        ->assertSee('Twee chassis, compleet met supervisors.', false);
});

it('falls back to category, condition and price when the description is empty', function () {
    $category = Category::factory()->create(['name' => 'Netwerk']);
    $listing = Listing::factory()->create([
        'state' => 'published',
        'description' => null,
        'condition' => 'used',
        'price_cents' => 45000,
        'category_id' => $category->id,
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Netwerk', false)
        ->assertSee('€ 450,00', false);
});

it('keeps the layout defaults on a non-published listing so it cannot leak', function () {
    $listing = Listing::factory()->create([
        'state' => 'pending_review',
        'title' => 'Geheime Cisco',
    ]);

    // The owner may preview it; the OG tags must still show the generic defaults.
    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Cloudmarktplaats', false)
        ->assertDontSee('<meta property="og:title" content="Geheime Cisco', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ListingOgTagsTest`
Expected: FAIL — de eerste test ziet de default titel in plaats van "Cisco 6509 — Cloudmarktplaats"

- [ ] **Step 3: Write minimal implementation**

In `app/Livewire/Listings/Detail.php`, vervang `render()` en voeg twee private helpers toe. Voeg `use Illuminate\Support\Str;` toe aan de imports:

```php
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
        ]);
    }

    /**
     * og:image must be the `original` variant: LinkedIn's crawler is unreliable
     * with WebP and wants ~1.91:1, while `card` is a 600x600 WebP crop. Null
     * lets the layout fall back to og-default.png.
     */
    private function ogImageUrl(): ?string
    {
        return $this->listing->photos->first()?->urlFor('original');
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ListingOgTagsTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Verify in the real page**

Run:
```bash
docker compose exec -T -u www-data php-fpm php artisan tinker --execute="echo App\Models\Listing::where('state','published')->value('ulid');"
curl -s "localhost:8080/listings/<ulid>-<slug>" | grep -o '<meta property="og:[^>]*>'
```
Expected: `og:title` bevat de listing-titel, `og:image` eindigt op `/original.jpg` (of `.png`), niet op `card.webp`.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Listings/Detail.php tests/Feature/Listings/ListingOgTagsTest.php
git commit -m "OG-tags per listing op de detailpagina

Zonder dit toonde elke gedeelde advertentie de generieke homepage-titel met
og-default.png. og:image gebruikt de original-variant: LinkedIn's crawler is
onbetrouwbaar met de webp card-variant. Niet-published listings houden de
layout-defaults zodat ze niet via meta-tags lekken."
```

---

### Task 3: Share-paneel

**Files:**
- Create: `resources/views/components/listings/share-panel.blade.php`
- Modify: `resources/views/livewire/listings/detail.blade.php` (na het `<article>`-blok)
- Modify: `resources/views/livewire/listings/mine.blade.php` (in de `@foreach` per listing)
- Test: `tests/Feature/Listings/SharePanelTest.php`

**Interfaces:**
- Consumes: `ShareLinkBuilder` (Task 1) — `linkedIn()`, `mainDeckUrl()`, `shareText()`. `ListingPolicy` voor de eigenaar-check.
- Produces: component `<x-listings.share-panel :listing="$listing" />`.

**Zichtbaarheid:** alleen de eigenaar, alleen bij `state === 'published'`. Een moderator hoeft me niet te helpen delen; `ListingPolicy` kent bewust geen admin-bypass.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Listings/SharePanelTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;

it('shows the share panel to the owner of a published listing', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Deel je advertentie')
        ->assertSee('linkedin.com/sharing/share-offsite', false);
});

it('hides the share panel from a visitor who is not the owner', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $this->actingAs(User::factory()->create())
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('Deel je advertentie');
});

it('hides the share panel on a listing that is not published yet', function () {
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('Deel je advertentie');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=SharePanelTest`
Expected: FAIL — "Deel je advertentie" niet gevonden

- [ ] **Step 3: Create the component**

`resources/views/components/listings/share-panel.blade.php`:

```blade
@props(['listing'])

@php
    // Owner-only, published-only. Staff moderate; they don't share someone
    // else's listing — which is why this checks ownership, not a policy that
    // staff would pass too.
    $isOwner = auth()->id() === $listing->user_id;
@endphp

@if ($isOwner && $listing->state === 'published')
    @php
        $share = app(App\Support\ShareLinkBuilder::class);
        $shareText = $share->shareText($listing);
    @endphp

    <section class="mt-6 rounded-sm border border-cmp-border bg-cmp-surface p-5 sm:p-6">
        <div class="cmp-section-label mb-3">{{ __('Delen') }}</div>
        <h2 class="font-display text-xl font-bold tracking-display-tight">
            {{ __('Deel je advertentie') }}
        </h2>
        <p class="mt-2 text-sm text-cmp-muted">
            {{ __('Je advertentie staat live. Delen levert meestal de eerste reacties op.') }}
        </p>

        <div x-data="{ copied: false }" class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <a
                href="{{ $share->linkedIn($listing) }}"
                target="_blank"
                rel="noopener external"
                class="cmp-btn cmp-btn-primary"
            >{{ __('Deel op LinkedIn') }}</a>

            <a
                href="{{ $share->mainDeckUrl() }}"
                target="_blank"
                rel="noopener external"
                class="cmp-btn cmp-btn-secondary"
            >{{ __('Deel op MainDeck') }}</a>

            {{-- Hidden input doubles as the execCommand fallback target: the
                 Clipboard API is unavailable on plain http and older browsers,
                 and there it silently rejects. --}}
            <input
                type="text"
                x-ref="shareText"
                value="{{ $shareText }}"
                readonly
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
            >

            <button
                type="button"
                class="cmp-btn cmp-btn-ghost"
                @click="
                    const copy = navigator.clipboard
                        ? navigator.clipboard.writeText($refs.shareText.value)
                        : Promise.reject();
                    copy.catch(() => {
                        $refs.shareText.classList.remove('sr-only');
                        $refs.shareText.select();
                        document.execCommand('copy');
                        $refs.shareText.classList.add('sr-only');
                    }).finally(() => {
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    });
                "
            >
                <span x-show="!copied">{{ __('Kopieer tekst + link') }}</span>
                <span x-show="copied" x-cloak>{{ __('Gekopieerd') }}</span>
            </button>
        </div>
    </section>
@endif
```

- [ ] **Step 4: Mount the component on the detail page**

In `resources/views/livewire/listings/detail.blade.php`, direct ná het sluitende `</article>` van het foto/beschrijving-blok:

```blade
    <x-listings.share-panel :listing="$listing" />
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=SharePanelTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Mount the component on "Mijn advertenties"**

In `resources/views/livewire/listings/mine.blade.php`, binnen de `@foreach ($listings as $listing)`-lus, onderaan het `<li>`-blok:

```blade
                    <x-listings.share-panel :listing="$listing" />
```

Het paneel verbergt zichzelf voor niet-published listings, dus de lus heeft geen extra conditie nodig.

- [ ] **Step 7: Verify the copy button in a browser**

Run: open `http://localhost:8080/listings/<ulid>-<slug>` als eigenaar, klik "Kopieer tekst + link".
Expected: knop wisselt 2 seconden naar "Gekopieerd"; geplakte tekst bevat titel, `€ ...` en een URL met `utm_source=copy`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/listings/share-panel.blade.php \
        resources/views/livewire/listings/detail.blade.php \
        resources/views/livewire/listings/mine.blade.php \
        tests/Feature/Listings/SharePanelTest.php
git commit -m "Share-paneel voor de eigenaar van een gepubliceerde advertentie"
```

---

### Task 4: Publicatie-mail

**Files:**
- Create: `app/Mail/ListingPublishedMail.php`
- Create: `app/Listeners/Listings/SendListingPublishedMail.php`
- Create: `resources/views/emails/listing-published.blade.php`
- Modify: `app/Providers/AppServiceProvider.php` (`boot()`, bij de bestaande `Event::listen`)
- Test: `tests/Feature/Listings/ListingPublishedMailTest.php`

**Interfaces:**
- Consumes: `ShareLinkBuilder::emailUrl()` (Task 1), `App\Events\Listings\ListingPublished` (property `public Listing $listing`).
- Produces: niets voor latere taken.

**Stijlkeuze:** de bestaande `emails/seller-contact.blade.php` is nog de oude donkere stijl (`#0A0D14`, Space Grotesk). De huisstijl is inmiddels het lichte datasheet-thema. Deze mail volgt de **huidige** huisstijl (licht, `#F5F6F6`/`#FFFFFF`/`#17191B`, accent `#D9480F`) met system-font fallbacks — mailclients laden onze self-hosted fonts toch niet. `seller-contact` bijwerken valt buiten deze scope.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Listings/ListingPublishedMailTest.php`:

```php
<?php

declare(strict_types=1);

use App\Mail\ListingPublishedMail;
use App\Models\Listing;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\Mail;

it('queues a mail to the seller when their listing is published', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    app(ListingStateService::class)->transition($listing, 'published');

    Mail::assertQueued(ListingPublishedMail::class, function (ListingPublishedMail $mail) use ($listing) {
        return $mail->hasTo($listing->user->email)
            && $mail->listing->is($listing);
    });
});

it('does not mail when a listing is rejected', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    app(ListingStateService::class)->transition($listing, 'rejected', 'Onvoldoende foto\'s');

    Mail::assertNothingQueued();
});

it('mails again when a rejected listing is resubmitted and approved', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);
    $svc = app(ListingStateService::class);

    $svc->transition($listing, 'rejected', 'Onvoldoende foto\'s');
    $svc->transition($listing->fresh(), 'draft');
    $svc->transition($listing->fresh(), 'pending_review');
    $svc->transition($listing->fresh(), 'published');

    // Deliberate: the seller is told their listing is now genuinely approved.
    Mail::assertQueued(ListingPublishedMail::class, 1);
});

it('tags the listing link in the mail as email/listing_published', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $rendered = (new ListingPublishedMail($listing))->render();

    expect($rendered)
        ->toContain('utm_source=email')
        ->toContain('utm_campaign=listing_published');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ListingPublishedMailTest`
Expected: FAIL — `Class "App\Mail\ListingPublishedMail" not found`

- [ ] **Step 3: Write the Mailable**

`app/Mail/ListingPublishedMail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Listing;
use App\Support\ShareLinkBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Told the seller their listing passed moderation and is now live.
 *
 * Queued so a moderator clicking "publish" in Filament never waits on SMTP —
 * and a mail failure can't roll back the state transition, which has already
 * been persisted by the time ListingPublished fires.
 *
 * The mail deliberately carries no share buttons: clipboard access needs JS,
 * which mail clients don't run. It links to the listing, where the share
 * panel does the work.
 */
class ListingPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je advertentie staat live: '.$this->listing->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.listing-published',
            with: [
                'title' => $this->listing->title,
                'url' => app(ShareLinkBuilder::class)->emailUrl($this->listing),
                'photoUrl' => $this->listing->photos->first()?->urlFor('card'),
            ],
        );
    }
}
```

- [ ] **Step 4: Write the mail view**

`resources/views/emails/listing-published.blade.php`:

```blade
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je advertentie staat live</title>
</head>
{{-- House style is the light datasheet. Fonts are system fallbacks: mail
     clients don't load our self-hosted woff2. --}}
<body style="margin:0;padding:0;background:#F5F6F6;color:#17191B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;line-height:1.7;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:#D9480F;margin:0 0 24px;">
            cloudmarktplaats.nl
        </p>

        <div style="background:#FFFFFF;border:1px solid #D9DDDE;padding:24px;">
            <p style="margin:0 0 16px;">Hoi,</p>

            <p style="margin:0 0 16px;">
                Je advertentie <strong>{{ $title }}</strong> is goedgekeurd en staat live.
            </p>

            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $title }}" width="512" style="width:100%;max-width:512px;height:auto;border:1px solid #D9DDDE;margin:0 0 16px;">
            @endif

            <p style="margin:0 0 24px;">
                Deel 'm om de eerste reacties binnen te krijgen — op de pagina staan knoppen
                voor LinkedIn en MainDeck, en een kant-en-klare tekst om te kopiëren.
            </p>

            <p style="margin:0;">
                <a href="{{ $url }}" style="display:inline-block;background:#17191B;color:#FFFFFF;text-decoration:none;padding:12px 20px;font-weight:700;">
                    Bekijk &amp; deel je advertentie
                </a>
            </p>
        </div>

        <p style="margin:24px 0 0;font-size:13px;color:#5C6166;">
            Je krijgt deze mail omdat je een advertentie op Cloudmarktplaats hebt geplaatst.
        </p>
    </div>
</body>
</html>
```

- [ ] **Step 5: Write the listener**

`app/Listeners/Listings/SendListingPublishedMail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners\Listings;

use App\Events\Listings\ListingPublished;
use App\Mail\ListingPublishedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Mails the seller when their listing goes live.
 *
 * No dedupe: a listing can legitimately reach `published` more than once
 * (rejected → draft → pending_review → published), and being told your
 * listing is now actually approved is the point.
 */
class SendListingPublishedMail
{
    public function handle(ListingPublished $event): void
    {
        $owner = $event->listing->user;

        if (! $owner instanceof User || $owner->email === null) {
            return;
        }

        // The Mailable is ShouldQueue, so this only pushes onto the queue.
        Mail::to($owner->email)->send(new ListingPublishedMail($event->listing));
    }
}
```

- [ ] **Step 6: Register the listener**

In `app/Providers/AppServiceProvider.php`, voeg de import toe:

```php
use App\Listeners\Listings\SendListingPublishedMail;
```

en registreer direct ná de bestaande `ListingPublished`-listener in `boot()`:

```php
        Event::listen(
            ListingPublished::class,
            AwardInviteKarmaOnFirstListing::class,
        );

        Event::listen(
            ListingPublished::class,
            SendListingPublishedMail::class,
        );
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=ListingPublishedMailTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Verify the real mail in Mailpit**

Run:
```bash
docker compose exec -T -u www-data php-fpm php artisan tinker --execute="
\$l = App\Models\Listing::factory()->create(['state' => 'pending_review']);
app(App\Services\Listings\ListingStateService::class)->transition(\$l, 'published');
echo 'sent for listing '.\$l->id;
"
```
Open `http://localhost:8025` (Mailpit).
Expected: mail "Je advertentie staat live: …", lichte opmaak, knop linkt naar `/listings/…?utm_source=email&utm_medium=email&utm_campaign=listing_published`.

Draait de queue-worker niet, dan blijft de mail in de queue staan — check met `docker compose logs queue-worker`.

- [ ] **Step 9: Commit**

```bash
git add app/Mail/ListingPublishedMail.php \
        app/Listeners/Listings/SendListingPublishedMail.php \
        resources/views/emails/listing-published.blade.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/Listings/ListingPublishedMailTest.php
git commit -m "Publicatie-mail naar de verkoper bij goedkeuring

Queued mailable zodat de moderator in Filament niet op SMTP wacht en een
mailfout de state-transitie niet raakt. Bewust geen share-knoppen in de mail:
clipboard vereist JS. De mail linkt naar het share-paneel."
```

---

### Task 5: Volledige suite + statische analyse

- [ ] **Step 1: Run the full test suite**

Run: `docker compose exec -T -u www-data php-fpm php artisan test`
Expected: alles groen. Let met name op `ModerationSkipTest`, `ListingResourceTest` en `ListingStateServiceTest` — die vuren `ListingPublished` en raken nu de nieuwe listener. Zien ze onverwacht mail-fouten, dan mist `Mail::fake()` in die test.

- [ ] **Step 2: Pint**

Run: `docker compose exec -T -u 1000 php-fpm ./vendor/bin/pint --test`
Expected: PASS. Bij failures: `./vendor/bin/pint` zonder `--test`.

- [ ] **Step 3: PHPStan**

Run: `docker compose exec -T -u 1000 -e TMPDIR=/tmp/pstan php-fpm sh -c 'mkdir -p /tmp/pstan && ./vendor/bin/phpstan analyse --no-progress'`
Expected: PASS.

- [ ] **Step 4: Commit fixes if any**

```bash
git add -A && git commit -m "Pint/PHPStan-fixes voor de share-feature"
```

---

## Deploy-aandachtspunten

Volgt `[[prod-deploy-runbook]]`. Twee dingen die specifiek voor deze feature misgaan:

- **Nieuwe Blade-component + gewijzigde views** → `view:clear` als www-data.
- **Geen nieuwe routes en geen config-wijziging**, dus route/config-cache hoeft niet opnieuw. Wél `restart php-fpm queue-worker` (de queue-worker draait de nieuwe Mailable) en **daarna `restart nginx`**, anders 502't alles.
