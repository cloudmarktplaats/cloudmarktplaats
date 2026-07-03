# "Uit de homelabs" — pseudonieme showcase-feed

**Datum:** 2026-07-03
**Status:** ontwerp goedgekeurd door Nick (chat), implementatieplan volgt

## Wat en waarom

Gebruikers tonen hun homelab met één foto + korte tekst; de feed verbergt wie postte.
Rack-porn is de lingua franca van de doelgroep (r/homelab-cultuur): het maakt de homepage
levendig vóórdat er veel advertenties zijn, geeft bestaande accounts een reden om terug te
komen, en is social proof voor de marktplaats.

Besluiten uit de brainstorm:

1. **Account verplicht, anoniem getoond** (gekozen boven volledig anoniem of pre-moderatie).
2. **Post = 1 foto (verplicht) + max 500 tekens tekst.** Geen titel, geen tags.
3. **Homepage-sectie (laatste 3) + volledige feed op `/homelabs`.**
4. **Direct live + rapporteerknop** (aanname na AFK; Nick kan alsnog pre-moderatie kiezen —
   dan wordt `status` default `pending` i.p.v. `published`, verder identiek).

## Anonimiteit — het contract

- `homelab_posts.user_id` bestaat intern: voor rate-limits, eigen-post-verwijderen en misbruik.
- Publiek toont een post **uitsluitend**: foto, tekst, relatieve tijd ("3 dagen geleden").
  Geen username, geen avatar, geen link naar de poster, geen exacte timestamp in de HTML.
- In Filament ziet een admin de poster wél (accountability zonder publieke exposure).
- Geen IP-opslag bij posten; het account is de verantwoordingslijn.
- Foto's gaan door de bestaande EXIF-strip-pipeline — geen nieuwe privacy-code.

## Datamodel

Nieuwe tabel `homelab_posts`:

| kolom | type | opmerking |
|---|---|---|
| `id` | bigint pk | |
| `ulid` | ulid, unique | publieke identifier (URL's, wire:key) |
| `user_id` | fk users, cascade | nooit publiek gerenderd |
| `body` | text | max 500 tekens (validatie), plain text, nl2br+escape bij render |
| `photo_path` | string | origineel + varianten via bestaande `StorageInterface`, pad `homelabs/{ulid}/…` |
| `status` | enum `published` / `removed` | `removed` = soft-takedown door admin of poster |
| `created_at` / `updated_at` | timestamps | publiek alleen als relatieve tijd |

Model `HomelabPost` met `scopePublished()`. Fotovariants zoals bij listings (card-formaat
volstaat; geen gallery). Verwijderen = status `removed` + blob-cleanup mag in dezelfde
storage-gc-pickup die al voor listing-foto's gepland staat (docs/known-gaps.md).

## Componenten

- **`App\Livewire\HomelabFeed`** (pagina `/homelabs`): gepagineerde feed, zelfde
  infinite-scroll-patroon als `listings/browse` (IntersectionObserver + "Meer laden"-fallback).
  Bovenaan voor ingelogde users het post-formulier (foto-upload + textarea + teller);
  uitgelogd een CTA "Log in om jouw lab te tonen". Route: `GET /homelabs`, naam `homelabs`.
- **`App\Livewire\HomelabRecent`** (homepage-sectie "Uit de homelabs"): laatste 3 published
  posts, zelfde kaartstijl; sectie verbergt zichzelf volledig bij 0 posts (geen lege staat op
  home). Plaatsing: tussen "Net geplaatst" en de principes-rijen.
- **Posten**: in `HomelabFeed`; validatie (foto verplicht/type/grootte zoals listing-foto's,
  body ≤ 500), rate-limit **1 post per account per 24 uur** (RateLimiter, key op user-id),
  EXIF-strip + varianten via bestaande services.
- **Eigen post verwijderen**: knop op eigen posts in de feed (vergelijking op user_id
  server-side), zet status `removed`.
- **Rapporteren**: hergebruik van de bestaande reports-flow, uitgebreid met een polymorf
  doel óf een parallel `homelab_post_reports`-pad — implementatieplan kiest wat het kleinst
  is gegeven de huidige `reports`-tabel (listing-specifiek). Dedup-gedrag (zelfde melder,
  zelfde post, open melding) blijft gelijk.
- **Filament `HomelabPostResource`**: lijst met foto-thumb, body, poster (alleen hier
  zichtbaar!), status; acties: remove/restore, doorklik naar user (ban via bestaand
  user-beheer). Alle admin-acties in de bestaande audit-trail (`admin_actions`).

## UI (DESIGN.md-conform)

Kaart = foto (4:3) + body-tekst + sticker-chip `HOMELAB` + relatieve tijd in mono.
Feed-grid 1/2/3 kolommen. Geen likes, geen comments, geen share-knoppen. Post-formulier
in datasheet-stijl: rechthoekig, ink-borders, mono-hints, oranje focus.

## Feature flag

`FEATURE_HOMELAB_FEED` (default true in config, uitzetbaar via .env) — consistent met de
bestaande flags; homepage-sectie, route en navigatie-links respecteren de flag.

## Tests (Pest)

1. Gast kan feed en homepage-sectie zien; gast kan níet posten (redirect login).
2. Ingelogde user post met foto+tekst → published, EXIF gestript, varianten aangemaakt.
3. Validatie: geen foto → fout; body 501 tekens → fout.
4. Rate-limit: tweede post binnen 24u geweigerd, na 24u toegestaan.
5. **Anonimiteits-test (de belangrijkste):** username/display_name van de poster komt
   nérgens voor in de HTML van `/homelabs` en `/` (assertDontSee op beide).
6. Eigen post verwijderen mag; andermans post verwijderen niet (403).
7. Rapporteren werkt + dedupt; admin-remove zet status en schrijft audit-row.
8. Sectie op home verbergt zichzelf bij 0 posts; flag uit → route 404 + sectie weg.

## Expliciet buiten scope (v1)

Likes/reacties, meerdere foto's per post, bewerken van posts, volgorde anders dan
chronologisch, RSS, notificaties. Pas heroverwegen als de feed aantoonbaar leeft.
