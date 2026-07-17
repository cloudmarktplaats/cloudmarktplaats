# schema.org JSON-LD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Voeg schema.org JSON-LD toe — `Product`+`Offer` op gepubliceerde advertenties en `Organization`+`WebSite` op de homepage — zodat Google rich results kan tonen.

**Architecture:** De marketing-layout heeft al een `$jsonLd`-prop-slot die als `<script type="application/ld+json">` in de `<head>` rendert (de FAQ vult die al). De advertentie-schema komt uit een testbare `App\Support\ListingJsonLd`-klasse en haakt op de bestaande published-gate in `Detail::render()`. De homepage-schema is statisch en staat inline in de blade, exact zoals de FAQ.

**Tech Stack:** Laravel 11, Livewire 3, Pest, PostgreSQL.

## Global Constraints

- **Alleen `state === 'published'`** krijgt Product-schema — haak aan op de bestaande gate in `Detail::render()`; drafts/pending/rejected/sold/archived lekken zo geen titel/prijs/foto.
- **Homelabs krijgen géén schema** (anonimiteitscontract) — dit plan raakt homelab-code niet aan.
- **Geen `seller`, `brand`, `sku`, `mpn`, `priceValidUntil`** in de Offer (geen betrouwbare bron / privacy).
- **Geen `WebSite.potentialAction`** (sitelinks-searchbox is door Google uitgefaseerd).
- **Prijs** = string met twee decimalen en punt-scheiding: `number_format($cents / 100, 2, '.', '')` → `"125.00"`.
- **Conditie-mapping:** `new`→`NewCondition`, `used`→`UsedCondition`, `defective`+`for_parts`→`DamagedCondition`, met een `default`-arm (voorkomt `UnhandledMatchError` bij toekomstige enum-uitbreiding).
- **Foto's:** `urlFor('original')` voor elke foto, géén mime-filter (Googlebot verwerkt WebP prima; de OG-null-fallback geldt hier niet).
- **JSON-encoding:** `json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.
- **`description`** wordt weggelaten als leeg; `image` wordt weggelaten als er geen foto's zijn.
- **Tests staan onder `tests/Feature/`** — in dit project krijgt alleen `Feature` `RefreshDatabase`+`TestCase` (zie `tests/Pest.php`); `tests/Unit` heeft geen DB/app-harness, dus ook de klasse-gerichte test leeft onder `Feature`.
- Geen migratie, geen nieuwe route, geen nginx-wijziging.

---

## File Structure

- **`app/Support/ListingJsonLd.php`** (nieuw) — bouwt de Product+Offer JSON-string uit een `Listing`. Enige verantwoordelijkheid: model → JSON-LD. Geen kennis van de gate (de caller gate't).
- **`app/Livewire/Listings/Detail.php`** (wijzigen) — voegt in de bestaande published-`layoutData` één `jsonLd`-sleutel toe.
- **`resources/views/pages/home.blade.php`** (wijzigen) — inline `@php`-blok met `Organization`+`WebSite` `@graph` + `:jsonLd`-attribuut op de layout-component.
- **`tests/Feature/StructuredData/ListingJsonLdTest.php`** (nieuw) — klasse-gerichte tests.
- **`tests/Feature/StructuredData/ProductSchemaTest.php`** (nieuw) — de gate, door de HTTP-laag.
- **`tests/Feature/StructuredData/HomepageSchemaTest.php`** (nieuw) — homepage-schema, door de HTTP-laag.

---

### Task 1: `ListingJsonLd`-klasse (Product + Offer)

**Files:**
- Create: `app/Support/ListingJsonLd.php`
- Test: `tests/Feature/StructuredData/ListingJsonLdTest.php`

**Interfaces:**
- Consumes: `App\Models\Listing` (`title`, `description` nullable, `condition`, `price_cents`, `ulid`, `slug`, `photos()` HasMany); `App\Models\ListingPhoto::urlFor(string $variant = 'card'): string`; route `listings.detail` met params `['ulid', 'slug']`.
- Produces: `App\Support\ListingJsonLd::toJson(Listing $listing): string` — een `application/ld+json`-string. Task 2 (Detail-integratie) roept dit aan.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/StructuredData/ListingJsonLdTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\ListingJsonLd;

/** Decodeer de JSON-LD van een advertentie naar een array. */
function listingJsonLd(Listing $listing): array
{
    return json_decode(
        app(ListingJsonLd::class)->toJson($listing),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

it('builds a Product with the listing title and schema context', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Cisco 6509']);

    $data = listingJsonLd($listing);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('Product')
        ->and($data['name'])->toBe('Cisco 6509');
});

it('formats price as a two-decimal string, EUR, in stock', function () {
    $listing = Listing::factory()->published()->create(['price_cents' => 12500]);

    $offer = listingJsonLd($listing)['offers'];

    expect($offer['@type'])->toBe('Offer')
        ->and($offer['price'])->toBe('125.00')
        ->and($offer['priceCurrency'])->toBe('EUR')
        ->and($offer['availability'])->toBe('https://schema.org/InStock');
});

it('points the offer url at the canonical listing route', function () {
    $listing = Listing::factory()->published()->create();

    expect(listingJsonLd($listing)['offers']['url'])
        ->toBe(route('listings.detail', ['ulid' => $listing->ulid, 'slug' => $listing->slug]));
});

it('maps each condition to the right schema.org itemCondition', function (string $condition, string $expected) {
    $listing = Listing::factory()->published()->create(['condition' => $condition]);

    expect(listingJsonLd($listing)['offers']['itemCondition'])->toBe($expected);
})->with([
    ['new', 'https://schema.org/NewCondition'],
    ['used', 'https://schema.org/UsedCondition'],
    ['defective', 'https://schema.org/DamagedCondition'],
    ['for_parts', 'https://schema.org/DamagedCondition'],
]);

it('lists every photo as an original-variant URL', function () {
    $listing = Listing::factory()->published()->create();
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/a/1/card.webp', 'position' => 1]);
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/a/2/card.webp', 'position' => 2]);

    $images = listingJsonLd($listing)['image'];

    expect($images)->toHaveCount(2)
        ->and($images[0])->toContain('/1/original.')
        ->and($images[1])->toContain('/2/original.');
});

it('omits image when the listing has no photos', function () {
    $listing = Listing::factory()->published()->create();

    expect(listingJsonLd($listing))->not->toHaveKey('image');
});

it('omits description when empty and includes it otherwise', function () {
    $without = Listing::factory()->published()->create(['description' => null]);
    $with = Listing::factory()->published()->create(['description' => 'Compleet met rails.']);

    expect(listingJsonLd($without))->not->toHaveKey('description')
        ->and(listingJsonLd($with)['description'])->toBe('Compleet met rails.');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/StructuredData/ListingJsonLdTest.php`
Expected: FAIL — `Class "App\Support\ListingJsonLd" not found`.

(Draai binnen Docker zoals gebruikelijk in dit project: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/ListingJsonLdTest.php`.)

- [ ] **Step 3: Write the class**

Create `app/Support/ListingJsonLd.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Listing;

/**
 * Bouwt schema.org Product+Offer JSON-LD voor een advertentie.
 *
 * Deze klasse kent de published-gate NIET — de caller
 * ({@see \App\Livewire\Listings\Detail::render()}) roept dit alleen aan
 * binnen de published-tak, zodat een niet-publieke advertentie geen
 * prijs/titel/foto via structured data lekt.
 */
class ListingJsonLd
{
    public function toJson(Listing $listing): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $listing->title,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($listing->price_cents / 100, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'itemCondition' => $this->itemCondition($listing->condition),
                'url' => route('listings.detail', [
                    'ulid' => $listing->ulid,
                    'slug' => $listing->slug,
                ]),
            ],
        ];

        $description = trim((string) $listing->description);

        if ($description !== '') {
            $data['description'] = $description;
        }

        $images = $listing->photos
            ->map(fn ($photo) => $photo->urlFor('original'))
            ->all();

        if ($images !== []) {
            $data['image'] = array_values($images);
        }

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * `condition`-enum → schema.org itemCondition-URL. De default-arm vangt
     * een toekomstige enum-uitbreiding af zodat de detail-render niet crasht
     * op een UnhandledMatchError.
     */
    private function itemCondition(string $condition): string
    {
        return match ($condition) {
            'new' => 'https://schema.org/NewCondition',
            'used' => 'https://schema.org/UsedCondition',
            'defective', 'for_parts' => 'https://schema.org/DamagedCondition',
            default => 'https://schema.org/UsedCondition',
        };
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/ListingJsonLdTest.php`
Expected: PASS — alle tests groen (de conditie-datatest telt als vier cases).

- [ ] **Step 5: Commit**

```bash
git add app/Support/ListingJsonLd.php tests/Feature/StructuredData/ListingJsonLdTest.php
git commit -m "Add ListingJsonLd: Product+Offer schema.org builder"
```

---

### Task 2: Detail-integratie + de published-gate

**Files:**
- Modify: `app/Livewire/Listings/Detail.php` (in `render()`, de bestaande `state === 'published'`-`layoutData`-tak — rond regel 116-125)
- Test: `tests/Feature/StructuredData/ProductSchemaTest.php`

**Interfaces:**
- Consumes: `App\Support\ListingJsonLd::toJson(Listing): string` (Task 1); de layout-prop `jsonLd` die als `<script type="application/ld+json">{!! $jsonLd !!}</script>` rendert.
- Produces: gepubliceerde detail-pagina's bevatten `"@type":"Product"` in de HTML; niet-gepubliceerde niet.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/StructuredData/ProductSchemaTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Listing;

it('emits Product JSON-LD on a published listing', function () {
    $listing = Listing::factory()->published()->create([
        'title' => 'Cisco 6509',
        'price_cents' => 12500,
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"price":"125.00"', false);
});

it('does not emit Product JSON-LD on a non-published listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'draft',
        'title' => 'Geheime Cisco',
    ]);

    // De eigenaar mag de draft previewen; de structured data mag er niet zijn.
    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('"@type":"Product"', false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/ProductSchemaTest.php`
Expected: de eerste test FAILT (geen `"@type":"Product"` in de HTML); de tweede test SLAAGT al (er is nog geen Product-schema, dus `assertDontSee` klopt).

- [ ] **Step 3: Wire the class into the published branch**

In `app/Livewire/Listings/Detail.php`, `render()`, voeg in de bestaande published-`layoutData`-array de `jsonLd`-sleutel toe. Van:

```php
        return $view->layoutData([
            'title' => $this->listing->title.' — Cloudmarktplaats',
            'description' => $this->ogDescription(),
            'ogImage' => $this->ogImageUrl(),
            'canonical' => route('listings.detail', [
                'ulid' => $this->listing->ulid,
                'slug' => $this->listing->slug,
            ]),
        ]);
```

naar:

```php
        return $view->layoutData([
            'title' => $this->listing->title.' — Cloudmarktplaats',
            'description' => $this->ogDescription(),
            'ogImage' => $this->ogImageUrl(),
            'canonical' => route('listings.detail', [
                'ulid' => $this->listing->ulid,
                'slug' => $this->listing->slug,
            ]),
            'jsonLd' => app(\App\Support\ListingJsonLd::class)->toJson($this->listing),
        ]);
```

Voeg bovenaan het bestand de import toe bij de bestaande `use`-regels:

```php
use App\Support\ListingJsonLd;
```

en gebruik dan `app(ListingJsonLd::class)` in plaats van het volledige pad. (Geen andere wijziging in `render()` — de `state !== 'published'`-tak hierboven blijft ongewijzigd, dus de gate erft vanzelf.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/ProductSchemaTest.php`
Expected: PASS — beide tests groen.

- [ ] **Step 5: Run the OG-tests to confirm no regression**

De gate deelt de `layoutData` met de OG-tags; draai die suite om zeker te zijn dat de published/niet-published-splitsing intact is.

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Listings/ListingOgTagsTest.php`
Expected: PASS — ongewijzigd groen.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Listings/Detail.php tests/Feature/StructuredData/ProductSchemaTest.php
git commit -m "Emit Product JSON-LD on published listings, gated like OG tags"
```

---

### Task 3: Homepage `Organization` + `WebSite`

**Files:**
- Modify: `resources/views/pages/home.blade.php` (top van het bestand + het `<x-layouts.marketing ...>`-openingstag)
- Test: `tests/Feature/StructuredData/HomepageSchemaTest.php`

**Interfaces:**
- Consumes: de layout-prop `jsonLd` (zelfde slot als Task 2).
- Produces: `GET /` (als gast) bevat `"@type":"Organization"` én `"@type":"WebSite"`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StructuredData/HomepageSchemaTest.php`:

```php
<?php

declare(strict_types=1);

it('emits Organization and WebSite JSON-LD on the guest homepage', function () {
    // Ingelogde gebruikers worden naar /listings geleid; de marketing-home
    // met schema is de gast-variant.
    $this->get('/')
        ->assertOk()
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/HomepageSchemaTest.php`
Expected: FAIL — geen `"@type":"Organization"` in de HTML.

- [ ] **Step 3: Add the inline `@php` block and the `:jsonLd` attribute**

In `resources/views/pages/home.blade.php`, voeg **helemaal bovenaan** (vóór de `<x-layouts.marketing`-regel) dit `@php`-blok toe:

```php
@php
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => url('/').'#organization',
                'name' => 'Cloudmarktplaats',
                'url' => url('/'),
                'logo' => asset('icon-512.png'),
                'sameAs' => [
                    'https://github.com/cloudmarktplaats/cloudmarktplaats',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'name' => 'Cloudmarktplaats',
                'url' => url('/'),
                'publisher' => ['@id' => url('/').'#organization'],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
```

Voeg vervolgens aan het bestaande `<x-layouts.marketing ...>`-openingstag het attribuut `:jsonLd="$jsonLd"` toe. Van:

```blade
<x-layouts.marketing
    title="Cloudmarktplaats — geen Marktplaats, geen Tweakers V&A, wel kabels en RAM"
    description="Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin. Open source, privacy by design, geen cookiebanner-theater."
    :canonical="url('/')"
>
```

naar:

```blade
<x-layouts.marketing
    title="Cloudmarktplaats — geen Marktplaats, geen Tweakers V&A, wel kabels en RAM"
    description="Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin. Open source, privacy by design, geen cookiebanner-theater."
    :canonical="url('/')"
    :jsonLd="$jsonLd"
>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/HomepageSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Run the whole StructuredData suite + FAQ schema regression**

De FAQ gebruikt dezelfde `$jsonLd`-slot; draai kort de hele nieuwe suite en bevestig dat de FAQ-pagina nog laadt.

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/StructuredData/`
Expected: PASS — alle drie de bestanden groen.

- [ ] **Step 6: Commit**

```bash
git add resources/views/pages/home.blade.php tests/Feature/StructuredData/HomepageSchemaTest.php
git commit -m "Add Organization+WebSite JSON-LD to the homepage"
```

---

## Uitrol (na merge)

Puur code + één blade + één PHP-klasse. **Geen** migratie, **geen** nieuwe route (dus geen route-cache-val), **geen** nginx-wijziging.

1. File-sync van `app/Support/ListingJsonLd.php`, `app/Livewire/Listings/Detail.php`, `resources/views/pages/home.blade.php` naar LXC 214 (chown 1000:1000).
2. `php artisan view:clear` als www-data (blades wijzigden).
3. `docker compose -f docker-compose.prod.yml restart php-fpm` (nieuwe klasse + component).
4. Verifiëren door de publieke laag:
   - `curl -s https://cloudmarktplaats.nl/ | grep -o '"@type":"Organization"'` → één treffer.
   - `curl -s https://cloudmarktplaats.nl/<een-published-advertentie> | grep -o '"@type":"Product"'` → één treffer.
   - Optioneel: plak beide URL's in Google's Rich Results Test.

---

## Self-Review

**Spec-dekking:**
- Homepage Organization+WebSite → Task 3. ✅
- Advertentie Product+Offer + published-gate → Task 1 (bouwer) + Task 2 (gate/integratie). ✅
- Conditie-mapping vier waarden met default → Task 1, Step 3 + datatest Step 1. ✅
- Prijs `"125.00"`-string, EUR, InStock → Task 1. ✅
- Foto's `original` zonder mime-filter, weglaten indien leeg → Task 1. ✅
- Description weglaten indien leeg → Task 1. ✅
- Geen seller/brand/sku/searchbox → nergens toegevoegd, expliciet in Global Constraints. ✅
- Homelabs ongemoeid → geen homelab-bestand in het plan. ✅
- Testplan (unit-equivalent + twee feature-tests) → Task 1/2/3. ✅ (Alle onder `Feature` vanwege `tests/Pest.php`; afwijking t.o.v. de spec-padnaam bewust, in Global Constraints toegelicht.)

**Placeholder-scan:** geen TBD/TODO; alle code volledig uitgeschreven. ✅

**Type-consistentie:** `ListingJsonLd::toJson(Listing): string` identiek gebruikt in Task 1 (definitie), Task 2 (`app(ListingJsonLd::class)->toJson(...)`). `urlFor('original')` conform `ListingPhoto`. `route('listings.detail', ['ulid','slug'])` conform bestaande code. ✅
