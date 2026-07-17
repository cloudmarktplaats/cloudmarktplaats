# schema.org JSON-LD — ontwerp

**Datum:** 2026-07-17
**Doel:** structured data (JSON-LD) toevoegen zodat Google rich results kan tonen — merk-signaal op de homepage en Product-rich-results (prijs, beschikbaarheid, conditie) onder advertentie-zoekresultaten.

## Context & scope

Lighthouse scoort de site al 100 op SEO en Best Practices; structured data telt Lighthouse níét mee (de audit is "manual"). Dit is dus geen score-fix maar een **zoek-verschijning**-verbetering: JSON-LD stuurt de weergave in Google zelf, niet het Lighthouse-cijfer.

De marketing-layout (`resources/views/components/layouts/marketing.blade.php`) heeft al een `$jsonLd`-prop-slot die als `<script type="application/ld+json">{!! $jsonLd !!}</script>` in de `<head>` wordt gerenderd. De FAQ-pagina vult die slot al met `FAQPage`-JSON-LD via een inline `@php`-blok. Dit ontwerp hergebruikt exact dat mechanisme.

**In scope:**
- Homepage (`pages/home.blade.php`): `Organization` + `WebSite` in één `@graph`.
- Advertentie-detailpagina (`App\Livewire\Listings\Detail`): `Product` + `Offer`, **alleen** bij `state === 'published'`.

**Buiten scope (bewust):**
- **Homelab-pagina's** — het anonimiteitscontract verbiedt dat de bouwer herleidbaar wordt; geen enkele schema die identiteit kan lekken.
- **Verkoper (`seller`) in de Offer** — exposeert een username zonder rich-result-winst.
- **`brand` / `sku` / `mpn` / `priceValidUntil`** — geen betrouwbare bron in het datamodel.
- **`WebSite.potentialAction` (sitelinks-searchbox)** — Google heeft die feature grotendeels uitgefaseerd; zou dode markup zijn.
- **`sold`-advertenties** — de regel is "schema alleen bij `published`", wat de bestaande OG-gate spiegelt. Een verkochte pagina krijgt géén schema (geen `SoldOut`). Dit houdt de gate één simpele conditie en is consistent met hoe de OG-tags al werken.

## De published-gate (belangrijkste correctheidseis)

`App\Livewire\Listings\Detail::render()` verrijkt de `layoutData` (title, description, ogImage, canonical) **alleen** wanneer `$this->listing->state === 'published'`; voor `draft` / `pending_review` / `rejected` / `sold` / `archived` valt het terug op de layout-defaults, zodat een nog-niet-publieke advertentie geen titel/foto/prijs via meta-tags lekt.

Het Product-JSON-LD haakt op **exact dezelfde gate** aan: de `jsonLd`-sleutel wordt toegevoegd aan de `layoutData`-array die al alléén in de published-tak wordt teruggegeven. Buiten die tak wordt `jsonLd` nooit gezet, dus `$jsonLd` is `null` en de layout rendert geen `<script>`.

## Datamodel (bestaand, niet wijzigen)

`App\Models\Listing`:
- `condition` — enum `['new', 'used', 'defective', 'for_parts']`
- `state` — enum `['draft', 'pending_review', 'published', 'sold', 'archived', 'rejected']`
- `price_cents` — integer (prijs in eurocenten)
- `title`, `description` (nullable), `ulid`, `slug`
- `photos()` — HasMany `ListingPhoto`; `ListingPhoto::urlFor(string $variant = 'card'): string` levert de absolute URL; `urlFor('original')` geeft de volledige originele afbeelding.

Route: `listings.detail` met params `['ulid' => ..., 'slug' => ...]` → `/listings/{ulid}-{slug}`.

## Component 1 — `Organization` + `WebSite` (homepage)

Inline `@php`-blok bovenaan `resources/views/pages/home.blade.php`, hetzelfde patroon als `pages/faq.blade.php`. Bouw een array met een `@graph` en geef die als `:jsonLd="$jsonLd"` aan de layout-component.

```php
@php
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => url('/') . '#organization',
                'name' => 'Cloudmarktplaats',
                'url' => url('/'),
                'logo' => asset('icon-512.png'),
                'sameAs' => [
                    'https://github.com/cloudmarktplaats/cloudmarktplaats',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/') . '#website',
                'name' => 'Cloudmarktplaats',
                'url' => url('/'),
                'publisher' => ['@id' => url('/') . '#organization'],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
```

Statische data, geen model-logica — inline blade is hier het juiste niveau en houdt het consistent met de FAQ. Het bestaande `<x-layouts.marketing ...>` in `home.blade.php` krijgt er één attribuut bij: `:jsonLd="$jsonLd"`.

## Component 2 — `Product` + `Offer` (advertentie)

Een testbare klasse `app/Support/ListingJsonLd.php` (echte logica: conditie-mapping, prijs-formattering, foto-URL's — hoort niet in een blade).

**Interface:**
```php
namespace App\Support;

use App\Models\Listing;

class ListingJsonLd
{
    /** JSON-string (application/ld+json) voor een gepubliceerde advertentie. */
    public function toJson(Listing $listing): string;
}
```

**Opbouw van de array:**
```php
[
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $listing->title,
    // description alleen als niet-leeg:
    'description' => <trim($listing->description), weggelaten indien ''>,
    'image' => [ <elke foto: $photo->urlFor('original')> ],
    'offers' => [
        '@type' => 'Offer',
        'price' => number_format($listing->price_cents / 100, 2, '.', ''),
        'priceCurrency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
        'itemCondition' => <schema-conditie, zie mapping>,
        'url' => route('listings.detail', ['ulid' => $listing->ulid, 'slug' => $listing->slug]),
    ],
]
```

Encodeer met `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.

**Conditie-mapping** (`condition` → schema.org `itemCondition`-URL):

| `condition` | schema.org |
|---|---|
| `new` | `https://schema.org/NewCondition` |
| `used` | `https://schema.org/UsedCondition` |
| `defective` | `https://schema.org/DamagedCondition` |
| `for_parts` | `https://schema.org/DamagedCondition` |

Gebruik een `match`-expressie met een default naar `UsedCondition` (defensief; het enum garandeert de vier waarden, maar een `match` zonder default gooit een `\UnhandledMatchError` bij toekomstige enum-uitbreiding — een default is veiliger dan een crash op de detail-render).

**Velden bewust weggelaten:** `sku`, `brand`, `mpn`, `priceValidUntil`, `seller` (zie scope).

**Integratie in `Detail`:** in `render()`, binnen de bestaande `state === 'published'`-tak, voeg toe aan de `layoutData`-array:
```php
'jsonLd' => app(ListingJsonLd::class)->toJson($this->listing),
```
Geen aparte gate, geen extra conditie — het erft de gate die er al is.

## Foto's & WebP

De OG-code (`Detail::ogImageUrl()`) valt terug op `null` voor WebP-originelen omdat LinkedIns crawler WebP onbetrouwbaar decodeert. **Voor JSON-LD geldt die beperking niet** — Googlebot verwerkt WebP prima — dus het `image`-veld gebruikt `urlFor('original')` voor elke foto zonder mime-filter. Als een advertentie geen foto's heeft, wordt `image` een lege array; laat het veld dan weg (Google's Product vereist geen image, maar een lege array is rommelig).

## Foutafhandeling

- Geen nieuwe faalpaden: alle data komt uit een reeds-geladen `Listing`-model. `price_cents` is `NOT NULL` op een published advertentie; `title` idem.
- `description` nullable → veld weggelaten bij leeg (geen `null` in de JSON).
- De `match` op `condition` heeft een default → geen `UnhandledMatchError` op de render.

## Testplan (Pest)

**Unit — `tests/Unit/Support/ListingJsonLdTest.php`:**
1. Een gepubliceerde advertentie levert valide JSON die decodeert naar `@type: Product` met de juiste `name`.
2. `offers.price` is de string `"125.00"` voor `price_cents = 12500`, `priceCurrency` = `EUR`, `availability` = `.../InStock`.
3. Conditie-mapping: elk van de vier `condition`-waarden levert de juiste `itemCondition`-URL (datatest over de vier waarden).
4. Foto's verschijnen als array van `original`-URL's; een advertentie zonder foto's heeft geen `image`-sleutel.
5. Een lege `description` levert geen `description`-sleutel; een gevulde wél.

**Feature — `tests/Feature/StructuredData/`:**
6. `GET` op een gepubliceerde detail-pagina bevat `"@type":"Product"` in de HTML.
7. `GET` op een `draft`-detail-pagina (als eigenaar, want drafts zijn niet publiek) bevat **geen** `"@type":"Product"` — de gate.
8. `GET /` (als gast) bevat `"@type":"Organization"` én `"@type":"WebSite"`.

## Bestanden

- **Maken:** `app/Support/ListingJsonLd.php`
- **Wijzigen:** `resources/views/pages/home.blade.php` (inline `@php` + `:jsonLd`-attribuut), `app/Livewire/Listings/Detail.php` (`jsonLd`-sleutel in de published `layoutData`)
- **Test maken:** `tests/Unit/Support/ListingJsonLdTest.php`, `tests/Feature/StructuredData/ProductSchemaTest.php`, `tests/Feature/StructuredData/HomepageSchemaTest.php`
- **Geen wijziging** aan de layout-blade (de `$jsonLd`-slot bestaat al), geen migratie, geen nieuwe route, geen config-key.

## Uitrol

Puur code + één blade + één PHP-klasse. Geen migratie, geen nieuwe route (dus **geen** route-cache-val), geen nginx-wijziging. Uitrol = file-sync + `view:clear` (blades wijzigden) + `php-fpm` restart. Verifiëren ná uitrol met Google's Rich Results Test óf simpelweg `curl` + grep op `"@type":"Product"` op een echte gepubliceerde advertentie en `"@type":"Organization"` op de homepage.
