# Phase 1a Design: Authentication & Legal Compliance

**Date:** 2026-04-21
**Phase:** 1a (first slice of Phase 1)
**Depends on:** Phase 0 (PSR-4 architecture, middleware pipeline, CSRF, migrations system) — merged via PR #3
**Followed by:** Phase 1b (Product CRUD), Phase 1c (Messaging + Forum)

---

## Goal

Modernize authentication with OAuth (Google + GitHub) and Web3 wallet login (MetaMask + WalletConnect v2, chain-agnostic), and introduce a versioned legal documents system (Terms of Service + Privacy Policy) with clickwrap acceptance gate.

Username/password login stays as an option.

## Pre-conditions (assumed)

- Pre-launch: no real production data — schema changes are free.
- Target community: NL tech community primarily; English audiences welcome (placeholder EN for now).
- Only strict-functional cookies are in use (session, CSRF) — a simple cookie notice banner is shown, no granular consent required.
- Legal texts drafted by Claude are **placeholders requiring legal review** before launch.

---

## Scope

### In scope

1. **OAuth login** — Google and GitHub, via `league/oauth2-client` + provider packages.
2. **Web3 wallet login** — MetaMask and WalletConnect v2, chain-agnostic (any EVM chain), using EIP-4361 "Sign-In with Ethereum" (SIWE).
3. **Legal documents system** — versioned `legal_documents` table holding ToS and Privacy Policy (NL primary, EN placeholder).
4. **Legal acceptance gate** — middleware enforcing current-version acceptance before any authenticated route (except `/legal/*` and logout).
5. **Cookie banner** — simple dismissible banner, link to privacy policy, localStorage state.
6. **Security/profile page** — `/profile/security`, listing connected OAuth providers and wallets, with link/unlink actions.

### Out of scope (deferred to later phases)

- EN translations of ToS/Privacy (placeholders only).
- Admin UI to manage legal documents (new versions go via migrations in V1).
- Granular cookie consent (only needed if analytics/marketing cookies are added later).
- 2FA.
- Email-verification flow for OAuth accounts (trust the provider's verification).
- Account-merge UI (merge two separate accounts).
- Password reset for Web3-only accounts (N/A — no password).

---

## Architecture

### Directory structure additions (under `src/`)

```
src/
  Controllers/
    OAuthController.php          # OAuth redirect + callback per provider
    Web3Controller.php           # nonce issuance + signature verification
    LegalController.php          # show ToS, show Privacy, POST accept
  Services/
    Auth/
      OAuthProviderFactory.php   # factory for league/oauth2-client providers
      Web3SignatureVerifier.php  # EIP-191 / EIP-4361 signature verify
      Web3NonceGenerator.php     # generate, store, consume, expire
      SiweMessageBuilder.php     # builds EIP-4361 message per spec
  Core/
    Middleware/
      LegalAcceptanceMiddleware.php
    RateLimiter.php              # APCu-backed or DB-fallback request counter
  Models/
    OAuthProvider.php
    WalletAddress.php
    LegalDocument.php
    AuthNonce.php
  Views/
    legal/
      tos.php
      privacy.php
      accept.php                 # clickwrap page
    auth/
      login.php                  # extended: OAuth buttons, Connect Wallet
    profile/
      security.php               # connected providers + wallets
    partials/
      cookie_banner.php
  routes.php                     # extended with new routes
public/
  assets/
    js/
      web3-login.js              # frontend signing flow
      cookie-banner.js           # dismiss + localStorage
bin/
  cleanup-nonces.php             # cronjob for expired nonces
docs/
  oauth-setup.md                 # how to register Google/GitHub apps
```

### New composer dependencies

- `league/oauth2-google`
- `league/oauth2-github`
- `kornrunner/keccak` — keccak-256 hashing
- `simplito/elliptic-php` — secp256k1 signature recovery

### Frontend dependencies (CDN or local bundle)

- `ethers.js` v6 — wallet interaction, signature request
- `@walletconnect/ethereum-provider` — WalletConnect v2 integration

---

## Data model

### New tables

```sql
CREATE TABLE oauth_providers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider ENUM('google','github') NOT NULL,
  provider_uid VARCHAR(255) NOT NULL,
  email VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_provider_uid (provider, provider_uid),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wallet_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  address CHAR(42) NOT NULL,            -- lowercase, incl. 0x prefix
  chain_id INT UNSIGNED NOT NULL,        -- chain where signature was made
  verified_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_address (address),     -- globally unique: one wallet = one identity
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE legal_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('tos','privacy') NOT NULL,
  version INT UNSIGNED NOT NULL,
  language CHAR(2) NOT NULL,             -- 'nl' | 'en'
  content LONGTEXT NOT NULL,             -- markdown
  published_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_type_version_lang (type, version, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auth_nonces (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nonce CHAR(32) NOT NULL,               -- alphanumeric, 32 chars
  address CHAR(42) NULL,                 -- bound to address after issuance
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_nonce (nonce),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Modifications to existing `users` table

```sql
ALTER TABLE users
  ADD COLUMN tos_version INT UNSIGNED NULL,
  ADD COLUMN tos_accepted_at DATETIME NULL,
  ADD COLUMN privacy_version INT UNSIGNED NULL,
  ADD COLUMN privacy_accepted_at DATETIME NULL,
  MODIFY COLUMN email VARCHAR(255) NULL,        -- Web3 users may have no email
  MODIFY COLUMN password_hash VARCHAR(255) NULL; -- OAuth/Web3-only accounts
```

### Invariants

- One wallet address globally ↔ one user account (no multi-accounting via wallets).
- One OAuth `(provider, provider_uid)` ↔ one user account.
- Every user has ≥1 working login method (password hash OR ≥1 OAuth link OR ≥1 wallet). Unlinking the last method is blocked.
- `legal_documents` is append-only; new versions get a new row, old rows stay readable for historical accept records.
- `auth_nonces.consumed_at` is set exactly once; a consumed nonce cannot be used again.

---

## Auth flows

### OAuth flow (Google / GitHub)

1. User clicks "Login met Google" → `GET /auth/oauth/google`.
2. `OAuthController` generates a random `state` token, stores it in session, redirects to Google authorize URL with `state` + `redirect_uri`.
3. Google redirects back: `GET /auth/oauth/google/callback?code=X&state=Y`.
4. Controller verifies `state` matches session value (CSRF protection). On mismatch → 400.
5. Exchange `code` for access token via `league/oauth2-client`, then fetch user info (email, name, `provider_uid`).
6. Lookup `oauth_providers WHERE provider=google AND provider_uid=X`:
   - **Found** → log in that user.
   - **Not found** → lookup `users.email`:
     - If user with that email exists → link (insert `oauth_providers` row, log in).
     - If not → create new user (`username` derived from OAuth name, sanitized to `[a-z0-9_]`; on collision append `_2`, `_3`, … until unique; email from provider, no password), insert `oauth_providers` row, log in.
7. If GitHub returns no public email → use placeholder `noreply_github_<uid>@users.noreply.github.com`. User can edit email later.
8. Check legal acceptance → redirect to `/legal/accept` (with `?return_to`) or to dashboard.

### Web3 flow (SIWE / EIP-4361)

1. User clicks "Connect Wallet" on login page → frontend JS (`web3-login.js`) opens MetaMask or WalletConnect v2 modal.
2. User approves connection. JS reads `address` (lowercased) and `chain_id`.
3. Frontend: `POST /auth/web3/nonce` with `{address, chain_id}` + CSRF token.
   - Backend generates 32-char alphanumeric nonce.
   - Stores row in `auth_nonces` with `address`, `expires_at = NOW() + 5min`, `consumed_at = NULL`.
   - Returns a fully-formed SIWE message string per EIP-4361 (domain, address, statement, URI, version, chain_id, nonce, issued-at).
4. Frontend asks wallet to sign the SIWE message via `personal_sign`. No gas, just signature.
5. Frontend: `POST /auth/web3/verify` with `{message, signature}` + CSRF token.
6. Backend:
   - Parse SIWE message, extract `address` (claimed), `chain_id`, `nonce`.
   - Query `auth_nonces`: must exist, match address, not consumed, not expired.
   - Verify signature: recover address from signature using `kornrunner/keccak` + `simplito/elliptic-php`. Must equal claimed address (case-insensitive).
   - Mark nonce consumed (`UPDATE auth_nonces SET consumed_at = NOW()`).
7. Lookup `wallet_addresses` by `address`:
   - **Found** → log in the linked user.
   - **Not found** → create new user:
     - `username = wallet_<first 8 chars of address without 0x>` (on collision append `_2`, `_3`, … until unique)
     - `email = NULL`, `password_hash = NULL`
     - Insert `wallet_addresses` row with `chain_id` and `verified_at = NOW()`
     - Log in.
8. Check legal acceptance → redirect.

### Legal acceptance gate

- `LegalAcceptanceMiddleware` runs on all authenticated routes except `/legal/*`, `/auth/logout`, and public assets.
- On each request: compare `user.tos_version` with the **latest** published `legal_documents` row of type='tos' (highest `version` where `published_at <= NOW()`). Same for privacy.
- If either version is `NULL` or less than current → 302 to `/legal/accept?return_to=<current_url>`.
- `GET /legal/accept`: render current ToS + Privacy (language preference → fallback NL), single checkbox "Ik accepteer de Algemene Voorwaarden en Privacy Policy", CSRF-protected form.
- `POST /legal/accept`:
  - Validate checkbox checked + CSRF.
  - `UPDATE users SET tos_version = <current>, tos_accepted_at = NOW(), privacy_version = <current>, privacy_accepted_at = NOW()`.
  - Redirect to sanitized `return_to` (default `/dashboard`).

### Profile security page (`/profile/security`)

- Shows:
  - Password set? Yes/No — with "Change password" button (existing flow).
  - OAuth providers: for each of Google/GitHub → either "Linked (email shown) [Unlink]" or "[Link your Google/GitHub]".
  - Wallets: list of linked wallets with shortened addresses + chain_id + "[Unlink]".
- **Link actions** trigger the same OAuth/Web3 flow but in "link" mode (bound to currently logged-in user instead of creating a new account).
- **Unlink** is blocked if it would leave the user with zero working auth methods — server returns friendly error.

---

## Cookie banner

- Simple Bootstrap 5 toast/alert fixed to bottom of viewport.
- Content: "Deze site gebruikt alleen strict-functionele cookies (sessie, beveiliging). [Meer info in ons privacybeleid](/legal/privacy). [Begrepen]".
- Dismiss button stores `cookie_notice_v1_dismissed=true` in localStorage.
- JS checks localStorage on each page load — if present, don't render. Otherwise render.
- No server-side state; content is public and doesn't depend on auth.

---

## Security

- **CSRF**: all POST forms use `View::csrfField()` via existing `CsrfMiddleware`.
- **OAuth state**: random 32-byte token per flow, stored in session, verified on callback.
- **SIWE replay protection**: message includes `domain`, `chain_id`, and one-time nonce; server rejects mismatched domain or reused nonce.
- **Nonce lifecycle**: 5-minute TTL, consumed on first successful verify, cleaned up by `bin/cleanup-nonces.php` (cron every 6h — deletes rows where `expires_at < NOW() - 24h`).
- **Signature verification** is 100% server-side; frontend signature is untrusted input.
- **Rate limits** (via new `Core/RateLimiter` — simple in-memory or APCu store):
  - `/auth/web3/nonce` — 10 req/min per IP
  - `/auth/web3/verify` — 5 req/min per IP
  - `/auth/oauth/*/callback` — 20 req/min per IP
- **OAuth client secrets** only in `.env`, never in git. `.env.example` documents required keys.
- **Address normalization**: always lowercase on write and lookup; EIP-55 checksum addresses are lowercased before DB hit.
- **Password-less accounts**: login flow for Web3-only users has no password path; password reset is not offered for these accounts.

## Edge cases

1. **OAuth email collision with existing password account** → auto-link (pre-launch assumption, no prior consent needed), flash message "Account gekoppeld met bestaand profiel".
2. **GitHub account with private email** → placeholder email, user can update later.
3. **Web3 user without email** → platform usable; actions that *require* email (e.g., email notifications) are skipped gracefully.
4. **Wallet already linked to another account** → on `/profile/security` link attempt → error "Deze wallet is al in gebruik".
5. **Nonce replay** → `consumed_at IS NOT NULL` check rejects.
6. **OAuth provider email changes** → update `oauth_providers.email`; leave `users.email` untouched (user-owned).
7. **Unlinking last auth method** → blocked with friendly error, lists current methods.
8. **ToS update during active session** → next request triggers middleware → redirect to accept page, logout still works.
9. **User refuses to accept** → stuck on accept page; logout is the only exit (explicitly present on the page).
10. **Clock skew on SIWE `issued-at`** → allow ±5 min drift when parsing.

---

## Testing (PHPUnit)

### Unit tests

- `Web3SignatureVerifierTest` — with fixture of pre-signed SIWE messages:
  - valid signature → recovers correct address
  - tampered message → rejects (recovered address mismatches claimed)
  - wrong signer → rejects
  - note: `chain_id` is accepted as-is (chain-agnostic auth); stored for reference but not restricted to a whitelist
- `Web3NonceGeneratorTest` — generate unique, store, lookup, mark consumed, expire-check
- `LegalAcceptanceMiddlewareTest` — fresh user (nulls), outdated user, up-to-date user, exempt routes
- `OAuthProviderFactoryTest` — loads config for Google / GitHub from env, missing env → exception
- `SiweMessageBuilderTest` — produces EIP-4361-compliant output

### Integration tests (with in-memory / test DB)

- `OAuthControllerTest` — mocking provider via `league/oauth2-client` test doubles:
  - callback without state → 400
  - callback with mismatched state → 400
  - new user creation path
  - existing-email link path
  - existing oauth_provider login path
- `Web3ControllerTest` — end-to-end nonce → verify → session contains user
- `LegalControllerTest` — GET renders latest, POST updates users row and redirects to return_to
- `ProfileSecurityTest` — link/unlink paths, last-method protection

### Fixtures

- Pre-signed SIWE messages for verifier tests, stored under `tests/fixtures/siwe/`
- A fixed test keypair (privkey in `tests/bootstrap.php`, never in prod `.env`)

### Coverage target

- All new Services, Controllers, and Middleware: >80% line coverage.

---

## Migrations (execution order)

```
002_create_oauth_providers.sql
003_create_wallet_addresses.sql
004_create_legal_documents.sql
005_create_auth_nonces.sql
006_alter_users_legal_acceptance.sql
007_alter_users_nullable_email_password.sql
008_seed_initial_tos_nl.sql              (placeholder NL text with TODO: juridisch review marker)
009_seed_initial_privacy_nl.sql
010_seed_placeholder_tos_en.sql          (placeholder EN "translation pending")
011_seed_placeholder_privacy_en.sql
```

Idempotency is handled by the migration runner itself (`migrations` tracking table records each executed file, so each `.sql` runs exactly once per environment). Individual SQL files do not need defensive `IF NOT EXISTS` guards.

---

## Routes (additions to `src/routes.php`)

```
GET  /auth/oauth/{provider}                OAuthController::redirect
GET  /auth/oauth/{provider}/callback       OAuthController::callback
POST /auth/web3/nonce                      Web3Controller::nonce
POST /auth/web3/verify                     Web3Controller::verify
GET  /legal/tos                            LegalController::tos
GET  /legal/privacy                        LegalController::privacy
GET  /legal/accept          [auth]         LegalController::showAccept
POST /legal/accept          [auth, csrf]   LegalController::accept
GET  /profile/security      [auth]         ProfileController::security
POST /profile/security/oauth/{provider}/unlink  [auth, csrf]  ProfileController::unlinkOAuth
POST /profile/security/wallet/{id}/unlink       [auth, csrf]  ProfileController::unlinkWallet
```

Middleware pipeline adjustment: register `LegalAcceptanceMiddleware` after `AuthMiddleware` in the authenticated-routes pipeline.

---

## Deliverables

- Working Google + GitHub OAuth on dev and prod.
- Working MetaMask + WalletConnect v2 login (chain-agnostic).
- `/legal/tos` and `/legal/privacy` publicly accessible.
- Clickwrap acceptance gate on first login.
- Cookie banner visible on first visit.
- `/profile/security` for managing auth methods.
- PHPUnit suite green, coverage target met.
- `.env.example` updated with required OAuth keys.
- `docs/oauth-setup.md` explaining how to register Google and GitHub apps and where to paste credentials.
- Placeholder ToS and Privacy texts (NL) with visible "TODO: juridisch review" banner at top.

## Success criteria

- User can log in via username/password, Google, GitHub, MetaMask, or WalletConnect — all reach dashboard.
- User can link all four methods to one account, and unlink any (except the last).
- User who hasn't accepted latest ToS is redirected to `/legal/accept` on any authenticated route.
- User who signs SIWE message with expired nonce is rejected.
- User who tries to link an already-used wallet gets a clear error.
- Cookie banner appears on first visit, disappears after dismiss, stays dismissed.
- All tests pass, PHPUnit coverage ≥80% for new code.

---

## Risks / open items

- **Legal texts need professional review before launch.** Placeholder drafts included; must not ship as-is.
- **WalletConnect v2 relay URL**: the public WalletConnect cloud requires a (free) project ID; must be registered and placed in `.env`. Document this in `oauth-setup.md`.
- **Rate limiter backing store**: if APCu isn't available in prod, fall back to a small `rate_limits` table (decide during implementation).
- **SIWE clock skew tolerance**: set to ±5 min; may need adjustment if users report issues.
