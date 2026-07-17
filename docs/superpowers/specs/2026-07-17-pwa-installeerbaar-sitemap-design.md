# Installeerbaar maken + vindbaar: PWA-manifest, iconen, sitemap

**Datum:** 2026-07-17
**Status:** ter review

## Waarom

De site claimt iets dat er niet is. De FAQ zegt letterlijk:

> *"Cloudmarktplaats werkt als progressive web app: je kan hem aan je beginscherm vastpinnen en hij draait offline waar het kan."*

Er is geen manifest, geen service worker, en `apple-touch-icon-*.png` geeft 12+ keer een 404 in de productie-log — een iOS-gebruiker die "toevoegen aan beginscherm" doet, krijgt een kapot icoon. De offline-belofte is simpelweg onwaar. Dat botst met waar dit platform op staat: *"we schreeuwen niet over dingen, we bouwen het."* Hier roepen we iets dat we niet gebouwd hebben.

Deze wijziging maakt de belofte waar (installeerbaar, mét een echt icoon) en schroeft de claim terug tot wat klopt (geen offline). En passant lossen we de vindbaarheid op het minimale niveau op: een sitemap zodat de advertenties en homelabs die er wél zijn geïndexeerd raken.

Eerlijk over de inzet: het knelpunt van de site is **conversie** (108 leden, 10 advertenties, 2 homelabs), niet vindbaarheid. Daarom bewust géén grote SEO-slag, geen structured-data-overhaul, geen service worker. Dit is de goedkope, eerlijke basis — niet meer.

## Beslissingen

- **Geen offline, geen service worker.** Een service worker + caching is fors werk voor een marktplaats waar de content juist live moet zijn, en het past bij het merk om niet te claimen wat je niet bouwt. De FAQ-claim wordt eerlijk: *installeerbaar*, niet *offline*.
- **Sitemap dynamisch, gecachet.** Een route die de gepubliceerde content opsomt, 1 uur gecachet. Nieuwe advertenties verschijnen vanzelf; geen onderhoud, geen verouderd statisch bestand.
- **App-iconen op de ink-achtergrond (`#17191B`).** Het witte circuit-cloud-merk met de oranje stip springt eruit op donker; op wit zou het wolkje wegvallen.

## Onderdelen

### 1. App-iconen (PNG, uit het bestaande SVG-merk)

Het merk staat als SVG in `resources/views/components/marketing/logo.blade.php`. We renderen het tot PNG's via headless Chrome — dezelfde methode als `og-default.png` (bewezen dit project). Het merk wordt gecentreerd op een effen `#17191B`-vlak.

Vier bestanden in `public/` (root is getrackt in git, net als `og-default.png`):

- `apple-touch-icon.png` — 180×180. iOS' standaardformaat; door 'm expliciet in de `<head>` te declareren stopt iOS met het gokken naar `-120x120`/`-240x240`-bestanden (dat is de bron van de 404's).
- `icon-192.png` — 192×192. Android/manifest.
- `icon-512.png` — 512×512. Manifest, splash.
- `icon-512-maskable.png` — 512×512 met ~10% veilige marge rondom het merk, zodat Android's maskable-crop het niet afsnijdt.

### 2. De `<head>` van de marketing-layout

In `resources/views/components/layouts/marketing.blade.php`, naast de bestaande inline SVG-favicon (die blijft):

```blade
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#F5F6F6">
```

`theme-color` `#F5F6F6` (de pagina-achtergrond) laat de mobiele browser-chrome overlopen in de pagina — passend bij de lichte datasheet-stijl.

### 3. Het manifest (`public/site.webmanifest`, statisch)

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

Statisch bestand: het manifest verandert nooit op basis van data, dus een route zou verspilling zijn.

### 4. De sitemap (`GET /sitemap.xml`, dynamisch + gecachet)

Een route die valide sitemap-XML teruggeeft, 1 uur gecachet (`Cache::remember('sitemap.xml', 3600, ...)`) zodat crawlers de database niet bij elke hit raken.

Bevat:

- **Statische publieke pagina's:** `/`, `/listings`, `/homelabs`, `/over-ons`, `/waarden`, `/faq`, `/sponsors`, `/roadmap`, `/doneren`, `/register`.
- **Elke gepubliceerde advertentie:** `/listings/{ulid}-{slug}` voor `Listing::where('state', 'published')`.
- **Elke gepubliceerde homelab:** `/homelabs/{ulid}-{slug}` voor `HomelabPost::published()`.

`/register` staat wél in de statische lijst — het is een conversiepagina die je gevonden wilt hebben. Niet in de sitemap: concepten, verwijderde/afgewezen items, de overige auth-pagina's (`/login`, `/forgot-password`, `/legal/accept`), utility-endpoints (`/healthz`, `/up`, `/auth/web3/nonce`), en gebruiker-specifieke of niet-canonieke pagina's (`/mijn-advertenties`, `/search`).

`public/robots.txt` krijgt een verwijzing bovenaan:

```
Sitemap: https://cloudmarktplaats.nl/sitemap.xml

User-agent: *
Disallow:
```

De absolute URL is nodig — een relatief pad is ongeldig in robots.txt.

### 5. De FAQ eerlijk maken

In `resources/views/pages/faq.blade.php` (regel 47) en de bijbehorende `lang/en.json`-sleutel: verwijder uitsluitend de offline-claim. *"...je kan hem aan je beginscherm vastpinnen en hij draait offline waar het kan."* wordt *"...je kan hem aan je beginscherm vastpinnen; hij opent dan als een zelfstandige app."* De drie redenen voor geen native app blijven ongewijzigd.

Omdat de hele alinea één vertaalsleutel is (NL is de sleutel), wijzigt de sleutel — dus de EN-vertaling verandert mee.

## Wat er niet in zit

- **Service worker / offline.** Zie beslissing 1.
- **Structured data / Product-schema op advertenties.** Loont pas bij volume; nu voorbarig. Er staat al JSON-LD-ondersteuning in de layout (`$jsonLd`) en op de FAQ — dat blijft, er komt niets bij.
- **Een grotere SEO-slag.** Idem.
- **Navigatie/responsiveness.** Geen bewijs van een probleem; buiten scope.

## Risico's

**Het manifest/de iconen kloppen pas als je ze test op een echt toestel.** Lighthouse en een `manifest`-linter vangen de structuur, maar "toevoegen aan beginscherm" met het juiste icoon is alleen op een echte telefoon 100% te bevestigen. De uitrol-check hoort dat te benoemen.

**De sitemap kan zware content-queries doen als hij niet gecachet is.** Daarom de 1-uur-cache. Bij duizenden advertenties later wil je 'm mogelijk pagineren (sitemap-index); nu, met tientallen, is één bestand ruim voldoende.

**Een verwijzing zonder bestand faalt stil** — precies de og-default-les. Elke `<link>` en elke manifest-icon-verwijzing wijst naar een bestand dat moet bestaan; de tests asserten dat.

## Testbaarheid

- `public/site.webmanifest` is geldige JSON, en de marketing-layout linkt ernaar. Elk icoon dat het manifest noemt bestaat als bestand.
- De `apple-touch-icon`-link in de `<head>` wijst naar een bestaand `public/apple-touch-icon.png`.
- `GET /sitemap.xml` geeft 200, `Content-Type: application/xml`, en valide XML.
- De sitemap bevat de URL van een gepubliceerde advertentie en een gepubliceerde homelab, en bevat **niet** die van een concept of een verwijderde/afgewezen post.
- `public/robots.txt` bevat de absolute `Sitemap:`-regel.
- De FAQ claimt geen offline meer: de pagina bevat "installeerbaar"/"opent als een app" en niet "draait offline".
- De iconen zijn PNG's van de juiste afmetingen (180/192/512), zodat een hernoeming of een leeg render-resultaat opvalt.
