# PWA installeerbaar + sitemap — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De site echt installeerbaar maken (manifest + app-iconen), een sitemap toevoegen, en de onware offline-claim in de FAQ terugschroeven tot wat klopt.

**Architecture:** Vier statische assets (icoon-PNG's uit het SVG-merk, gerenderd via headless Chrome) plus een statisch `site.webmanifest` in `public/`; drie regels in de marketing-`<head>`; een invokable `SitemapController` met een gecachete XML-respons; een regel in `robots.txt`; en een chirurgische tekstwijziging in de FAQ (NL-blade + EN-vertaalsleutel).

**Tech Stack:** Laravel 11, Blade, Pest, headless Chrome (`google-chrome`), Docker Compose.

**Spec:** `docs/superpowers/specs/2026-07-17-pwa-installeerbaar-sitemap-design.md`

## Global Constraints

- Alles draait in Docker; de host heeft geen PHP. Tests: `docker compose exec -T php-fpm ./vendor/bin/pest`. Kwaliteitspoorten: `docker compose exec -T php-fpm ./vendor/bin/pint --test` en `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G` (level 8; zonder die limit crasht hij).
- **De og-default-les:** elke verwijzing (`<link>`, manifest-icoon, sitemap-URL) wijst naar iets dat moet bestaan. Een verwijzing zonder bestand faalt stil — alleen een crawler of een telefoon ziet het. Tests asserten dat het doel bestaat.
- App-iconen staan op de **ink-achtergrond `#17191B`**; het merk is wit met een oranje accentstip (`#D9480F`).
- `theme_color` en `background_color` = `#F5F6F6` (de pagina-achtergrond).
- NL is de brontaal én de vertaalsleutel; `lang/en.json` blijft geldige JSON zonder dubbele sleutels.
- Headless Chrome staat op `/usr/bin/google-chrome`; render met `--headless=new --no-sandbox --force-device-scale-factor=1`.
- `public/`-rootbestanden zijn getrackt in git (net als `og-default.png`); `public/build` is gitignored (dat zijn de Vite-assets, niet deze).

---

## File Structure

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `public/apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `icon-512-maskable.png` | App-iconen op ink | 1 |
| `public/site.webmanifest` | Manifest: installeerbaar, iconen | 2 |
| `resources/views/components/layouts/marketing.blade.php` | `<head>`: icon/manifest-links + theme-color | 2 |
| `app/Http/Controllers/SitemapController.php` (nieuw) | Gecachete sitemap-XML | 3 |
| `routes/web.php` | De `/sitemap.xml`-route | 3 |
| `public/robots.txt` | `Sitemap:`-verwijzing | 3 |
| `resources/views/pages/faq.blade.php` + `lang/en.json` | FAQ zonder offline-claim | 4 |

**Volgorde:** iconen eerst (het manifest verwijst ernaar), dan manifest+head, dan sitemap+robots, dan FAQ, dan uitrol.

---

### Task 1: App-iconen renderen uit het SVG-merk

**Achtergrond:** het merk staat als SVG in `resources/views/components/marketing/logo.blade.php`. iOS vraagt nu `apple-touch-icon-*.png` op en krijgt 404's; er is geen manifest-icoon. We renderen PNG's via headless Chrome — dezelfde methode die `og-default.png` opleverde. Het merk is een wit wolkje met ink-details; op de ink-achtergrond definieert de witte vulling zich prima, de ink-details zitten óp het witte wolkje.

**Files:**
- Create: `public/apple-touch-icon.png`, `public/icon-192.png`, `public/icon-512.png`, `public/icon-512-maskable.png`
- Create (tijdelijk, niet committen): een HTML-render-bestand in de scratchpad
- Test: `tests/Feature/Pwa/AppIconsTest.php`

**Interfaces:**
- Produces: vier PNG-bestanden in `public/` met exacte afmetingen 180/192/512/512.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Pwa/AppIconsTest.php`:

```php
<?php

declare(strict_types=1);

/*
 * De iconen zijn statische assets waar de <head> en het manifest naar wijzen.
 * Een verwijzing zonder bestand faalt stil — alleen een telefoon ziet het
 * kapotte thuisscherm-icoon. Dit pint dat elk bestand bestaat én het juiste
 * formaat heeft, zodat een leeg render-resultaat of een verkeerde maat opvalt.
 */
it('ships app icons at the declared sizes', function (string $file, int $size) {
    $path = public_path($file);

    expect($path)->toBeFile();

    [$width, $height] = (array) getimagesize($path);
    expect($width)->toBe($size)->and($height)->toBe($size);
})->with([
    ['apple-touch-icon.png', 180],
    ['icon-192.png', 192],
    ['icon-512.png', 512],
    ['icon-512-maskable.png', 512],
]);
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pwa/AppIconsTest.php`
Expected: FAIL — de bestanden bestaan nog niet (`toBeFile` faalt).

- [ ] **Step 3: Genereer één render-HTML per formaat**

`--window-size` bepaalt de screenshot-afmeting, maar Chrome schaalt de body-inhoud niet mee — dus body én svg moeten per formaat de juiste maat hebben. We maken daarom vier HTML-bestanden in de scratchpad (niet committen), elk met een `body` op het doelformaat N en een `svg` op `round(0.64·N)` (gewoon) of `round(0.50·N)` (maskable, meer marge voor de Android-crop). Het merk komt uit `logo.blade.php`; de cloud-stroke is weggelaten (onzichtbaar op ink), de details zitten óp het witte wolkje.

Draai dit script — het genereert alle vier de HTML-bestanden met de juiste maten en het gedeelde merk:

```bash
D=/tmp/claude-1000/-mnt-nvme1tb-projects-cloudmarktplaats/63f4a6d0-8d09-4fe6-9ac5-cf89cb6e13d5/scratchpad
MARK='<svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 28C6.7 28 4 25.3 4 22C4 19.1 6 16.7 8.7 16.1C8.3 15.1 8 14 8 13C8 9.1 11.1 6 15 6C16.8 6 18.4 6.7 19.6 7.8C21 6.7 22.8 6 24.8 6C29.2 6 32.8 9.2 33.4 13.4C33.6 13.3 33.8 13.3 34 13.3C37.3 13.3 40 16 40 19.3C40 22.3 37.8 24.8 34.9 25.3L34.9 28Z" fill="#FFFFFF"/><path d="M14 28L14 22L20 22" stroke="#17191B" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/><path d="M20 22L20 18L26 18" stroke="#17191B" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/><path d="M30 28L30 24L26 24L26 18" stroke="#17191B" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/><circle cx="14" cy="28" r="2.5" fill="#17191B"/><circle cx="20" cy="22" r="2" fill="#17191B" opacity=".8"/><circle cx="26" cy="18" r="2" fill="#D9480F"/><circle cx="30" cy="28" r="2.5" fill="#17191B"/></svg>'
emit () { # $1=bestand $2=N $3=svgpx
  printf '<!DOCTYPE html><html><head><meta charset="utf-8"><style>*{margin:0;padding:0}body{width:%spx;height:%spx;background:#17191B;display:flex;align-items:center;justify-content:center}svg{width:%spx;height:%spx}</style></head><body>%s</body></html>' "$2" "$2" "$3" "$3" "$MARK" > "$1"
}
emit "$D/icon-512.html"          512 328   # 0.64·512
emit "$D/icon-192.html"          192 123   # 0.64·192
emit "$D/apple-touch-icon.html"  180 115   # 0.64·180
emit "$D/icon-512-maskable.html" 512 256   # 0.50·512 — extra marge
echo "vier HTML-bestanden gegenereerd"
```

- [ ] **Step 4: Render de PNG's met headless Chrome**

```bash
D=/tmp/claude-1000/-mnt-nvme1tb-projects-cloudmarktplaats/63f4a6d0-8d09-4fe6-9ac5-cf89cb6e13d5/scratchpad
cd /mnt/nvme1tb/projects/cloudmarktplaats
render () { # $1=basename $2=N
  google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars --force-device-scale-factor=1 \
    --window-size="$2,$2" --screenshot="$D/$1.png" "file://$D/$1.html"
}
render icon-512 512
render icon-192 192
render apple-touch-icon 180
render icon-512-maskable 512
cp "$D/icon-512.png" "$D/icon-192.png" "$D/apple-touch-icon.png" "$D/icon-512-maskable.png" public/
echo "gerenderd + gekopieerd naar public/"
```

Verwacht: `file public/icon-512.png` meldt `PNG image data, 512 x 512`, en de andere de bijbehorende maten (192, 180, 512).

- [ ] **Step 5: Draai de test en zie hem slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pwa/AppIconsTest.php`
Expected: alle vier de dataset-rijen PASS (bestand bestaat + exacte afmeting).

- [ ] **Step 6: Controleer met eigen ogen dat het icoon leesbaar is**

Open `public/icon-512.png` en `public/icon-512-maskable.png`. Verwacht: een wit circuit-cloud-merk met één oranje stip op een donkere (`#17191B`) achtergrond; het maskable-icoon heeft zichtbaar meer marge rondom. Is het wolkje een vormeloze witte vlek of valt de oranje stip weg, dan klopt het render-HTML niet — meld dat.

- [ ] **Step 7: Commit**

```bash
git add public/apple-touch-icon.png public/icon-192.png public/icon-512.png public/icon-512-maskable.png tests/Feature/Pwa/AppIconsTest.php
git commit -m "feat(pwa): app-iconen op ink-achtergrond, uit het SVG-merk

Vier PNG's (180/192/512/512-maskable) gerenderd uit het circuit-cloud-merk
op #17191B. Fixt de apple-touch-icon-404's en levert de manifest-iconen.
Test pint bestaan + afmeting — een verwijzing zonder bestand faalt anders
stil (de og-default.png-les)."
```

---

### Task 2: Manifest + head-links + theme-color

**Files:**
- Create: `public/site.webmanifest`
- Modify: `resources/views/components/layouts/marketing.blade.php` (rond regel 79, na de inline SVG-favicon)
- Test: `tests/Feature/Pwa/ManifestTest.php`

**Interfaces:**
- Consumes: de vier icoon-bestanden uit Taak 1.
- Produces: `public/site.webmanifest` (geldige JSON), en de `<head>` linkt ernaar + naar `apple-touch-icon.png`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Pwa/ManifestTest.php`:

```php
<?php

declare(strict_types=1);

it('ships a valid webmanifest whose icons all exist', function () {
    $path = public_path('site.webmanifest');
    expect($path)->toBeFile();

    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Cloudmarktplaats')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['theme_color'])->toBe('#F5F6F6');

    // Elk icoon dat het manifest belooft, moet als bestand bestaan.
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('links the manifest and apple-touch-icon from the marketing layout head', function () {
    $html = (string) $this->get('/')->getContent();

    expect($html)->toContain('rel="manifest"')
        ->and($html)->toContain('site.webmanifest')
        ->and($html)->toContain('rel="apple-touch-icon"')
        ->and($html)->toContain('apple-touch-icon.png')
        ->and($html)->toContain('name="theme-color"');
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pwa/ManifestTest.php`
Expected: FAIL — het manifest bestaat niet en de head linkt er niet naar.

- [ ] **Step 3: Maak het manifest**

Maak `public/site.webmanifest`:

```json
{
    "name": "Cloudmarktplaats",
    "short_name": "Cloudmarktplaats",
    "description": "Open source marktplaats voor IT-hardware. Geen trackers, geen algoritme.",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#F5F6F6",
    "theme_color": "#F5F6F6",
    "icons": [
        { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png" },
        { "src": "/icon-512-maskable.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
    ]
}
```

- [ ] **Step 4: Voeg de head-links toe**

In `resources/views/components/layouts/marketing.blade.php`, direct ná de bestaande inline SVG-favicon-`<link>` (rond regel 79), toevoegen:

```blade
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#F5F6F6">
```

- [ ] **Step 5: Draai en zie slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pwa/ManifestTest.php`
Expected: beide tests PASS.

- [ ] **Step 6: Commit**

```bash
git add public/site.webmanifest resources/views/components/layouts/marketing.blade.php tests/Feature/Pwa/ManifestTest.php
git commit -m "feat(pwa): webmanifest + head-links maken de site installeerbaar

'Toevoegen aan beginscherm' werkt nu echt: manifest met de ink-iconen,
apple-touch-icon-link (stopt het 404-gokken van iOS), theme-color. De test
pint dat elk manifest-icoon als bestand bestaat."
```

---

### Task 3: Sitemap + robots.txt

**Achtergrond:** er is geen sitemap. Een dynamische, gecachete route somt de statische pagina's + gepubliceerde advertenties + gepubliceerde homelabs op, zodat nieuwe content vanzelf verschijnt. `robots.txt` verwijst ernaar.

**Files:**
- Create: `app/Http/Controllers/SitemapController.php`
- Modify: `routes/web.php`
- Modify: `public/robots.txt`
- Test: `tests/Feature/SitemapTest.php`

**Interfaces:**
- Consumes: `Listing` (kolom `state`, `ulid`, `slug`, scope niet nodig — filter op `where('state','published')`), `HomelabPost::published()` (bestaande scope; kolommen `ulid`, `slug`).
- Produces: route `GET /sitemap.xml` (naam `sitemap`) die `application/xml` teruggeeft.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/SitemapTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('returns valid xml with the right content type', function () {
    $res = $this->get('/sitemap.xml');

    $res->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    // Parse-baar als XML — een kapotte sitemap is erger dan geen.
    $xml = simplexml_load_string($res->getContent());
    expect($xml)->not->toBeFalse();
});

it('includes published listings and homelabs, excludes drafts and removed', function () {
    $published = Listing::factory()->create(['state' => 'published']);
    $draft = Listing::factory()->create(['state' => 'draft']);
    $lab = HomelabPost::factory()->create(['status' => 'published']);
    $removedLab = HomelabPost::factory()->create(['status' => 'removed']);

    $body = (string) $this->get('/sitemap.xml')->getContent();

    expect($body)->toContain("/listings/{$published->ulid}-{$published->slug}")
        ->and($body)->not->toContain($draft->ulid)
        ->and($body)->toContain("/homelabs/{$lab->ulid}-{$lab->slug}")
        ->and($body)->not->toContain($removedLab->ulid);
});

it('lists the key static pages', function () {
    $body = (string) $this->get('/sitemap.xml')->getContent();

    expect($body)->toContain('/waarden')
        ->and($body)->toContain('/faq')
        ->and($body)->toContain('/homelabs');
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/SitemapTest.php`
Expected: FAIL — de route bestaat niet (404).

- [ ] **Step 3: Maak de controller**

Maak `app/Http/Controllers/SitemapController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HomelabPost;
use App\Models\Listing;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamische sitemap: statische pagina's + gepubliceerde advertenties en
 * homelabs. Eén uur gecachet zodat crawlers de database niet bij elke hit
 * raken. Concepten, verwijderde en afgewezen items horen er niet in — die
 * zijn niet publiek.
 */
class SitemapController extends Controller
{
    /** Statische publieke pagina's die we geïndexeerd willen hebben. */
    private const STATIC_PATHS = [
        '/', '/listings', '/homelabs', '/over-ons', '/waarden',
        '/faq', '/sponsors', '/roadmap', '/doneren', '/register',
    ];

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function build(): string
    {
        $urls = [];

        foreach (self::STATIC_PATHS as $path) {
            $urls[] = ['loc' => url($path), 'lastmod' => null];
        }

        Listing::query()->where('state', 'published')->get(['ulid', 'slug', 'updated_at'])
            ->each(function (Listing $l) use (&$urls): void {
                $urls[] = [
                    'loc' => url("/listings/{$l->ulid}-{$l->slug}"),
                    'lastmod' => $l->updated_at?->toAtomString(),
                ];
            });

        HomelabPost::query()->published()->get(['ulid', 'title', 'body', 'updated_at'])
            ->each(function (HomelabPost $p) use (&$urls): void {
                $urls[] = [
                    'loc' => url("/homelabs/{$p->ulid}-{$p->slug}"),
                    'lastmod' => $p->updated_at?->toAtomString(),
                ];
            });

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $u) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>';
            if ($u['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$u['lastmod'].'</lastmod>';
            }
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
```

Let op: `HomelabPost::$slug` is een accessor (`getSlugAttribute`) die op `title`/`body` leunt — daarom laden we `title` en `body` mee in de `get([...])`. `Listing::$slug` is een echte kolom.

- [ ] **Step 4: Registreer de route**

In `routes/web.php`, bij de andere publieke GET-routes (bijv. naast `/healthz` of de `Route::view`-pagina's):

```php
Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');
```

- [ ] **Step 5: Voeg de Sitemap-verwijzing toe aan robots.txt**

Vervang de inhoud van `public/robots.txt` (nu `User-agent: *` + `Disallow:`) door:

```
Sitemap: https://cloudmarktplaats.nl/sitemap.xml

User-agent: *
Disallow:
```

De absolute URL is verplicht in robots.txt — een relatief pad is ongeldig.

- [ ] **Step 6: Draai en zie slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/SitemapTest.php`
Expected: alle drie de tests PASS.

- [ ] **Step 7: Bevestig de robots-verwijzing**

Run: `docker compose exec -T php-fpm php -r 'echo str_contains(file_get_contents("public/robots.txt"), "Sitemap: https://cloudmarktplaats.nl/sitemap.xml") ? "ok\n" : "MIST\n";'`
Expected: `ok`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SitemapController.php routes/web.php public/robots.txt tests/Feature/SitemapTest.php
git commit -m "feat: dynamische gecachete sitemap + robots-verwijzing

GET /sitemap.xml somt de statische pagina's + gepubliceerde advertenties
en homelabs op, 1u gecachet. Concepten/verwijderde items eruit. robots.txt
wijst ernaar. Nieuwe content verschijnt vanzelf; geen onderhoud."
```

---

### Task 4: De FAQ eerlijk maken

**Achtergrond:** de FAQ claimt dat de app offline draait. Dat is onwaar (geen service worker). We halen uitsluitend die claim eruit; de rest van het antwoord blijft.

**Files:**
- Modify: `resources/views/pages/faq.blade.php` (regel 47)
- Modify: `lang/en.json` (de bijbehorende sleutel)
- Test: `tests/Feature/Pages/FaqPwaClaimTest.php`

**Interfaces:** geen.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Pages/FaqPwaClaimTest.php`:

```php
<?php

declare(strict_types=1);

/*
 * De FAQ claimde dat de app offline draait — onwaar, er is geen service
 * worker. Bij een merk dat "we bouwen wat we claimen" hoog houdt, is dat een
 * eerlijkheidsgat. De claim is nu "installeerbaar", niet "offline".
 */
it('claims installable, not offline, for the PWA', function () {
    $html = (string) $this->get('/faq')->getContent();

    expect($html)->toContain('aan je beginscherm')
        ->and($html)->not->toContain('draait offline');
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/FaqPwaClaimTest.php`
Expected: FAIL — de tekst bevat nog "draait offline".

- [ ] **Step 3: Wijzig de NL-blade**

In `resources/views/pages/faq.blade.php` regel 47, vervang exact het zinsdeel. De hele alinea is één `__()`-sleutel; wijzig alleen deze woorden:

- Van: `je kan hem aan je beginscherm vastpinnen en hij draait offline waar het kan.`
- Naar: `je kan hem aan je beginscherm vastpinnen; hij opent dan als een zelfstandige app.`

De rest van de alinea (de drie redenen voor geen native app) blijft ongewijzigd.

- [ ] **Step 4: Vervang de EN-vertaalsleutel**

Omdat de Nederlandse zin de sleutel ís, is de oude sleutel nu ongeldig. In `lang/en.json`: verwijder de oude sleutel (de lange alinea die begint met `<p>Cloudmarktplaats werkt als progressive web app: ... hij draait offline waar het kan. ...`) en voeg de nieuwe sleutel toe met de gewijzigde NL-zin als key en deze EN-waarde:

De nieuwe NL-sleutel (key) is de volledige alinea met het gewijzigde zinsdeel. De EN-waarde is dezelfde alinea als nu, met dit ene zinsdeel vervangen:
- Van (EN): `you can pin it to your home screen, and it runs offline where it can.`
- Naar (EN): `you can pin it to your home screen; it then opens as a standalone app.`

Praktisch: kopieer de bestaande NL-key en EN-value uit `lang/en.json`, pas in beide alleen dat ene zinsdeel aan, verwijder het oude paar, voeg het nieuwe toe. Het bestand moet geldige JSON blijven.

- [ ] **Step 5: Draai en zie slagen + controleer EN**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/FaqPwaClaimTest.php
docker compose exec -T php-fpm php -r 'json_decode(file_get_contents("lang/en.json"), true, 512, JSON_THROW_ON_ERROR); echo "geldige JSON\n";'
```
Expected: test PASS; `geldige JSON`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/pages/faq.blade.php lang/en.json tests/Feature/Pages/FaqPwaClaimTest.php
git commit -m "docs: FAQ claimt installeerbaar i.p.v. offline (klopt met de werkelijkheid)

Er is geen service worker, dus 'draait offline' was onwaar. Nu:
'installeerbaar — opent als een zelfstandige app'. De rest van het antwoord
(geen native app, en waarom) blijft."
```

---

### Task 5: Kwaliteitspoorten en uitrol

**Achtergrond:** deployen is een file-sync naar LXC 214, géén git pull. Nieuwe route (`/sitemap.xml`) → route-cache verversen (de valkuil van de homelab-uitrol: prod cachet routes in `bootstrap/cache/routes-v7.php`; `config:cache` alleen is niet genoeg). Geen migratie, geen config-key. Nieuwe statische assets in `public/` root (iconen, manifest, robots) moeten mee in de sync.

**Files:** geen; dit is de uitrol van Taak 1-4.

- [ ] **Step 1: Volle suite en poorten**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G
```
Expected: alles groen.

- [ ] **Step 2: Sync naar productie**

```bash
cd /mnt/nvme1tb/projects/cloudmarktplaats
tar czf - \
  public/apple-touch-icon.png public/icon-192.png public/icon-512.png public/icon-512-maskable.png \
  public/site.webmanifest public/robots.txt \
  app/Http/Controllers/SitemapController.php \
  routes/web.php \
  resources/views/components/layouts/marketing.blade.php \
  resources/views/pages/faq.blade.php \
  lang/en.json \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && tar xzf - && chown 1000:1000 public/apple-touch-icon.png public/icon-192.png public/icon-512.png public/icon-512-maskable.png public/site.webmanifest public/robots.txt app/Http/Controllers/SitemapController.php routes/web.php resources/views/components/layouts/marketing.blade.php resources/views/pages/faq.blade.php lang/en.json && echo synced'"
```
Expected: `synced`.

- [ ] **Step 3: Route-cache verversen + view:clear + herstart**

De nieuwe `/sitemap.xml`-route zit niet in de gecachete route-file tot je die herbouwt — anders 404't hij (exact de valkuil van de homelab-uitrol). Artisan als `www-data`.

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan route:clear && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan route:cache && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart php-fpm'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart nginx'"
```

nginx ná php-fpm, anders 502't de site.

- [ ] **Step 4: Verifieer publiek**

```bash
sleep 3
echo -n "  manifest: "; curl -s -o /dev/null -w "%{http_code} %{content_type}\n" https://cloudmarktplaats.nl/site.webmanifest
echo -n "  apple-touch-icon: "; curl -s -o /dev/null -w "%{http_code} (%{size_download} bytes)\n" https://cloudmarktplaats.nl/apple-touch-icon.png
echo -n "  sitemap: "; curl -s -o /dev/null -w "%{http_code} %{content_type}\n" https://cloudmarktplaats.nl/sitemap.xml
echo "  sitemap bevat een advertentie?"; curl -s https://cloudmarktplaats.nl/sitemap.xml | grep -oE "/listings/[0-9A-HJKMNP-TV-Z]{26}" | head -1
echo "  robots verwijst?"; curl -s https://cloudmarktplaats.nl/robots.txt | grep -i sitemap
echo "  FAQ zonder offline-claim?"; curl -s https://cloudmarktplaats.nl/faq | grep -c "draait offline"
echo -n "  healthz: "; curl -s -o /dev/null -w "%{http_code}\n" https://cloudmarktplaats.nl/healthz
```
Expected: manifest 200 (`application/json` of `application/manifest+json`), apple-touch-icon 200 met bytes, sitemap 200 `application/xml`, een advertentie-URL verschijnt, robots toont de Sitemap-regel, `draait offline` telt 0, healthz 200.

- [ ] **Step 5: Bevestig op een echt toestel**

Alleen een telefoon toont de laatste 20%. Open op een iPhone en/of Android `cloudmarktplaats.nl`, kies "toevoegen aan beginscherm", en controleer: het icoon is het ink-merk (geen kaal/gebroken icoon), de naam is "Cloudmarktplaats", en openen vanaf het beginscherm opent zonder browser-chrome (standalone). Meld wat je zag.

---

## Rollback

- **Route/sitemap/head/manifest:** `git revert` van de betreffende commits en opnieuw syncen, gevolgd door `route:clear && route:cache` en `view:clear`. Geen migratie, geen data.
- **Iconen/manifest/robots:** statische bestanden; de oude versies komen terug met de revert. De FAQ-tekst idem.
