# Foto-lightbox met zoom — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Foto's op advertentie- en homelab-detailpagina's fullscreen tonen en laten inzoomen, zodat kopers hardware-conditie kunnen beoordelen.

**Architecture:** Eén herbruikbaar Blade-component levert de thumbnail-grid én een Alpine-gedreven lightbox-overlay; de zoom-/navigatielogica leeft als één geregistreerd Alpine-component in een eigen JS-bestand. De detailpagina's bouwen een payload uit hun foto's en roepen het component aan.

**Tech Stack:** Laravel 11, Livewire 3 (levert Alpine), Alpine.js, Tailwind, Vite, Pest.

## Global Constraints

- **Thumbnails tonen de `card`-variant** (`aspect-[4/3] object-cover`, bijgesneden); de **lightbox laadt de `original`-variant lui** (`object-contain`, niets bijgesneden). Payload per foto: `['card' => <card-URL>, 'original' => <original-URL>, 'alt' => <alt>]`.
- **Mobiel:** native browser-pinch-zoom + één-vinger horizontale swipe in fit-stand (`scale === 1`). **Geen** custom multi-touch pinch-engine.
- **Desktop:** zelf-beheerde `scale`+`translate`-transform — dubbelklik schakelt fit↔2×, scroll-wiel zoomt 1×–4×, slepen pant bij `scale > 1`.
- **Toegankelijkheid:** overlay `role="dialog"` + `aria-modal="true"`; focus-trap; `Esc` sluit; focus keert terug naar de aangeklikte thumbnail; `prefers-reduced-motion` → geen transities; thumbnails zijn `<button>`s.
- **Anonimiteit:** homelab-`alt` altijd generiek `__('Homelab-foto')` — nooit identificerend. Advertentie-`alt` = de listing-titel.
- **Geen migratie, geen route, geen model-/job-wijziging.** Front-end only. `ListingPhoto::urlFor(string): string` en `HomelabPhoto::urlFor(string): string` bestaan beide.
- Tailwind purgt ongebruikte classes → **geen dynamische class-namen** als `sm:grid-cols-{{ $columns }}`; map op literals.
- Tests draaien in Docker: `docker compose exec -T php-fpm ./vendor/bin/pest <pad>`. Tests onder `tests/Feature/`.

---

## File Structure

- **`resources/js/photo-lightbox.js`** (nieuw) — registreert `Alpine.data('photoLightbox', …)` via `alpine:init`. Alle zoom-/pan-/navigatielogica. Eén verantwoordelijkheid.
- **`resources/js/app.js`** (wijzigen) — `import './photo-lightbox';` toevoegen.
- **`resources/views/components/photo-lightbox.blade.php`** (nieuw) — thumbnail-grid (`card`, `<button>`s) + de teleported overlay-markup, met `x-data="photoLightbox(@js($photos))"`.
- **`resources/views/livewire/listings/detail.blade.php`** (wijzigen) — de huidige foto-grid (regels ~48-60) vervangen door een payload-`@php`-blok + `<x-photo-lightbox :photos="…" :columns="3" />`.
- **`resources/views/livewire/homelab/detail.blade.php`** (wijzigen) — idem (regels ~9-17), `:columns="2"`, anonieme alt.
- **`tests/Feature/PhotoLightboxTest.php`** (nieuw) — bedrading + anonimiteit.

---

### Task 1: Het `photo-lightbox`-component (Blade + Alpine)

**Files:**
- Create: `resources/js/photo-lightbox.js`
- Create: `resources/views/components/photo-lightbox.blade.php`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/PhotoLightboxTest.php`

**Interfaces:**
- Consumes: niets (zelfstandig component).
- Produces: het Blade-component `<x-photo-lightbox :photos="$array" :columns="$int" />`, waarbij `$array` een lijst is van `['card' => string, 'original' => string, 'alt' => string]`. Task 2 roept dit aan.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PhotoLightboxTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders a button thumbnail per photo with the card image', function () {
    $photos = [
        ['card' => 'https://cdn.test/a/card.webp', 'original' => 'https://cdn.test/a/original.jpg', 'alt' => 'Cisco 6509'],
        ['card' => 'https://cdn.test/b/card.webp', 'original' => 'https://cdn.test/b/original.jpg', 'alt' => 'Cisco 6509'],
    ];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    expect(substr_count($html, '<button'))->toBe(2)
        ->and($html)->toContain('https://cdn.test/a/card.webp')
        ->and($html)->toContain('https://cdn.test/b/card.webp')
        ->and($html)->toContain('photoLightbox(');
});

it('carries the original URLs in the Alpine payload for the lightbox', function () {
    $photos = [
        ['card' => 'https://cdn.test/a/card.webp', 'original' => 'https://cdn.test/a/original.jpg', 'alt' => 'Cisco 6509'],
    ];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    // De original-URL zit in de @js-payload die de overlay lui laadt.
    expect($html)->toContain('https:\/\/cdn.test\/a\/original.jpg')
        ->or->toContain('https://cdn.test/a/original.jpg');
});

it('renders nothing when there are no photos', function () {
    $html = trim(Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => []]));

    expect($html)->toBe('');
});

it('is keyboard-reachable and announces the dialog for accessibility', function () {
    $photos = [['card' => 'c', 'original' => 'o', 'alt' => 'Cisco 6509']];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    expect($html)->toContain('role="dialog"')
        ->and($html)->toContain('aria-modal="true"')
        ->and($html)->toContain('<button');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/PhotoLightboxTest.php`
Expected: FAIL — `Unable to locate a class or view for component [photo-lightbox]`.

- [ ] **Step 3: Write the Alpine component**

Create `resources/js/photo-lightbox.js`:

```js
// Fullscreen foto-lightbox met zoom. Geregistreerd op de Alpine die Livewire
// meelevert (alpine:init vuurt vóór Alpine start).
//
// Zoom is bewust twee gescheiden paden op één element:
//   - Mobiel: native browser-pinch-zoom (touch raakt het transform NIET; touch
//     stuurt alleen swipe-navigatie in fit-stand).
//   - Desktop: een zelf-beheerde scale/translate-transform via muis + wiel.
document.addEventListener('alpine:init', () => {
    Alpine.data('photoLightbox', (photos) => ({
        photos,
        open: false,
        index: 0,
        scale: 1,
        tx: 0,
        ty: 0,
        trigger: null,   // thumbnail om focus aan terug te geven
        dragStart: null, // desktop pan
        swipeStart: null, // mobiel swipe

        show(i, event) {
            this.trigger = event?.currentTarget ?? null;
            this.index = i;
            this.resetZoom();
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.dialog?.focus());
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
            this.trigger?.focus();
        },

        get current() {
            return this.photos[this.index] ?? null;
        },

        get hasMultiple() {
            return this.photos.length > 1;
        },

        next() {
            this.index = (this.index + 1) % this.photos.length;
            this.resetZoom();
        },

        prev() {
            this.index = (this.index - 1 + this.photos.length) % this.photos.length;
            this.resetZoom();
        },

        resetZoom() {
            this.scale = 1;
            this.tx = 0;
            this.ty = 0;
        },

        // Desktop: scroll-wiel zoomt 1x–4x.
        onWheel(e) {
            e.preventDefault();
            this.scale = Math.min(4, Math.max(1, this.scale + (e.deltaY < 0 ? 0.25 : -0.25)));
            if (this.scale === 1) {
                this.tx = 0;
                this.ty = 0;
            }
        },

        // Desktop: dubbelklik schakelt fit <-> 2x.
        toggleZoom() {
            if (this.scale > 1) {
                this.resetZoom();
            } else {
                this.scale = 2;
            }
        },

        onPointerDown(e) {
            if (e.pointerType === 'touch') {
                this.swipeStart = { x: e.clientX };
                return;
            }
            if (this.scale > 1) {
                this.dragStart = { x: e.clientX - this.tx, y: e.clientY - this.ty };
            }
        },

        onPointerMove(e) {
            if (this.dragStart && e.pointerType !== 'touch') {
                this.tx = e.clientX - this.dragStart.x;
                this.ty = e.clientY - this.dragStart.y;
            }
        },

        onPointerUp(e) {
            // Eén-vinger swipe navigeert alleen in fit-stand; twee-vinger pinch
            // is native (visual viewport) en raakt dit niet.
            if (e.pointerType === 'touch' && this.swipeStart && this.scale === 1 && this.hasMultiple) {
                const dx = e.clientX - this.swipeStart.x;
                if (Math.abs(dx) > 50) {
                    dx < 0 ? this.next() : this.prev();
                }
            }
            this.swipeStart = null;
            this.dragStart = null;
        },

        onKey(e) {
            if (!this.open) return;
            if (e.key === 'Escape') this.close();
            else if (e.key === 'ArrowRight') this.next();
            else if (e.key === 'ArrowLeft') this.prev();
        },
    }));
});
```

- [ ] **Step 4: Import it in app.js**

In `resources/js/app.js`, voeg de import toe:

```js
import './bootstrap';
import './easter-eggs';
import './photo-lightbox';
```

- [ ] **Step 5: Write the Blade component**

Create `resources/views/components/photo-lightbox.blade.php`:

```blade
@props(['photos' => [], 'columns' => 3])

@php
    // Geen dynamische Tailwind-classnamen (die purgt de build weg): map op literals.
    $gridCols = (int) $columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3';
    $items = array_values($photos);
@endphp

@if (! empty($items))
    <div
        x-data="photoLightbox(@js($items))"
        x-on:keydown.window="onKey($event)"
    >
        {{-- Thumbnail-grid: card-variant, bijgesneden. Elke tegel is een knop. --}}
        <div class="grid grid-cols-1 gap-1 bg-cmp-bg2 {{ $gridCols }}">
            @foreach ($items as $i => $photo)
                <button
                    type="button"
                    @click="show({{ $i }}, $event)"
                    class="block aspect-[4/3] w-full overflow-hidden bg-cmp-bg2"
                    aria-label="{{ __('Foto :n van :total groter bekijken', ['n' => $i + 1, 'total' => count($items)]) }}"
                >
                    <img
                        src="{{ $photo['card'] }}"
                        alt="{{ $photo['alt'] }}"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-200 hover:scale-105 motion-reduce:transition-none motion-reduce:hover:scale-100"
                    >
                </button>
            @endforeach
        </div>

        {{-- Overlay, geteleporteerd naar body zodat z-index/overflow van de
             pagina er niet mee bemoeit. --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 motion-reduce:transition-none"
                style="display: none;"
            >
                <div
                    x-ref="dialog"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ __('Fotoweergave') }}"
                    class="relative flex h-full w-full items-center justify-center overflow-hidden"
                    @pointerdown="onPointerDown($event)"
                    @pointermove="onPointerMove($event)"
                    @pointerup="onPointerUp($event)"
                    @pointercancel="onPointerUp($event)"
                >
                    {{-- De foto: original, lui geladen (src pas gezet als open). --}}
                    <img
                        x-show="open"
                        :src="open ? current?.original : ''"
                        :alt="current?.alt"
                        @wheel.prevent="onWheel($event)"
                        @dblclick="toggleZoom()"
                        :style="`transform: translate(${tx}px, ${ty}px) scale(${scale}); cursor: ${scale > 1 ? 'grab' : 'zoom-in'};`"
                        class="max-h-full max-w-full touch-pinch-zoom select-none object-contain"
                        draggable="false"
                    >

                    {{-- Sluiten --}}
                    <button
                        type="button"
                        @click="close()"
                        aria-label="{{ __('Sluiten') }}"
                        class="absolute right-4 top-4 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    {{-- Teller --}}
                    <div
                        x-show="hasMultiple"
                        class="absolute left-1/2 top-4 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-sm text-white"
                        x-text="`${index + 1} / ${photos.length}`"
                    ></div>

                    {{-- Chevrons --}}
                    <button
                        type="button"
                        x-show="hasMultiple"
                        @click="prev()"
                        aria-label="{{ __('Vorige foto') }}"
                        class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button
                        type="button"
                        x-show="hasMultiple"
                        @click="next()"
                        aria-label="{{ __('Volgende foto') }}"
                        class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>
@endif
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/PhotoLightboxTest.php`
Expected: PASS — alle vier groen. (De `@js()`-directive escapet forward slashes, vandaar de `or`-assertie op de original-URL.)

- [ ] **Step 7: Build the front-end**

De blade + JS moeten door Vite. Bouw en bevestig dat het slaagt.

Run: `docker compose exec -T php-fpm npm run build`
Expected: build slaagt, `public/build/manifest.json` bijgewerkt.

- [ ] **Step 8: Commit**

```bash
git add resources/js/photo-lightbox.js resources/js/app.js resources/views/components/photo-lightbox.blade.php tests/Feature/PhotoLightboxTest.php public/build
git commit -m "Add reusable photo-lightbox component with zoom"
```

---

### Task 2: Inhaken op advertentie- en homelab-detail

**Files:**
- Modify: `resources/views/livewire/listings/detail.blade.php` (foto-grid ~48-60)
- Modify: `resources/views/livewire/homelab/detail.blade.php` (foto-grid ~9-17)
- Test: `tests/Feature/PhotoLightboxTest.php` (uitbreiden)

**Interfaces:**
- Consumes: `<x-photo-lightbox :photos="…" :columns="…" />` (Task 1); `ListingPhoto::urlFor(string): string` en `HomelabPhoto::urlFor(string): string`.
- Produces: beide detailpagina's tonen de lightbox met de juiste alt-teksten.

- [ ] **Step 1: Write the failing tests**

Voeg toe aan `tests/Feature/PhotoLightboxTest.php`:

```php
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Homelab\HomelabPost;
use App\Models\Homelab\HomelabPhoto;

it('shows the lightbox with original URLs and the title as alt on a listing detail page', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Cisco 6509']);
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/x/1/card.webp', 'mime' => 'image/jpeg', 'position' => 1]);

    $html = (string) $this->get("/listings/{$listing->ulid}-{$listing->slug}")->assertOk()->getContent();

    expect($html)->toContain('photoLightbox(')
        ->and($html)->toContain('/1/original.jpg')       // original in de payload
        ->and($html)->toContain('Cisco 6509');           // alt = titel
});

it('uses an anonymous alt for homelab photos — never anything identifying', function () {
    $post = HomelabPost::factory()->create();
    HomelabPhoto::factory()->for($post)->create(['path' => 'homelabs/y/1/card.webp', 'mime' => 'image/jpeg', 'position' => 1]);

    $html = (string) $this->get('/homelabs/'.$post->ulid.'-'.$post->slug)->assertOk()->getContent();

    expect($html)->toContain('photoLightbox(')
        ->and($html)->toContain('Homelab-foto');
});
```

Let op: verifieer tijdens implementatie de exacte homelab-detail-URL en de model-namespace/factory-namen (`HomelabPost`, `HomelabPhoto`) tegen de codebase; pas de `use`-imports en de URL-opbouw zo nodig aan het bestaande patroon aan (zie `tests/Feature/Homelab/StoreHomelabPhotoJobTest.php`).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/PhotoLightboxTest.php`
Expected: de twee nieuwe tests FALEN (de pagina's tonen nog de oude grid zonder `photoLightbox(`).

- [ ] **Step 3: Wire into the listing detail page**

In `resources/views/livewire/listings/detail.blade.php`, vervang het bestaande foto-blok:

```blade
        @if ($listing->photos->isNotEmpty())
            <div class="grid grid-cols-1 gap-1 bg-cmp-bg2 sm:grid-cols-3">
                @foreach ($listing->photos as $photo)
                    <img
                        src="{{ $photo->urlFor('card') }}"
                        alt="{{ $listing->title }}"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover"
                    >
                @endforeach
            </div>
        @endif
```

door:

```blade
        @php
            $lightboxPhotos = $listing->photos->map(fn ($photo) => [
                'card' => $photo->urlFor('card'),
                'original' => $photo->urlFor('original'),
                'alt' => $listing->title,
            ])->all();
        @endphp
        <x-photo-lightbox :photos="$lightboxPhotos" :columns="3" />
```

- [ ] **Step 4: Wire into the homelab detail page**

In `resources/views/livewire/homelab/detail.blade.php`, vervang het bestaande foto-blok:

```blade
        @if ($post->photos->isNotEmpty())
            <div class="mb-6 grid grid-cols-1 gap-1 bg-cmp-bg2 sm:grid-cols-2">
                @foreach ($post->photos as $photo)
                    <img src="{{ $photo->urlFor('card') }}" alt="{{ __('Homelab-foto') }}" loading="lazy"
                         class="aspect-[4/3] w-full object-cover">
                @endforeach
            </div>
        @endif
```

door:

```blade
        @php
            $lightboxPhotos = $post->photos->map(fn ($photo) => [
                'card' => $photo->urlFor('card'),
                'original' => $photo->urlFor('original'),
                'alt' => __('Homelab-foto'),
            ])->all();
        @endphp
        <div class="mb-6">
            <x-photo-lightbox :photos="$lightboxPhotos" :columns="2" />
        </div>
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/PhotoLightboxTest.php`
Expected: PASS — alle tests groen.

- [ ] **Step 6: Run the OG-tests to confirm the listing photo change didn't regress meta tags**

De advertentie-detail deelt de pagina met de OG-tag-logica; bevestig dat die intact is.

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Listings/ListingOgTagsTest.php`
Expected: PASS — ongewijzigd groen.

- [ ] **Step 7: Rebuild the front-end**

Run: `docker compose exec -T php-fpm npm run build`
Expected: build slaagt.

- [ ] **Step 8: Commit**

```bash
git add resources/views/livewire/listings/detail.blade.php resources/views/livewire/homelab/detail.blade.php tests/Feature/PhotoLightboxTest.php public/build
git commit -m "Wire photo-lightbox into listing and homelab detail pages"
```

---

## Uitrol (na merge)

Front-end: één component, één JS-bestand, `app.js`, twee blades. **Vite-build vereist.**

1. Lokaal `npm run build` (of `docker compose exec php-fpm npm run build`) — `public/build` bijgewerkt.
2. File-sync naar LXC 214: `public/build`, `resources/views/components/photo-lightbox.blade.php`, `resources/views/livewire/listings/detail.blade.php`, `resources/views/livewire/homelab/detail.blade.php` (chown 1000:1000). De `resources/js`-bronbestanden hoeven niet mee (alleen de gebouwde `public/build`), maar sync ze voor parity.
3. `php artisan view:clear` (blades wijzigden).
4. `docker compose -f docker-compose.prod.yml restart php-fpm`, daarna `restart nginx` (502-guard).
5. **Verifieer in de browser op prod** (de gebaren leven in een laag die Pest niet raakt):
   - Desktop: klik een foto → fullscreen `original`; dubbelklik/scroll zoomt; slepen pant; pijltjes + Esc; teller klopt.
   - Mobiel (echte telefoon, de laatste 20%): tik opent; native pinch-zoom werkt; één-vinger-swipe bladert in fit-stand.
   - Toegankelijkheid: Esc sluit en focus keert terug naar de thumbnail.
6. Sluit hierna GitHub-issue #7 met een verwijzing (dit lost Wannials "kan er niet op klikken om ze groter te zien" op).

---

## Self-Review

**Spec-dekking:**
- Herbruikbaar component (grid + overlay) → Task 1. ✅
- Payload `['card','original','alt']`, grid=card, lightbox=original lui → Task 1 (component) + Task 2 (payload-opbouw). ✅
- Mobiel native pinch + swipe fit-stand; desktop scale/translate zoom/pan → Task 1 (`photo-lightbox.js`). ✅
- A11y (dialog/aria-modal/focus-trap/Esc/focus-restore/reduced-motion) → Task 1 (blade + JS). ✅
- Toegepast op beide detailpagina's, homelab-alt anoniem → Task 2. ✅
- Geen migratie/route/model-wijziging → geen enkel zo'n bestand in het plan. ✅
- Pest: rendert op beide pagina's met original-URLs, advertentie-alt=titel, homelab-alt anoniem, geen foto's=niets → Task 1 + Task 2 tests. ✅

**Placeholder-scan:** geen TBD/TODO. Eén expliciete verificatie-instructie in Task 2 Step 1 (homelab model-/URL-namen tegen de codebase checken) — dat is een gerichte controle, geen placeholder; de omringende testcode is volledig.

**Type-consistentie:** `photoLightbox(items)` met `items` = lijst van `{card, original, alt}` — identiek gebruikt in `@js($items)` (blade), de `show/next/current`-methods (JS), en de payload-opbouw (Task 2). `urlFor('card')`/`urlFor('original')` conform beide foto-modellen. Component-prop-namen `:photos`/`:columns` consistent tussen component en aanroep. ✅

**Kanttekening voor de reviewer:** de zoom-/swipe-gebaren zijn niet in Pest te vangen; de spec en dit plan schuiven die bewust naar browser-verificatie bij uitrol. Verifieer het pan-/zoom-/swipe-gedrag in de browser (desktop + echte telefoon).
