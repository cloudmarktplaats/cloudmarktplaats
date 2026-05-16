# Cloudmarktplaats (English summary)

> An open marketplace for IT hardware, built by and for the Dutch-speaking tech community. Not yet-another-SaaS with dark patterns; just stuff being traded, bought and sold between people who can (digitally) verify each other.

Cloudmarktplaats is licensed **AGPL-3.0**. Forking and self-hosting are encouraged; publicly running a modified version means publishing your changes.

This is the English-language quick-tour. The canonical README is Dutch — see [README.md](README.md).

---

## What's in Foundation (`v0.1.0-foundation`)

- **Authentication:** email + password, GitHub/GitLab OAuth, Sign-In-With-Ethereum (SIWE), TOTP-based 2FA.
- **Listings:** 3-step Livewire wizard, photo pipeline that strips EXIF/GPS, Postgres `tsvector` full-text search, ltree-based category tree, reports with rate-limiting + dedup.
- **Admin:** Filament 3 panel with 6 resources (Users, Listings, Categories, Reports, Legal Documents, AdminActions) and 5 dashboard widgets; every privileged action is logged in `admin_actions`.
- **Legal versioning:** ToS / privacy revisions stored immutably; `LegalAcceptanceMiddleware` re-prompts users on legally-consequential routes when a new version has been published since their last acceptance.
- **Privacy:** no trackers, no cookie banner (there are no non-essential cookies to consent to), `last_login_ip` automatically stripped after 24 h by `IpStripperJob`.

Not in Foundation yet (sequenced sub-projects): messaging, reviews/reputation, sponsoring/donations, DAC7 reporting export, Web3 escrow, self-hosted analytics. See [§ 12 of the spec](docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md#12-sub-project-roadmap-post-foundation).

---

## Stack

PHP 8.3 · Laravel 11 · Postgres 16 · Redis 7 · Livewire 3 · Filament 3 · Tailwind 3 · Pest 3 · Pint · PHPStan level 8.

---

## Quickstart

```bash
git clone https://github.com/cloudmarktplaats/cloudmarktplaats.git
cd cloudmarktplaats
cp .env.example .env
docker compose up -d
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed
```

Open `http://localhost:8080`. Mailpit (dev mail UI): `http://localhost:8025`.

```bash
docker compose exec php-fpm ./vendor/bin/pest
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=512M
curl -fsS http://localhost:8080/healthz
```

---

## Why this exists / what's different

- **AGPL-3.0**, not MIT. We do not want this codebase ending up as the backbone of a closed-source competitor.
- **No DAC7 reporting in Foundation by design.** No on-platform transactions happen yet, so there's nothing to report to the Dutch tax authority. See [`docs/dac7-position.md`](docs/dac7-position.md) for the full reasoning.
- **No dark patterns:** no "you've been outbid!" emails (we're not an auction), no urgency timers, no growth-team-approved engagement loops. The product surface is intentionally boring; that's the point.
- **By the community, for the community.** Roadmap = GitHub Issues. Specs in `docs/superpowers/`.

---

## Documentation

- Dutch README (primary): [README.md](README.md)
- DAC7 position: [docs/dac7-position.md](docs/dac7-position.md)
- Feature flags: [docs/feature-flags.md](docs/feature-flags.md)
- Known gaps: [docs/known-gaps.md](docs/known-gaps.md)
- Architecture spec: [docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md](docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md)
- Implementation plan: [docs/superpowers/plans/2026-05-16-cloudmarktplaats-v2-foundation.md](docs/superpowers/plans/2026-05-16-cloudmarktplaats-v2-foundation.md)
