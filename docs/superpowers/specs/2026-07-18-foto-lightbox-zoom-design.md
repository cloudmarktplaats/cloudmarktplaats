# Foto-lightbox met zoom — ontwerp

**Datum:** 2026-07-18
**Doel:** foto's op advertentie- en homelab-detailpagina's fullscreen laten bekijken en inzoomen, zodat kopers de conditie van hardware echt kunnen beoordelen (de kern-conversiedrempel) en homelab-setups tot hun recht komen.

## Context & motivatie

De detailpagina's tonen nu alleen de **`card`-variant (600×600, `object-cover` — bijgesneden)**. De **`original`-variant (tot 2000px lange zijde)** wordt al gegenereerd en opgeslagen maar nergens aan de kijker getoond (alleen als og:image). Bij een hardware-marktplaats ís de foto de inspectie: een 600px-crop verbergt precies het detail (kras vs. reflectie, scheve pin, model/revisie op het label) dat de koopbeslissing bepaalt. Dit ontwerp maakt de bestaande `original` zichtbaar via een lightbox met zoom — **geen nieuwe beeldverwerking nodig**.

Gekozen aanpak (waarden-keuze door de eigenaar): **zelf bouwen met Alpine**, geen dependency. Mobiel leunt op native browser-pinch-zoom (gratis, vloeiend); desktop krijgt een zelf-geschreven klik/scroll-zoom met pan. Past bij het "own-your-stack, minimale JS"-principe; het project gebruikt Alpine al via Livewire.

## Scope

**In scope:**
- Eén herbruikbaar Blade+Alpine-component dat de thumbnail-grid én de lightbox-overlay levert.
- Toegepast op **advertentie-detail** (`resources/views/livewire/listings/detail.blade.php`) en **homelab-detail** (`resources/views/livewire/homelab/detail.blade.php`).

**Buiten scope (bewust):**
- De feed-/browse-grids (die linken naar detail; de lightbox leeft op de detailpagina).
- Homelab-prominentie / social-feed — aparte, latere ronde.
- Nieuwe fotovarianten of wijziging aan de foto-jobs — de `original` volstaat.
- Een multi-touch pinch-engine in JS — mobiel gebruikt native pinch; dat is bewust weggelaten om het janky stuk te vermijden.

## Componentontwerp

Eén Blade-component `resources/views/components/photo-lightbox.blade.php`, aangeroepen als:

```blade
<x-photo-lightbox :photos="$photosPayload" :columns="3" />
```

- **`:photos`** — een array van `['card' => <card-URL>, 'original' => <original-URL>, 'alt' => <alt-tekst>]` per foto. De aanroepende blade bouwt dit uit `$listing->photos` resp. `$post->photos` met `$photo->urlFor('card')` en `$photo->urlFor('original')`. De grid toont `card`, de lightbox laadt `original`.
- **`:columns`** — het aantal kolommen in de thumbnail-grid op `sm` en groter (advertentie: 3, homelab: 2). Mobiel is altijd 1 kolom. Default 3.

Het component rendert twee dingen:

1. **De thumbnail-grid** — vervangt de huidige ad-hoc grids op beide pagina's (die vrijwel identiek zijn; dit consolideert ze). Thumbnails blijven de **`card`-variant, `aspect-[4/3] object-cover`** (netjes bijgesneden). Elke thumbnail is een `<button>` met `@click="show(index)"` en een toegankelijk label (bijv. "Foto :n van :total vergroten").
   - Let op: de thumbnail-grid gebruikt de `card`-URL, niet de `original`. Het component krijgt beide nodig, of leidt de `card`-URL af. **Beslissing:** de `:photos`-payload bevat per foto zowel `card` als `original` (`['card' => ..., 'original' => ..., 'alt' => ...]`); de grid toont `card`, de lightbox laadt `original`.

2. **De lightbox-overlay** — een Alpine-root (`x-data="photoLightbox(photos)"`) die verborgen is tot `open`. Bevat:
   - `fixed inset-0 z-50 bg-black/90`, met een `<div role="dialog" aria-modal="true" x-ref="dialog" tabindex="-1">`.
   - De huidige foto als `object-contain` binnen de viewport (dus **niets bijgesneden** — de volledige `original`). De `original` wordt **lui geladen bij openen** (`:src` wordt pas gezet als `open`), niet op de paginalading.
   - Prev/next-chevrons, een teller (`{index+1} / {photos.length}`), en een sluit-knop (×) rechtsboven.

## Interactie

**Zoom — twee paden op één element, schoon gescheiden:**
- **Mobiel (touch):** native browser-pinch-zoom. Geen custom transform; touch-events sturen alléén swipe-navigatie aan (zie onder). Twee-vinger pinch blijft native.
- **Desktop (muis):** een zelf-beheerde `scale` + `translateX/Y`-transform op de fotocontainer:
  - dubbelklik schakelt fit ↔ ware grootte (rond het klikpunt);
  - scroll-wiel zoomt in/uit (begrensd, bijv. 1×–4×);
  - slepen pant wanneer `scale > 1`.
  - Touch raakt dit transform niet, dus de twee zoomsystemen botsen niet.

**Navigatie:**
- Chevrons (overal, muis + tap).
- Toetsenbord: `←`/`→` bladeren, `Esc` sluit (desktop).
- Eén-vinger horizontale swipe (mobiel) bij `scale == 1` (fit-stand). Bij native pinch-zoom pant de browser met één vinger; onze swipe-nav vuurt dan niet — dat is intuïtief en conflictvrij.
- Bij bladeren reset de zoom (`scale=1, tx=0, ty=0`).

**Openen/sluiten:**
- `show(index)` zet de index, opent de overlay, reset zoom, verplaatst focus naar de dialog, en zet `document.body` op `overflow: hidden`.
- `close()` sluit, herstelt `body`-overflow, en **geeft focus terug aan de aangeklikte thumbnail** (bewaar de trigger-referentie).

## Toegankelijkheid

Het project hecht aan toegankelijkheid (de skip-link bestaat al). De overlay:
- `role="dialog"` + `aria-modal="true"`; focus-trap binnen de overlay zolang open.
- `Esc` sluit; focus keert terug naar de trigger-thumbnail.
- Chevrons/sluitknop zijn echte `<button>`s met `aria-label`.
- `prefers-reduced-motion: reduce` → geen zoom-/fade-transities.
- Thumbnails zijn `<button>`s (toetsenbord-bereikbaar), niet klikbare `<div>`s.

## Anonimiteit (homelab)

De `alt`-tekst voor homelabfoto's blijft generiek (`"Homelab-foto"`) — nooit iets identificeerbaars, conform het anonimiteitscontract. De advertentie-`alt` gebruikt de listing-titel (al publiek).

## Bestanden

- **Maken:** `resources/views/components/photo-lightbox.blade.php` (grid + overlay-markup), en de Alpine-logica als geregistreerd component `Alpine.data('photoLightbox', …)` in `resources/js/app.js` (herbruikbaar, één plek).
- **Wijzigen:** `resources/views/livewire/listings/detail.blade.php` (huidige foto-grid, regels ~48-60, vervangen door `<x-photo-lightbox :photos="…" :columns="3" />`), `resources/views/livewire/homelab/detail.blade.php` (idem, regels ~9-17, `:columns="2"`).
- **Test maken:** `tests/Feature/PhotoLightboxTest.php`.
- **Geen wijziging** aan de foto-jobs, modellen of migraties. Geen nieuwe route.

## Foutafhandeling & randgevallen

- **Nul foto's:** het component rendert niets (geen lege grid, geen lege overlay). De aanroepende blade omhult het al met een `@if photos->isNotEmpty()`-check; het component dubbelcheckt op een lege payload.
- **Eén foto:** lightbox opent normaal; chevrons/swipe/teller verbergen (of chevrons disabled). Geen navigatie nodig.
- **`original` ontbreekt op schijf:** `urlFor('original')` levert altijd een pad (geen bestaanscheck); een gebroken beeld toont de browser-fallback. Dit is bestaand gedrag en buiten scope — de jobs schrijven altijd een `original`.
- **JS uit:** de thumbnails zijn `<button>`s zonder href; zonder JS gebeurt er niets bij klik (de foto's zijn nog steeds als `card` zichtbaar). Progressive enhancement — de pagina blijft bruikbaar.

## Testplan

**Pest — `tests/Feature/PhotoLightboxTest.php`** (toetst de bedrading; de gebaren leven in een laag die Pest niet raakt):
1. Een gepubliceerde advertentie-detailpagina bevat het lightbox-component: de thumbnails (aantal = aantal foto's), en de `original`-URL's in de Alpine-payload.
2. De advertentie-`alt` is de listing-titel.
3. Een homelab-detailpagina bevat het component met **generieke** `alt` (`"Homelab-foto"`) — geen identificerende tekst (anonimiteit).
4. Een detailpagina zónder foto's rendert géén lightbox-markup (geen lege overlay/grid).

**Handmatig / browser** (headless Chrome + echte telefoon voor de laatste 20%):
- Desktop: klik opent fullscreen; dubbelklik/scroll zoomt; slepen pant; pijltjes + Esc werken.
- Mobiel: tik opent; native pinch-zoom werkt; één-vinger-swipe bladert in fit-stand.
- Toegankelijkheid: Esc sluit, focus keert terug naar de thumbnail, focus-trap houdt.

## Uitrol

Puur front-end: één component, één `app.js`-toevoeging, twee blades. **Vite-build nodig** (`npm run build`) — de `app.js`-wijziging moet in `public/build`. Uitrol = build lokaal, dan file-sync van `public/build` + de twee blades + het nieuwe component naar LXC 214, `view:clear`, `php-fpm` restart, nginx restart. Geen migratie, geen route, geen config. Verifieer in de browser op prod dat de lightbox opent en de `original` (niet de 600px-crop) toont.
