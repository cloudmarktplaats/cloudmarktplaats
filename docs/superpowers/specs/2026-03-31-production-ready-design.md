# Cloudmarkplaats.nl Production-Ready Design

## Overview

Cloudmarkplaats.nl is a privacy-first marketplace platform for IT hardware trading, targeting IT experts and datacenter specialists. This document describes the full design for making the existing Bootstrap Studio codebase production-ready across four phases.

**Target audience:** IT professionals, datacenter specialists — competent users who take responsibility. Interface may be technical.

**Deployment:** Own datacenter/colocation.
**PHP version:** 8.1 minimum.
**Architecture:** PSR-4 namespaced MVC.

---

## Current State Summary

### What Works
- User authentication (login/register/logout) — session-based
- Product marketplace (browse, add, edit, delete, approval workflow)
- Forum (categories, topics, replies)
- User-to-user messaging
- User profiles, reviews, favorites
- Admin dashboard (partially broken)

### Critical Issues
- **Hardcoded DB credentials** in `config.php`
- **Admin panel broken** — calls `$db->update()`/`$db->delete()` that don't exist on legacy Database class
- **XSS vulnerability** in forum content (no sanitization)
- **No file upload validation** (no extension whitelist, 0777 directory permissions)
- **No CSRF tokens** on any forms
- **Two parallel architectures** — legacy `/controllers/` active, `/src/` PSR-4 incomplete and unused
- **Three Database classes** with overlapping functionality

### What's Missing (from requirements)
- OAuth/Web3 authentication
- Legal waiver flow
- Tag system (partial)
- RSS feed aggregator
- Reputation/gamification (badges, fame wall)
- Sponsor section
- Legal pages (ToS, Privacy Policy)
- Native e-commerce for own hardware
- Payment processing (Mollie/Stripe)
- Donation system
- Environment config (.env)
- CI/CD, testing framework

---

## Phase 0: Architecture & Security

**Goal:** Solid foundation. No new features — stabilize, secure, unify architecture.

### 0.1 PSR-4 Migration

Migrate the entire legacy structure to a namespaced PSR-4 architecture under `/src/`:

```
src/
  Core/
    App.php              # Bootstrap, routing, middleware pipeline
    Config.php           # .env-based configuration
    Database.php         # Unified PDO wrapper (merged from 3 versions)
    Router.php           # URL routing with named routes
    Session.php          # Session management
    View.php             # Template renderer
    Middleware/
      CsrfMiddleware.php
      AuthMiddleware.php
      AdminMiddleware.php
  Controllers/
    BaseController.php
    AuthController.php
    ProductController.php
    ForumController.php
    MessageController.php
    ProfileController.php
    DashboardController.php
    HomeController.php
  Models/
    User.php
    Product.php
    Message.php
    ForumTopic.php
    Review.php
  Views/
    layouts/main.php
    auth/
    product/
    forum/
    messages/
    profile/
    dashboard/
    home/
    errors/
```

Key decisions:
- **Three Database classes merged** into one unified class with: PDO wrapper, prepared statements, transaction support, `insert()`, `update()`, `delete()` methods
- Legacy `/controllers/`, `/includes/`, root `Database.php` deleted after migration
- Autoloading via Composer PSR-4 mapping in `composer.json`
- `index.php` becomes thin bootstrap that instantiates `App.php`
- All controller logic preserved — refactored into namespaced classes, not rewritten

### 0.2 Environment Configuration

- `.env` file for all credentials (DB, OAuth keys, JWT secret, payment API keys)
- `.env.example` committed to repo with placeholder values
- `Config.php` loads via `vlucas/phpdotenv` (already in `composer.json`)
- Hardcoded credentials removed from `config.php` (file itself removed)
- Config sections: database, auth, app, mail, payments
- `APP_DEBUG` flag controls error display (off in production)

### 0.3 Security Fixes

**CSRF Protection:**
- `CsrfMiddleware` generates per-session token
- All POST forms include hidden `_csrf_token` field
- Middleware validates token on every POST/PUT/DELETE request
- AJAX requests pass token via `X-CSRF-Token` header

**XSS Prevention:**
- All user content output through `htmlspecialchars()` enforced in View renderer
- Forum content: HTMLPurifier library for rich text sanitization
- Content Security Policy headers in `.htaccess`

**File Upload Hardening:**
- Extension whitelist: `jpg`, `jpeg`, `png`, `webp`
- MIME type verification via `finfo_file()`
- Max 5MB per image, max 5 images per product
- Upload directory permissions `0755` (not `0777`)
- Randomized filenames — no original filename preserved
- Uploaded files stored outside web root where possible, served via PHP

**Broken Admin Fix:**
- `update()` and `delete()` methods included in unified Database class
- Admin controllers migrated to PSR-4 namespace

**Additional:**
- Error reporting disabled in production via `.env` `APP_DEBUG=false`
- Password minimum consistent at 8 characters everywhere
- Session cookie secure flag configurable via `.env`
- Rate limiting on login attempts — simple token bucket in session (max 5 attempts per 15 minutes)

### 0.4 Cleanup

- Delete incomplete `/src/` stubs (current broken PSR-4 code)
- Delete duplicate root `Database.php`
- Delete `counter.php` (not integrated)
- Consolidate `setup.php` + `setup_forum.php` + `database.sql` into single migration system
- Delete `install.php` (broken)
- Delete `install.sql` (stub)

---

## Phase 1: Auth & Core Features

**Goal:** Modern authentication, legal compliance gate, improved core functionality.

### 1.1 OAuth Authentication (Google + GitHub)

- **League OAuth2 Client** (`league/oauth2-client`) with provider packages for Google and GitHub
- New `OAuthController.php` with redirect/callback flow per provider
- Database: `oauth_providers` table links external accounts to users (user_id, provider, provider_uid, email)
- Existing username/password login remains as option alongside OAuth
- First OAuth login auto-creates user account
- Account linking: existing user can connect OAuth provider via profile settings
- Profile shows connected providers with disconnect option

### 1.2 Web3 Wallet Login

- **MetaMask/WalletConnect** via frontend JS (ethers.js)
- Flow: wallet connect -> sign nonce message -> backend verifies signature -> login/register
- Database: `wallet_addresses` table (user_id, address, chain_id, verified_at)
- Backend signature verification via `simplito/elliptic-php` or `kornrunner/keccak`
- One user can link multiple wallets
- No blockchain transactions needed — pure signature-based authentication
- Nonce stored server-side per auth attempt, expires after 5 minutes

### 1.3 Legal Waiver Flow

- On first login (any method) -> redirect to waiver page before platform access
- User must accept before any other page is accessible
- Database: `accepted_at` timestamp + `waiver_version` field on users table
- On waiver text update -> new version number -> re-acceptance required
- Waiver text stored as versioned records in `legal_documents` table
- `AuthMiddleware` checks waiver acceptance on every authenticated request

### 1.4 Product CRUD Improvements

**Tag system:**
- `tags` table with pre-defined popular tags (server, networking, storage, rack, UPS, switch, firewall, etc.)
- Users can add max 5 custom tags per product
- Autocomplete on existing tags to prevent duplicates
- Tags searchable and filterable on product listing page

**Status workflow improvement:**
- Draft -> Pending Review -> Approved / Rejected
- User can edit rejected listing and resubmit
- Admin sees reason field on reject action
- User notified of approval/rejection via dashboard flash message

### 1.5 Messaging Improvements

- Pagination on conversations (20 per page) and messages (50 per page)
- Message timestamps and "read" indicators
- Notification badge in navbar showing unread message count
- Spam/abuse report button per message -> creates admin ticket in `reports` table
- No real-time updates V1 — poll on page load via HTMX

### 1.6 Forum Hardening

- HTMLPurifier on all forum content (XSS fix from Phase 0 applied here)
- Edit/delete own topics and replies (within 24 hours of posting)
- Admin can close/pin topics, delete any content
- Basic search within forum (LIKE queries on title/content)
- Pagination on topic lists (20 per page) and replies (25 per page)

---

## Phase 2: New Features

**Goal:** Reputation system, content feeds, legal pages, sponsor visibility, advanced search.

### 2.1 Reputation & Gamification

**Rating system:**
- 5-star rating + review text
- Only possible after message exchange about a product (verified contact)
- Database: extend existing `reviews` table with `verified_contact` boolean
- Seller average score calculated and cached on user record (`avg_rating`, `review_count`)
- Review responses: seller can reply once per review

**Fame & Shame Wall:**
- Public page at `/reputation`
- Top sellers: highest rating + most transactions (minimum 5 reviews to qualify)
- Warning section: users with repeated negative reviews or admin flags
- Admin can manually add/remove users from shame wall

**Badge system:**
- `badges` table (name, description, icon, criteria)
- `user_badges` junction table (user_id, badge_id, awarded_at)
- Auto-awarded on triggers:
  - First Listing, 10 Sales, 50 Sales
  - 1 Year Active, Top Reviewer
  - Verified Seller (completed 10+ successful contacts)
- Badges visible on profile page and next to username in listings

### 2.2 RSS Feed Aggregator

- Pre-configured feeds: security.nl headlines, NCSC, feedspot datacenter feeds
- `rss_feeds` table (already in schema) for feed URLs + metadata
- Background fetch via cron job (PHP CLI script) — results cached in `rss_items` table
- Cache TTL: 15 minutes per feed
- User preferences: selectable feeds shown on dashboard (`user_feed_preferences` table)
- Dedicated "Tech Feeds" page at `/feeds` with responsive card layout
- Fallback on unreachable feed: show cached items + "last updated" timestamp
- Admin can add/remove/disable feeds

### 2.3 Sponsor Section

- Homepage: sponsor logo carousel with three confirmed sponsors:
  - **InternalsHost.eu** (primary sponsor)
  - **EASEO.nl** (technology partner)
  - **SourceParts.eu** (hardware partner)
- Footer: "Ondersteund door" with all three logos on every page
- Dedicated sponsor/about page at `/sponsors` with descriptions
- Admin-manageable: `sponsors` table (name, logo_path, url, description, sort_order, active)
- Optional donation button (external link, no in-platform payment flow)
- Banner text: "100% gratis platform, mogelijk gemaakt door onze sponsors"

### 2.4 Legal Pages

**Terms of Service (`/terms`):**
- Platform disclaimer: "Cloudmarkplaats.nl faciliteert alleen contact tussen gebruikers. Transacties, risico's en geschillen zijn tussen partijen onderling."
- Usage terms, conduct rules, account termination clauses
- Versioned in `legal_documents` table (connected to waiver flow from Phase 1)

**Privacy Policy (`/privacy`):**
- GDPR compliant, minimal data storage
- What data, why, how long, user rights (access, deletion, portability)
- Cookie policy section: functional cookies only, no tracking

**Cookie Consent:**
- Simple dismissible banner: "Wij gebruiken alleen functionele cookies"
- No opt-in required (functional cookies exempt under GDPR)
- No cookie wall

**Mediation Service (`/mediation`):**
- Information page about optional paid mediation for disputes
- No in-platform flow — external contact form reference

### 2.5 Search & Filter Improvements

- MySQL FULLTEXT index on product name, description, tags
- Filters: new/used condition, price range (min/max input), category dropdown, tag selection
- Sort options: newest, price low-high, price high-low, relevance (fulltext score)
- Wishlist/save button on every product card (uses existing `favorites` table)
- Search results page with active filter display and clear-all option
- URL-based filter state (shareable search URLs)

---

## Phase 3: Own Hardware Shop

**Goal:** Native e-commerce section for official Cloudmarkplaats hardware sales.

### 3.1 Shop Architecture

- Separate section under `/shop` route
- Visually distinct from marketplace: "Official Cloudmarkplaats" badge/accent
- Own `ShopController.php` and `ShopProductController.php`
- Shop products use same `products` table with `is_shop_item = true` flag and `seller_type = 'official'`
- No approval workflow for own items — direct live
- Additional inventory fields: `stock_quantity`, `sku`, `weight`, `shipping_cost`

### 3.2 Inventory Management

- Admin interface for own stock management
- Stock tracking: quantity decremented on order placement, restored on cancellation
- Low stock alerts: configurable threshold per product, shown on admin dashboard
- No product variants for V1 — one listing per product configuration
- Bulk CSV import as nice-to-have (not V1 critical)

### 3.3 Checkout Flow

- Shopping cart: persisted to DB for logged-in users (login required for checkout)
- `carts` table (user_id, session_id) + `cart_items` table (cart_id, product_id, quantity)
- Checkout steps: cart review -> shipping address -> payment method -> order confirmation
- Shipping address input (not stored on profile unless user opts in — privacy-first)
- Order summary review before payment initiation
- Guest checkout not supported V1 — login required

### 3.4 Payment Integration

**Mollie (primary, NL/BE):**
- `mollie/mollie-api-php` composer package
- Methods: iDEAL, Bancontact, creditcard, PayPal
- Webhook endpoint for async payment status updates
- Redirect flow: checkout -> Mollie hosted payment page -> return URL with status

**Stripe (international):**
- `stripe/stripe-php` composer package
- Stripe Checkout Sessions (hosted payment page)
- Webhook for payment confirmation
- Used when Mollie payment methods are not available/applicable

**Payment routing:**
- Payment method selection on checkout page
- NL/BE bank options routed to Mollie, everything else to Stripe
- `orders` table: user_id, status, total, payment_provider, payment_id, paid_at
- `order_items` table: order_id, product_id, quantity, price_at_purchase
- Order statuses: pending -> paid -> processing -> shipped -> delivered / cancelled / refunded

### 3.5 Order Management

- **User side:** Order history on dashboard, order detail page with status tracking
- **Admin side:** Order overview with filters, status updates (processing -> shipped with tracking code), refund trigger
- **Email notifications** via PHPMailer (already in `composer.json`):
  - Order confirmation
  - Shipping notification with tracking
  - Status change updates
- **PDF invoice generation** via `dompdf/dompdf` (optional V1)

---

## Phase 4: Polish & Deployment

**Goal:** Production-grade admin tools, testing, performance, accessibility, deployment pipeline.

### 4.1 Admin Panel Extension

- Unified admin dashboard with widgets: pending listings, open reports, recent orders, revenue stats
- Product moderation: approve/reject with reason, bulk actions
- User management: assign roles, deactivate accounts, review abuse reports
- Forum moderation: close/pin/delete topics, delete replies
- Sponsor management: upload logos, change order, activate/deactivate
- RSS feed management: add/remove feeds, manual cache refresh
- Order management: status updates, refunds
- Basic analytics: registrations per week, listings per category, active users, revenue chart

### 4.2 Testing

**PHPUnit for unit/integration tests:**
- Models: CRUD operations, validation, edge cases
- Controllers: route handling, auth checks, input validation
- Middleware: CSRF, auth, admin guard verification
- Payment: mock Mollie/Stripe webhook responses

**Critical E2E flows (manual test protocol):**
- Register -> OAuth login -> waiver accept -> create listing -> messaging -> review
- Shop: browse -> cart -> checkout -> payment -> order status
- Admin: approve listing, manage users, process order

**Security tests:**
- CSRF token validation on all POST endpoints
- XSS attempts on forum and product descriptions
- File upload with wrong MIME types
- SQL injection attempts on search/filter parameters
- Unauthorized access on admin/protected routes

### 4.3 Performance

- Database indexes on all foreign keys and frequently queried WHERE/ORDER BY columns
- Query optimization: resolve N+1 queries in messaging and forum
- Image optimization: WebP conversion on upload via Intervention Image (already in `composer.json`)
- Lazy loading on product images (`loading="lazy"`)
- OPcache enabled for PHP bytecode caching
- Redis or APCu for session storage and feed cache (available in own datacenter)
- Target: acceptable performance for ~1000 concurrent users

### 4.4 UX/UI Polish

- Consistent color palette throughout all views:
  - Oxford Blue (primary)
  - Space Cadet (secondary)
  - Verdigris (accent)
  - White (background)
- Responsive verification: mobile, tablet, desktop breakpoints
- Loading states on HTMX requests (spinners/skeleton screens)
- Empty states with call-to-actions ("Nog geen producten? Plaats je eerste listing")
- Flash messages consistently styled (success/error/warning/info)
- WCAG AA compliance: contrast ratios, focus indicators, aria labels, keyboard navigation

### 4.5 Deployment & DevOps

**Environment setup:**
- `.env.production`, `.env.staging` templates in repo
- Apache vhost configuration with security headers
- PHP-FPM tuning for ~1000 concurrent users
- MySQL tuning (innodb_buffer_pool_size, query_cache)

**Database migrations:**
- Simple PHP migration system: timestamped SQL files in `/migrations/`
- `migrate.php` CLI script: tracks executed migrations in `migrations` table
- Rollback support per migration (up/down methods)

**Monitoring & Logging:**
- Monolog for application logging (file-based, daily rotation)
- Error tracking: log all exceptions with stack trace
- Access logging via Apache
- Health check endpoint at `/api/health` for uptime monitoring

**Backup strategy:**
- Daily MySQL dump via cron
- Upload directory backup
- 30-day retention

**CI/CD:**
- GitHub Actions pipeline: PHP_CodeSniffer lint -> PHPUnit tests -> deploy via SSH
- Deploy script: `git pull` -> `composer install --no-dev` -> `php migrate.php` -> OPcache reset

---

## Database Schema Additions

Summary of new tables required across all phases:

### Phase 0
- `migrations` (id, filename, executed_at)

### Phase 1
- `oauth_providers` (id, user_id, provider, provider_uid, email, created_at)
- `wallet_addresses` (id, user_id, address, chain_id, verified_at, created_at)
- `legal_documents` (id, type, version, content, created_at)
- `reports` (id, reporter_id, reported_user_id, message_id, reason, status, created_at)

### Phase 2
- `badges` (id, name, description, icon, criteria_type, criteria_value)
- `user_badges` (id, user_id, badge_id, awarded_at)
- `rss_items` (id, feed_id, title, url, description, published_at, fetched_at)
- `user_feed_preferences` (id, user_id, feed_id, enabled)
- `sponsors` (id, name, logo_path, url, description, sort_order, active, created_at)

### Phase 3
- `carts` (id, user_id, session_id, created_at, updated_at)
- `cart_items` (id, cart_id, product_id, quantity)
- `orders` (id, user_id, status, total, shipping_address_json, payment_provider, payment_id, paid_at, created_at, updated_at)
- `order_items` (id, order_id, product_id, quantity, price_at_purchase)

### Existing tables modified
- `users`: add `waiver_version`, `accepted_at`, `avg_rating`, `review_count`
- `products`: add `is_shop_item`, `seller_type`, `stock_quantity`, `sku`, `weight`, `shipping_cost`, `status` enum expansion
- `reviews`: add `verified_contact`
- `rss_feeds`: already exists in schema, may need column adjustments

---

## Technology Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.1+ |
| Architecture | PSR-4 MVC |
| Database | MySQL 8.0 |
| Frontend | Bootstrap 5, HTMX |
| Auth | League OAuth2 (Google, GitHub), ethers.js (Web3) |
| Payments | Mollie API, Stripe API |
| Email | PHPMailer |
| Images | Intervention Image |
| Security | HTMLPurifier, CSRF middleware |
| Logging | Monolog |
| Cache | Redis/APCu |
| Testing | PHPUnit |
| CI/CD | GitHub Actions |
| Env | vlucas/phpdotenv |

---

## Confirmed Sponsors

1. **InternalsHost.eu** — Primary sponsor
2. **EASEO.nl** — Technology partner
3. **SourceParts.eu** — Hardware partner

---

## Success Criteria

- All core features work end-to-end
- Security best practices implemented (CSRF, XSS, upload validation, prepared statements)
- Responsive and performant (~1000 concurrent users)
- Clear sponsor visibility on homepage and footer
- Legal basis in order (ToS, Privacy Policy, waiver flow)
- Admin can moderate platform (listings, users, forum, orders)
- Zero critical bugs
- Deployment-ready with documentation
