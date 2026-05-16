# Cloudmarktplaats v2 — Foundation MVP — Design

**Status:** Approved by Nick, awaiting implementation plan
**Date:** 2026-05-16
**Sub-project:** 1 of 10 (Foundation)
**Project location:** `/mnt/nvme1tb/projects/cloudmarktplaats` (new directory, `.nl` TLD spelling with `t`)
**Predecessor:** v1 PHP-MVC at `/mnt/nvme1tb/projects/cloudmarkplaats` (untouched, archived in place)

---

## 1. Scope & Intent

Cloudmarktplaats v2 is a freedom-oriented, privacy-first C2C marketplace for hardware, electronics, IT equipment, maker supplies, networking, servers, dev-boards, and adjacent tech. Target audience: tinkerers, makers, IT professionals, engineers, hobbyists, hackers, sustainability-minded corporates, internet entrepreneurs.

Owner is a social tech entrepreneur. All profit returns to the community. Governance is community-driven. **No VC, no data monetisation, no tracking.**

This document specifies **Sub-project 1: Foundation MVP** — a publishable skeleton with authentication, listings, and admin panel. Subsequent sub-projects (messaging, search, reputation, sponsoring, DAC7, Web3 escrow, analytics, roadmap UI, real-time) each get their own spec.

### Out-of-scope for Foundation (deferred to later sub-projects)

Meilisearch (Postgres FTS used as interim), messaging, reputation/reviews, sponsorship + donations (Stripe), DAC7 module, Web3 escrow (on-chain), Umami analytics, IPFS pinning, public roadmap UI, real-time messaging.

### Hard policies from day 1 (non-negotiable)

- No cookie banner — only functional session cookie
- No Google/Facebook OAuth providers (excluded by code, not config)
- No external trackers/pixels
- IP-stripping in logs after 24h via `IpStripperJob` scheduled hourly (clears `users.last_login_ip` and rotates Laravel log files older than 24h through a sed pass)
- Open-stats schema hook (public dashboard added in analytics sub-project)
- AGPL-3.0 licence

---

## 2. Architecture & Tech Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.3+ |
| Framework | Laravel 11 |
| Database | PostgreSQL 16 (with `ltree` extension) |
| Cache/Queue/Session | Redis 7 |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS 3 |
| Admin | Filament 3 |
| Image processing | Intervention/Image |
| Auth (OAuth) | Laravel Socialite |
| Auth (TOTP) | pragmarx/google2fa-laravel |
| Auth (Web3) | Custom EIP-4361 SIWE (kornrunner/keccak + simplito/elliptic-php) |
| Search (interim) | Postgres FTS (tsvector + GIN index) |
| Testing | Pest 3 (unit + feature + browser) |
| Static analysis | PHPStan level 8 + Laravel Pint |
| Containerisation | Custom `docker-compose.yml` (same shape dev/prod) |
| Licence | AGPL-3.0 |

### Container topology

`docker-compose.yml` services: `nginx`, `php-fpm`, `postgres`, `redis`, `mailpit` (dev), `minio` (dev, S3-compatible local).
Production overlay (`docker-compose.prod.yml`): swaps mailpit→SMTP env, minio→S3 endpoint env, adds healthchecks.

### Configuration

Single Laravel config file `config/cloudmarktplaats.php` centralises feature flags + thresholds (see §10). All credentials via `.env`. `.env.example` documented.

---

## 3. Domain Model

### `users`

- `id` (bigint PK), `ulid` (public-facing identifier, unique)
- `email` (unique, **required for all users** — used for legal notifications and recovery; SIWE-only users provide it at onboarding)
- `password_hash` (nullable; SIWE-only and OAuth-only users may have no password)
- `username` (unique, slug-safe, 3–30 chars), `display_name`
- `email_verified_at` (nullable timestamp)
- `last_login_at`, `last_login_ip` (nullable, scrubbed by `IpStripperJob` after 24h)
- `role` enum (`user`, `moderator`, `admin`)
- `is_banned` boolean, `banned_reason` text (nullable)
- `two_factor_secret` (encrypted, nullable)
- `two_factor_recovery_codes` (encrypted JSON array of 8 single-use codes)
- `two_factor_confirmed_at` (nullable)
- `created_at`, `updated_at`, `deleted_at` (soft delete)

### `user_identities`

Allows multiple login methods per user.

- `id`, `user_id` FK
- `provider` enum (`password`, `oauth_github`, `oauth_gitlab`, `siwe`)
- `provider_uid` (string: github user_id, gitlab user_id, lowercased ethereum address, or fixed `password` token)
- `provider_data` jsonb (provider metadata; never store access tokens beyond their natural lifetime)
- `created_at`, `last_used_at`
- Unique constraint `(provider, provider_uid)`

### `auth_nonces`

SIWE nonce cache, 5-minute TTL.

- `id`, `nonce` (32 random bytes hex), `address`, `expires_at`, `used_at` (nullable)

### `legal_documents`

Versioned Terms of Service / Privacy Policy.

- `id`, `type` enum (`tos`, `privacy`)
- `version` (semver string)
- `locale` (`nl`, `en`)
- `markdown_content` text
- `published_at` (nullable; null = draft)
- Index `(type, locale, published_at desc)`

### `legal_acceptances`

- `id`, `user_id`, `legal_document_id`, `accepted_at`, `ip_hash` (sha256 of IP+pepper; no plain IP)

### `categories`

Hierarchical via Postgres `ltree`.

- `id`, `name`, `slug`, `path` ltree, `description`, `icon` (FontAwesome class or SVG name), `is_active`
- GIST index on `path`
- Seed (top-level): Server hardware, Networking, Storage, Compute, Kabels & connectoren, Power, Audio/Video pro, Meetapparatuur, 3D printers & CNC, Software licenties, Boeken & documentatie, Overig

### `listings`

- `id`, `ulid`, `user_id` FK, `category_id` FK
- `title` (255), `slug` (unique within user_id scope)
- `description` text (markdown, sanitised via CommonMark + HTMLPurifier on render)
- `condition` enum (`new`, `used`, `defective`, `for_parts`)
- `price_cents` (integer ≥ 0), `currency` ('EUR' constant for now)
- `is_trade_allowed` boolean
- `region_postcode` char(4) (NL postcode digits only; no house number)
- `shipping_options` jsonb (`{pickup: bool, post: bool}`)
- `state` enum (`draft`, `pending_review`, `published`, `sold`, `archived`, `rejected`)
- `published_at`, `sold_at`, `moderation_notes` text (nullable)
- `view_count` integer default 0
- `search_vector` tsvector (Postgres generated column on `title || ' ' || description`, Dutch dictionary)
- `created_at`, `updated_at`, `deleted_at`
- Indexes: `state`, `category_id`, `(state, published_at desc)`, GIN on `search_vector`

### `listing_photos`

Max 10 per listing, ordered.

- `id`, `listing_id` FK, `disk` ('local'|'s3'|'ipfs'), `path`, `width`, `height`, `mime`, `byte_size`, `position` (1–10)
- Unique `(listing_id, position)`

### `reports`

Foundation: schema + admin view only. Public reporting UI is minimal (`POST /listings/{ulid}/report`).

- `id`, `reportable_type` (polymorphic), `reportable_id`
- `reporter_user_id` (nullable for system-generated)
- `reason` enum (`illegal`, `stolen`, `spam`, `wrong_category`, `other`)
- `details` text, `status` enum (`open`, `resolved`, `dismissed`)
- `resolved_by_user_id` (nullable), `resolution_note` text (nullable)
- `created_at`, `updated_at`

### `transactions` (DAC7-ready, empty in Foundation)

Schema present, logic deferred to DAC7 sub-project.

- `id`, `listing_id` FK, `buyer_user_id`, `seller_user_id`
- `amount_cents`, `currency`, `status` enum (`pending`, `completed`, `cancelled`)
- `completed_at`, `off_platform` boolean (true for handshake/cash deals, false for on-platform escrow)
- `external_tx_ref` (nullable; blockchain tx hash when Web3 sub-project lands)

### `admin_actions` (audit log)

- `id`, `user_id` (admin/moderator), `action` (string), `target_type`, `target_id`
- `meta` jsonb, `created_at`, `ip_hash`

### Standard Laravel tables

`sessions` (Redis-backed via cache driver fallback), `jobs`, `failed_jobs`, `cache`, `cache_locks`, `password_resets`.

---

## 4. Authentication Flows

### 4.1 Email + Password

**Registration:**
1. `POST /register` — Livewire form (email, username, display_name, password, accept_tos checkbox)
2. Validate → create `users` + `user_identities(provider=password)` + `legal_acceptances` row
3. Send signed verify-email link (60 min TTL) via Mailpit (dev) / SMTP (prod)
4. Redirect to `/email/verify-notice`
5. Verify link click → set `email_verified_at`, auto-login, redirect `/`

**Login:**
1. `POST /login` (Livewire), throttled 5/min per `(IP, email)` via Laravel RateLimiter
2. Match `user_identities(provider=password)` → `Hash::check` → session login
3. Failed: generic "credentials invalid" (no user enumeration)

**Password reset:**
1. `POST /forgot-password` → uses Laravel `password_resets` table, signed link 60 min TTL
2. `GET /reset-password/{token}` → form → `POST /reset-password`
3. Updates `password_hash` on `user_identities(provider=password)`; **creates that identity row if user previously had only OAuth/SIWE**
4. Throttle 3/hour per email

### 4.2 OAuth (GitHub, GitLab)

1. `GET /oauth/{provider}/redirect` → Socialite redirect
2. Callback `GET /oauth/{provider}/callback`:
   - Lookup `user_identities(provider=oauth_github|oauth_gitlab, provider_uid=$id)`
   - **Match** → login that user
   - **No match, email matches existing user:**
     - If user is currently authenticated → link identity to that user
     - If anonymous → show "this email already exists, log in with existing method first to link" (no silent merge — prevents account takeover via OAuth email override)
   - **No match, new user** → onboarding (username, ToS-accept) → create user + identity → login
3. Providers configured via `.env`: `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITLAB_CLIENT_ID`, `GITLAB_CLIENT_SECRET`
4. **No Google/Facebook providers exist in code.** Hard exclusion per project policy.

### 4.3 SIWE (Sign-In With Ethereum, EIP-4361)

1. `GET /auth/web3/nonce?address=0x...` — create `auth_nonces` row, return JSON `{nonce, message}` where message follows EIP-4361 format with `domain=cloudmarktplaats.nl`
2. Browser signs via `window.ethereum` (MetaMask) or WalletConnect v2 → returns signature
3. `POST /auth/web3/verify` — body `{address, signature, message}`:
   - Verify nonce exists, not expired, not used
   - Verify signature via `kornrunner/keccak` + `simplito/elliptic-php` (EIP-191 personal_sign recovery → recover address → compare to claimed address)
   - Mark nonce used
   - Lookup `user_identities(provider=siwe, provider_uid=lowercase(address))`
     - Match → login
     - No match → onboarding (username, email, ToS-accept) → create user + identity → login
4. Throttle 10/min per IP
5. CSRF-exempt (signature is the proof)

### 4.4 Identity linking (`/profile/security`)

Authenticated user can add identities: "Link GitHub", "Link GitLab", "Link wallet", "Add password" (for OAuth/SIWE-only users).

**Last-method protection:** server-side guard `IdentityService::canUnlink($user, $identity)` refuses to unlink the user's last identity. UI disables the unlink button accordingly. Test coverage required.

### 4.5 Two-Factor Authentication (TOTP)

Library: `pragmarx/google2fa-laravel` (RFC 6238, 30-second window, 6 digits).

**Enable flow (`/profile/security/2fa/enable`):**
1. Generate base32 secret + 8 single-use recovery codes
2. Display QR (`otpauth://totp/cloudmarktplaats.nl:{username}?secret=...`) + raw key for manual entry
3. User scans with authenticator app
4. User submits first TOTP code → verify → set `two_factor_confirmed_at`, encrypted-store secret + recovery codes
5. Show recovery codes once (UI suggests download as `.txt`)

**Login challenge:**
1. After successful primary auth (password/OAuth/SIWE), check `two_factor_confirmed_at`
2. If set → redirect `/2fa/challenge`. Session holds `pending_2fa_user_id` flag; full login not yet established.
3. Form accepts 6-digit TOTP OR longer recovery code (auto-detect by length)
4. Match → complete login. Recovery code → remove from encrypted array.
5. Throttle 5/min per pending session

**Disable 2FA:** requires current TOTP code AND password (or re-auth via OAuth/SIWE if no password).

**Regenerate recovery codes:** requires TOTP, new codes shown once.

**Admin override:** moderator/admin can disable 2FA for a user via Filament after verification flow. Logged to `admin_actions`.

**Known gap (Foundation):** total lockout (no 2FA token, no recovery code, no email access) requires manual support intervention. Recovery flow deferred to "account-recovery" sub-project. Documented in README.

### 4.6 Middleware pipeline

- **Global:** `EncryptCookies`, `StartSession`, `VerifyCsrfToken`, `SubstituteBindings`, `IpStripperLogger` (custom; scrubs IP from `last_login_ip` and request logs after 24h)
- **Auth-guarded routes:** `auth`, `legal` (LegalAcceptanceMiddleware — re-prompts user to accept ToS when a new published version exists since their last acceptance)
- **Admin routes:** `auth` + `role:admin|moderator`

---

## 5. Listings

### 5.1 Routes

| Route | Auth | Purpose |
|---|---|---|
| `GET /` | public | Homepage: recent + featured |
| `GET /c/{category-path}` | public | Category browse (subcats included via ltree `<@`) |
| `GET /listings` | public | All listings, filter + sort |
| `GET /listings/{ulid}-{slug}` | public | Detail (seller contact button redirects to login for anonymous) |
| `GET /listings/new` | auth+legal | Create wizard |
| `POST /listings` | auth+legal | Store |
| `GET /listings/{ulid}/edit` | auth+owner | Edit form |
| `PUT /listings/{ulid}` | auth+owner | Update |
| `POST /listings/{ulid}/state` | auth+owner | State transition (publish, mark-sold, archive) |
| `DELETE /listings/{ulid}` | auth+owner | Soft delete |
| `POST /listings/{ulid}/report` | auth | Create reports row |
| `GET /search?q=...` | public | Postgres FTS query |

### 5.2 Create wizard (Livewire, 3 steps, draft auto-save)

1. **Basics**: category picker (cascading dropdown via ltree, or text search), title, condition, price (EUR), is_trade_allowed
2. **Details**: description (markdown editor with preview), region_postcode (4 digits), shipping_options
3. **Photos**: drag-drop upload (Livewire `WithFileUploads` + Alpine.js sortable), 1–10 photos, reorder

After step 3 submit → state = `pending_review` (moderation via Filament). Auto-publish flag hardcoded false in Foundation (becomes reputation-driven in reputation sub-project).

### 5.3 Photo pipeline

Upload → temporary `storage/app/livewire-tmp/` → on form submit:
1. `StoreListingPhotoJob` (Redis-queued):
   - Validate MIME via finfo (whitelist: image/jpeg, image/png, image/webp)
   - Validate dimensions (max 8000×8000, min 200×200)
   - **Strip EXIF** (Intervention/Image `->stripExif()`) — privacy default; removes GPS, camera, timestamps
   - Generate variants: `original` (max 2000px long edge, source mime preserved), `card` (600×600 fit, webp), `thumb` (200×200 fit, webp)
   - Store via `StorageInterface::put($disk, $path, $bytes)` — `local` driver in Foundation
   - Path convention: `listings/{listing_ulid}/{photo_id}/{variant}.{ext}` where `{variant}` ∈ {`original`, `card`, `thumb`}
   - Insert `listing_photos` row storing the **base path** (without variant suffix). A `ListingPhoto::urlFor(string $variant)` helper composes the full path. This avoids three rows per photo and keeps deletions atomic.

### 5.4 StorageInterface

```php
// app/Services/Storage/StorageInterface.php
interface StorageInterface
{
    public function put(string $path, string $contents, array $options = []): string;
    public function get(string $path): string;
    public function url(string $path): string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
}
```

Bindings: `LocalStorage` (Foundation), `S3Storage` (later sub-project — when sponsoring or scaling triggers it), `IpfsStorage` (Web3 sub-project). Resolved via `StorageManager` reading `config('cloudmarktplaats.storage.driver')`.

### 5.5 State machine

```
draft ──→ pending_review ──→ published ──→ sold
                          ↘             ↘ archived
                            rejected (back to draft, with moderation_notes)
```

Transitions through `ListingStateService::transition($listing, $newState)`:
- Validates allowed transitions
- Fires events (`ListingPublished`, `ListingSold`, `ListingRejected`, `ListingArchived`)
- Events have empty listeners in Foundation; later sub-projects subscribe (search-indexing, reputation, DAC7)

### 5.6 Interim search (Postgres FTS)

- `search_vector` is a Postgres **STORED** generated column: `to_tsvector('dutch', coalesce(title,'') || ' ' || coalesce(description,''))` (STORED so the GIN index can be used directly)
- Query: `WHERE search_vector @@ plainto_tsquery('dutch', :q) ORDER BY ts_rank(search_vector, plainto_tsquery('dutch', :q)) DESC`
- Combinable with: category filter (ltree `<@`), price range, condition, region prefix (postcode first 2 digits)
- Replaced by Meilisearch in search sub-project; `SearchInterface` already defined so swap is internal

### 5.7 Error handling

- Photo upload failures → listing stays in draft, per-photo error shown in UI
- State transition errors → 422 + form error, no DB mutation
- Moderation rejection → email notification with reason; listing returns to draft

### 5.8 View-count anti-abuse

`IncrementViewJob` throttled per `(listing_id, ip_hash)` — max 1× per hour. Hash uses daily-rotating pepper.

---

## 6. Admin Panel (Filament 3)

### 6.1 Access

`/admin` panel. Filament's built-in auth disabled; uses application auth (including 2FA challenge). Authorization via `role:admin|moderator` middleware.

### 6.2 Resources

| Resource | Allowed roles | Actions |
|---|---|---|
| Users | admin | List/filter, view, edit role, ban/unban (with reason), force-disable 2FA (audited), soft-delete |
| Listings | admin + moderator | List (filters: state, category, has-reports, date), bulk publish/reject, view detail incl. photos, reject with reason (state→draft + email to owner) |
| Categories | admin | Tree view via ltree, drag-drop reorder, create/edit/move, toggle is_active. Slug auto-derived from name; path auto-derived from parent. |
| Reports | admin + moderator | List (filter status), view detail (link to reported entity), resolve/dismiss with note. Resolve action can trigger state change on target (e.g., listing → archived) |
| Legal documents | admin only | CRUD ToS/Privacy per locale + version. "Publish new version" action sets `published_at=now()`. Old versions remain readable (legal_acceptances reference historical versions). Markdown editor with preview. |
| Admin actions | admin (read-only) | Filterable audit log |

### 6.3 Audit logging

`ActionLoggerObserver` registered on Filament's `Action::after()` hook. Every moderator/admin action writes an `admin_actions` row with target reference, meta payload, and ip_hash.

### 6.4 Dashboard widgets

- Pending reviews count (listings in `pending_review`)
- Open reports count
- New users last 7 days (chart)
- Active listings count
- Outdated ToS acceptance count

### 6.5 Hard exclusion

**No "impersonate user" feature in Foundation.** Privacy + audit complexity. May appear later with explicit consent flow.

---

## 7. Testing Strategy

### 7.1 Framework

Pest 3 on PHPUnit 11.

### 7.2 Test pyramid

| Layer | Tool | Scope | Examples |
|---|---|---|---|
| Unit | Pest | Pure services, no DB | `SiweMessageBuilder`, `StorageManager` driver resolution, `Dac7Service` (returns zero) |
| Feature | Pest + RefreshDatabase on test Postgres | HTTP, Livewire, jobs end-to-end | Login per provider, listing wizard, 2FA challenge, admin actions |
| Browser | Pest 3 Browser (Playwright) | E2E happy paths | Register → verify → create listing → publish; SIWE with mocked wallet |

### 7.3 Test DB

Separate Postgres 16 container in `docker-compose.test.yml` (or `--profile test`), `ltree` extension installed. Migrations run via `RefreshDatabase`. For `pest --parallel`, each worker gets its own database (`testing_1`, `testing_2`, ...) via Pest's parallel-database setup hook; templates seeded once per worker for speed.

### 7.4 Fixtures

- Factories for all entities
- Seeders: `CategorySeeder` (12 top-level), `LegalDocumentSeeder` (NL+EN placeholders), `DemoUserSeeder` (admin@example.local, user@example.local, both with known passwords; test-only flag)
- SIWE fixtures: deterministic ECDSA keypair at `tests/Fixtures/siwe-keypair.json` (test-only, prominently documented as such)

### 7.5 "Foundation done" test checklist

- [ ] Email register + verify + login + logout
- [ ] OAuth flow (GitHub + GitLab via Socialite test pattern)
- [ ] SIWE flow (sign deterministic message with fixture key)
- [ ] Identity linking + last-method protection
- [ ] 2FA enable → challenge → recovery code → disable
- [ ] Password reset (incl. for SIWE-only user adding a password)
- [ ] Legal acceptance middleware (re-prompts on new version)
- [ ] Listing CRUD + every state transition
- [ ] Photo upload: MIME validation, EXIF strip (assert no GPS in output), variants generated
- [ ] Category ltree: create + move + descendants query
- [ ] FTS search: term match + ranking sanity
- [ ] Admin: non-admin gets 403; admin can reject listing with email-notify; audit row written
- [ ] Anonymous browsing: detail page loads without login; contact button redirects to login

### 7.6 Static analysis

- PHPStan level 8 on `app/`
- Laravel Pint (PSR-12)
- Rector deferred

### 7.7 CI (GitHub Actions)

`.github/workflows/ci.yml`:

```
matrix: PHP 8.3 on Postgres 16
jobs:
  lint   → pint --test
  static → phpstan analyse
  test   → pest --parallel
  build  → docker compose build (smoke: stack comes up, /healthz returns 200)
```

Branch protection on `main`: all 4 jobs green required.

### 7.8 Healthcheck

`GET /healthz` (public, no rate limit) → JSON `{db: ok, redis: ok, storage: ok, version: <git-sha>}`. Used by docker healthchecks and (later) monitoring.

---

## 8. Service Interfaces (stubs for later sub-projects)

Each interface ships with one implementation and one caller in Foundation; no orphan interfaces.

```
app/Services/Storage/StorageInterface.php          (impl: LocalStorage; caller: photo pipeline)
app/Services/Search/SearchInterface.php            (impl: PostgresSearchService; caller: /search route)
app/Services/Web3/Web3Service.php                  (noop; caller: SIWE flow only uses signature verifier directly)
app/Services/Dac7/Dac7Service.php                  (noop; returns 0 from getThresholdProgress())
app/Services/Reputation/ReputationService.php      (noop; returns null from getRating())
```

---

## 9. Anonymous Browsing & Auth Gating

Per project policy: browsing is free, login required at action boundary.

| Action | Anonymous? |
|---|---|
| Browse listings, categories, search | Yes |
| View listing detail page | Yes |
| View seller's display name + region (postcode) | Yes |
| Contact seller / send message | No → redirect to login with return_to |
| Create listing | No |
| Favorite, report, review | No |
| View own profile, account settings | No (auth required) |

No registration is silently required until the user takes an action.

---

## 10. Configuration

`config/cloudmarktplaats.php`:

```php
return [
    'features' => [
        'oauth_github'      => env('FEATURE_OAUTH_GITHUB', true),
        'oauth_gitlab'      => env('FEATURE_OAUTH_GITLAB', true),
        'siwe'              => env('FEATURE_SIWE', true),
        'two_factor'        => env('FEATURE_2FA', true),
        'anonymous_browse'  => env('FEATURE_ANON_BROWSE', true),
        'meilisearch'       => env('FEATURE_MEILISEARCH', false),
        'messaging'         => env('FEATURE_MESSAGING', false),
        'reputation'        => env('FEATURE_REPUTATION', false),
        'sponsoring'        => env('FEATURE_SPONSORING', false),
        'donations'         => env('FEATURE_DONATIONS', false),
        'dac7_reporting'    => env('FEATURE_DAC7', false),
        'web3_escrow'       => env('FEATURE_WEB3_ESCROW', false),
        'ipfs_pinning'      => env('FEATURE_IPFS', false),
        'umami_analytics'   => env('FEATURE_UMAMI', false),
    ],
    'storage' => [
        'driver' => env('LISTING_STORAGE_DRIVER', 'local'),
    ],
    'dac7' => [
        'threshold_transactions' => 30,
        'threshold_eur_cents'    => 200000,
    ],
];
```

`.env.example` documents all keys with safe defaults.

---

## 11. README structure

1. What it is + AGPL-3.0 licence statement
2. Tech stack summary
3. Quickstart: `docker compose up -d` + `php artisan migrate --seed`
4. DAC7 legal position (transactions-off-platform clause explicit)
5. Feature-flag table
6. Privacy statement: no trackers, IP-stripping, no-cookie-banner rationale
7. How to contribute (link to GitHub Issues = roadmap)
8. Known gaps (e.g., total-lockout recovery)

---

## 12. Sub-project Roadmap (post-Foundation)

| # | Sub-project | Notes |
|---|---|---|
| 2 | Messaging | New `conversations` + `messages` tables. Real-time deferred to #10. |
| 3 | Search (Meilisearch + Scout) | Swap `SearchInterface` impl. |
| 4 | Reputation/reviews | New `reviews` + `review_replies`. Reads `transactions` for eligibility. |
| 5 | Sponsoring + donations | Stripe integration. Sponsor tiers per bootstrap. |
| 6 | DAC7 module | Reads existing `transactions` schema, builds Belastingdienst XML export. |
| 7 | Web3 / escrow | Polygon Mumbai contract via Hardhat, IPFS pinning via `IpfsStorage` driver. |
| 8 | Analytics (Umami self-host) | New container in compose, config-toggled script include. |
| 9 | Public roadmap UI | GitHub Issues API read-only. Polls module for community voting. |
| 10 | Real-time messaging | Soketi/Reverb WebSocket broadcast. |

Each sub-project gets its own spec → plan → implementation cycle.

---

## 13. Open / Known Gaps

- Total lockout recovery (no 2FA + no recovery code + no email) requires manual support. Recovery flow deferred to dedicated sub-project. Documented in README.
- Reports public reporting UI is minimal in Foundation (single `POST` endpoint). Richer report-with-screenshot flow lives in a later moderation sub-project.
- DAC7 transactions table exists but no transaction creation logic yet — every transaction will originate from a future on-platform purchase flow or be imported via off-platform tracking sub-project.
- No "impersonate user" admin feature; if needed later, requires explicit consent design.

---

## 14. Acceptance criteria (Foundation considered complete)

- All migrations idempotent and reversible
- `docker compose up -d` brings a working stack on a clean machine
- `php artisan migrate --seed` populates demo data
- All 13 items in §7.5 pass in CI
- PHPStan level 8 clean
- Pint clean
- `/healthz` returns 200 with all components ok
- README sections 1–8 written
- A user can: register → verify → enable 2FA → create listing → submit for review → admin approves → listing is publicly visible → search finds it → another (anonymous) user views it → clicks "contact seller" → is redirected to login
