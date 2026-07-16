# Cloudmarktplaats

> Een open marktplaats voor IT-hardware, opgezet door en voor de Nederlandstalige tech-community. **Niet** alweer een SaaS met dark patterns; gewoon spullen ruilen, kopen en verkopen onder gebruikers die elkaar (digitaal) kunnen vertrouwen.

Cloudmarktplaats is open source onder de **AGPL-3.0** (zie `LICENSE`). Dat betekent: forken mag, host het zelf als je dat wilt — maar wijzigingen die je publiekelijk draait moeten ook open zijn. Geen wallgardens.

> 🇬🇧 An English summary is in [README-EN.md](README-EN.md).

---

## 1. Wat het is

Een herstart-vanaf-nul van het oude (PHP-zonder-framework) Cloudmarkplaats. **Foundation** (deze release, `v0.1.0-foundation`) levert het skelet:

- Authenticatie via e-mail/wachtwoord, GitHub/GitLab (OAuth), Ethereum-wallet (SIWE) en TOTP-2FA.
- Een advertentiewizard, foto-pipeline (EXIF wordt automatisch gestript), categorieën, full-text zoeken (Postgres tsvector), reports.
- Een Filament-3 adminpanel met audit-trail, modder- en admin-rollen.
- Juridische versionering van ToS en privacyverklaring met re-acceptatie-flow.
- Een privacy-statement dat ook handhaafbaar is in code (`IpStripperJob`, geen trackers, geen cookiebanner-theater).

Wat **nog niet** in Foundation zit, wel in toekomstige sub-projecten: messaging, reviews, sponsoring/donaties, DAC7-export, Web3-escrow, analytics. Zie ook [§ 8 Bekende gaten](#8-bekende-gaten).

---

## 2. Stack

| Laag | Keuze | Waarom |
|---|---|---|
| Runtime | **PHP 8.3** | Strict types, readonly props, intersection types — gebruikt het allemaal. |
| Framework | **Laravel 11** | Filament + Livewire ecosysteem, batterijen inbegrepen. |
| Database | **Postgres 16** | Generated `tsvector` kolom voor zoekindex, `ltree` voor categorieboom, native `jsonb`. |
| Cache & queue | **Redis 7** | View-counter SETNX, throttle-buckets, queue-backend. |
| UI | **Livewire 3 + Tailwind 3** | Component-based, geen SPA-overhead. |
| Admin | **Filament 3** | Resources voor users, listings, reports, legal docs, audit trail. |
| Storage | Lokale disk (default) / S3-compatible / IPFS pinning (later) | Achter `StorageInterface`. |
| Tests | **Pest 3** | Feature-tests draaien in Docker via Postgres + Redis. |
| Lint | **Pint** + **PHPStan level 8** | Beide blijven groen in CI. |

Mail in dev gaat via Mailpit (`docker compose`), object-storage via MinIO.

---

## 3. Quickstart

```bash
git clone https://github.com/cloudmarktplaats/cloudmarktplaats.git
cd cloudmarktplaats
cp .env.example .env
docker compose up -d
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed
```

Open `http://localhost:8080`. Demo-inloggegevens zitten in de seeders (`DemoUserSeeder`). Mailpit-UI staat op `http://localhost:8025`.

Tests:

```bash
docker compose exec php-fpm ./vendor/bin/pest
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=512M
```

Health-endpoint: `curl -fsS http://localhost:8080/healthz`.

---

## 4. DAC7 juridische positie

De EU-richtlijn DAC7 (Council Directive 2021/514) verplicht platforms om verkopers te rapporteren aan de Belastingdienst zodra zij in een kalenderjaar **30 transacties of €2.000** op het platform realiseren.

Cloudmarktplaats wijkt op dit punt af van Marktplaats/Vinted/eBay: in Foundation **vinden geen transacties op-platform plaats**. Wij faciliteren ontmoeting (advertentie + contactknop) — de afhandeling gebeurt offline of via een externe betaaldienst die wij niet beheren. De `transactions`-tabel is alvast voorbereid voor toekomstige on-platform betaling/escrow, maar zolang die functionaliteit gated is achter `web3_escrow` / `donations` (default `false`), is er niets te rapporteren.

Lees de volledige analyse in [`docs/dac7-position.md`](docs/dac7-position.md).

---

## 5. Feature flags

Features worden geactiveerd in `config/cloudmarktplaats.php` (env-overrides via `.env`):

| Flag | Standaard | Activerend sub-project |
|---|---|---|
| `anonymous_browse` | `true` | Foundation |
| `oauth_github` / `oauth_gitlab` | `true` | Foundation |
| `siwe` | `true` | Foundation |
| `two_factor` | `true` | Foundation |
| `messaging` | `false` | Messaging (#2) |
| `meilisearch` | `false` | Search-upgrade (#3) |
| `reputation` | `false` | Reviews (#4) |
| `sponsoring` / `donations` | `false` | Sponsoring (#5) |
| `dac7_reporting` | `false` | DAC7-module (#6) |
| `web3_escrow` | `false` | Web3 (#7) |
| `ipfs_pinning` | `false` | Web3 (#7) |
| `umami_analytics` | `false` | Analytics (#8) |

Zie de uitgebreidere tabel met betekenissen en sub-project-koppelingen in [`docs/feature-flags.md`](docs/feature-flags.md).

---

## 6. Privacy

Wat we **niet** doen:

- **Geen third-party trackers.** Geen Google Analytics, geen Facebook Pixel, geen Hotjar. Foundation ondersteunt later een self-hosted Umami-instantie achter `umami_analytics=true`; standaard staat hij uit.
- **Geen cookiebanner.** Omdat er geen non-essential cookies zijn (alleen session + CSRF), is een banner overbodig — en die banners zijn op zichzelf een UX-misdrijf.
- **Geen verkoop van data.** Punt.

Wat we **wel** doen:

- **IP-retentie max 24 uur.** `last_login_ip` wordt opgeslagen voor incident-response, maar `IpStripperJob` (hourly cron) wist het zodra de login ≥ 24 u oud is. Zie `app/Jobs/IpStripperJob.php`.
- **EXIF/GPS strippen.** Foto-uploads worden door `StoreListingPhotoJob` herendcodeerd zonder EXIF — telefoons lekken anders het thuisadres van de verkoper.
- **Versioned ToS/privacy.** Elke publicatie van een nieuwe versie triggert re-acceptatie via `LegalAcceptanceMiddleware`; het oude akkoord blijft als legal trail bewaard.

Gegevensverzoeken (export / wissen) gaan via GitHub Issues; een geautomatiseerd portaal komt in een latere sub-project.

---

## 7. Bijdragen

Cloudmarktplaats wordt door de community gebouwd. Volledige wegwijzer: **[CONTRIBUTING.md](CONTRIBUTING.md)** (waarden, workflow, de drie kwaliteitspoorten, je eerste PR). Kort samengevat werken we via [GitHub Issues](https://github.com/cloudmarktplaats/cloudmarktplaats/issues):

- **Bugs:** open een issue met reproductie-stappen.
- **Features:** open een issue met de waarom-vraag; we discussiëren intentie voordat er code wordt gevormd. Specs leven in `docs/superpowers/specs/`.
- **Pull requests:** geen verplichte CLA, maar je submission valt onder dezelfde AGPL-3.0 als de rest. Pint + PHPStan + Pest moeten groen blijven.

Roadmap = de [Issues-tracker](https://github.com/cloudmarktplaats/cloudmarktplaats/issues). De grote brokken (sub-projecten 2–10) staan in [`docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md`](docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md) §12.

---

## 8. Bekende gaten

We zijn eerlijk over wat (nog) niet werkt of niet af is in Foundation. Volledige lijst in [`docs/known-gaps.md`](docs/known-gaps.md). Hoofdpunten:

- **Total-lockout recovery** (geen 2FA-token + geen recovery codes + geen e-mail-toegang) is op dit moment een handmatig support-pad. Self-service-recovery komt in een toekomstig support-sub-project.
- **Foto-cleanup hook** ontbreekt: als een verkoper een advertentie verwijdert, blijven de blob-paden voorlopig staan. Cron-cleanup komt mee met sub-project #4.
- **Messaging-knop** redirect naar een notice; de daadwerkelijke berichten-functionaliteit zit in sub-project #2.
- **Wizard-resume UX** werkt (de draft wordt op elke stap bewaard), maar de surface voor "open je drafts" is nog niet aanwezig in het dashboard.

---

## Licentie

[AGPL-3.0](LICENSE). Forken/zelfhosten mag — en moedigen we aan — maar als je een gewijzigde versie publiek draait, moet je je wijzigingen ook publiek maken.
