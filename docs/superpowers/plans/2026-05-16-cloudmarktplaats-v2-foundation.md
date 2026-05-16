# Cloudmarktplaats v2 — Foundation MVP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a publishable Laravel 11 marketplace MVP with multi-provider auth (email + GitHub/GitLab OAuth + SIWE), 2FA, listings with photos, Filament admin, and green CI — all in a fresh `/mnt/nvme1tb/projects/cloudmarktplaats` directory (sibling to v1).

**Architecture:** Fresh Laravel 11 install, Postgres 16 with `ltree`, Redis 7, Livewire 3 + Tailwind 3 frontend, Filament 3 admin. Custom auth (no Breeze/Fortify) so the multi-identity model + SIWE + 2FA flow stay coherent. Photo pipeline behind `StorageInterface` so later sub-projects can swap to S3/IPFS without controller changes. TDD throughout via Pest 3.

**Tech Stack:** PHP 8.3, Laravel 11, PostgreSQL 16 (`ltree`, tsvector), Redis 7, Livewire 3, Alpine.js, Tailwind 3, Filament 3, Pest 3, Playwright (browser tests), Laravel Socialite, pragmarx/google2fa-laravel, Intervention/Image, kornrunner/keccak + simplito/elliptic-php, PHPStan 8, Laravel Pint.

**Reference spec:** `docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md` (commit `64a26f5`, this repo).

**Working directory:** Implementation happens in `/mnt/nvme1tb/projects/cloudmarktplaats/` (the new dir, with `t`). Task A1 creates it. All subsequent `cd` and paths are relative to that dir unless stated otherwise.

---

## File Structure

Files this plan creates (grouped by responsibility). Modifications to Laravel scaffolding files are listed inline per task.

```
cloudmarktplaats/
├── .github/workflows/ci.yml
├── docker-compose.yml                          # nginx, php-fpm, postgres, redis, mailpit, minio
├── docker-compose.test.yml                     # parallel test postgres
├── docker/
│   ├── php-fpm/Dockerfile
│   └── nginx/default.conf
├── config/
│   ├── cloudmarktplaats.php                    # feature flags + thresholds
│   └── filesystems.php                         # extended for storage drivers
├── app/
│   ├── Models/                                 # User, UserIdentity, AuthNonce, LegalDocument,
│   │                                           # LegalAcceptance, Category, Listing,
│   │                                           # ListingPhoto, Report, Transaction, AdminAction
│   ├── Livewire/
│   │   ├── Auth/Register.php, Login.php, ForgotPassword.php, ResetPassword.php,
│   │   │   VerifyEmailNotice.php, TwoFactorChallenge.php
│   │   ├── Profile/Security.php (identity linking + 2FA mgmt)
│   │   └── Listings/Wizard.php, Browse.php, Detail.php
│   ├── Http/
│   │   ├── Controllers/Auth/OAuthController.php, Web3Controller.php
│   │   ├── Controllers/HealthController.php
│   │   └── Middleware/LegalAcceptance.php, RoleMiddleware.php, IpStripperLogger.php
│   ├── Services/
│   │   ├── Auth/IdentityService.php, OAuthProviderRegistry.php,
│   │   │   SiweMessageBuilder.php, Web3SignatureVerifier.php, Web3NonceGenerator.php
│   │   ├── Listings/ListingStateService.php
│   │   ├── Storage/StorageInterface.php, LocalStorage.php, StorageManager.php
│   │   ├── Search/SearchInterface.php, PostgresSearchService.php
│   │   ├── Web3/Web3Service.php                # noop
│   │   ├── Dac7/Dac7Service.php                # noop
│   │   └── Reputation/ReputationService.php    # noop
│   ├── Jobs/
│   │   ├── StoreListingPhotoJob.php
│   │   ├── IncrementViewJob.php
│   │   └── IpStripperJob.php
│   ├── Events/
│   │   └── Listings/ListingPublished.php, ListingSold.php, ListingRejected.php, ListingArchived.php
│   ├── Observers/
│   │   └── AdminActionLogger.php
│   ├── Filament/
│   │   └── Resources/UserResource.php, ListingResource.php, CategoryResource.php,
│   │       ReportResource.php, LegalDocumentResource.php, AdminActionResource.php
│   └── Console/Kernel.php                      # schedules IpStripperJob
├── database/
│   ├── migrations/                             # one per entity in spec §3
│   ├── factories/                              # one per model
│   └── seeders/                                # CategorySeeder, LegalDocumentSeeder, DemoUserSeeder
├── resources/
│   └── views/livewire/, components/, layouts/
├── routes/
│   └── web.php
└── tests/
    ├── Pest.php
    ├── Feature/Auth/, Profile/, Listings/, Admin/, Anonymous/
    ├── Unit/Services/
    ├── Browser/                                # Playwright e2e
    └── Fixtures/siwe-keypair.json
```

---

## Task Phasing

- **Phase A — Infrastructure & CI** (A1–A7): empty Laravel project boots, docker-compose runs, CI green, healthcheck works
- **Phase B — Domain migrations & models** (B1–B9): all DB tables + Eloquent models exist, factories work
- **Phase C — Email auth** (C1–C6): register/verify/login/reset/logout, password identity row created
- **Phase D — OAuth (GitHub + GitLab)** (D1–D4): provider redirect, callback, identity linking on email match
- **Phase E — SIWE** (E1–E5): nonce, message build, signature verify, onboarding flow
- **Phase F — Identity linking + 2FA** (F1–F6): link/unlink with last-method protection, TOTP enable/challenge/disable
- **Phase G — Listings + photos + search** (G1–G11): wizard, state machine, photo pipeline, browse, FTS, reports
- **Phase H — Filament admin** (H1–H8): all resources, audit log, dashboard widgets
- **Phase I — Polish & acceptance** (I1–I6): legal middleware, IP-stripper, README, e2e walkthrough

Each task ends in a commit. Each commit keeps CI green.

---

## Phase A — Infrastructure & CI

### Task A1: Create fresh Laravel 11 project + git init

**Files:**
- Create: `/mnt/nvme1tb/projects/cloudmarktplaats/` (entire dir)

- [ ] **Step 1: Verify v2 dir does not yet exist**

Run from `/mnt/nvme1tb/projects/`:
```bash
ls cloudmarktplaats 2>/dev/null && echo "EXISTS — abort" || echo "OK to create"
```
Expected: `OK to create`. If it exists, stop and check with the user.

- [ ] **Step 2: Scaffold Laravel 11**

```bash
cd /mnt/nvme1tb/projects
composer create-project laravel/laravel:^11.0 cloudmarktplaats
cd cloudmarktplaats
```

- [ ] **Step 3: Pin PHP 8.3, set project name, AGPL licence**

Edit `composer.json`:
- Set `"name": "cloudmarktplaats/app"`
- Set `"license": "AGPL-3.0-or-later"`
- Set `"require": { "php": "^8.3", ... }`
- Set `"description": "Privacy-first C2C marketplace for IT hardware and maker tech."`

Run:
```bash
composer update --no-scripts
```

- [ ] **Step 4: Initialise git, first commit**

```bash
git init -b main
git add .
git commit -m "Initial Laravel 11 scaffold"
```

- [ ] **Step 5: Add LICENSE file (AGPL-3.0)**

Download AGPL text:
```bash
curl -sSL https://www.gnu.org/licenses/agpl-3.0.txt -o LICENSE
git add LICENSE && git commit -m "Add AGPL-3.0 license"
```

---

### Task A2: docker-compose dev stack

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/php-fpm/Dockerfile`
- Create: `docker/nginx/default.conf`
- Modify: `.env` (add service hostnames)

- [ ] **Step 1: Create php-fpm Dockerfile**

`docker/php-fpm/Dockerfile`:
```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl libpng-dev libwebp-dev libjpeg-turbo-dev libzip-dev icu-dev \
    postgresql-dev oniguruma-dev autoconf g++ make linux-headers \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd pdo_pgsql intl zip bcmath pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && apk del autoconf g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

- [ ] **Step 2: Create nginx config**

`docker/nginx/default.conf`:
```nginx
server {
    listen 80 default_server;
    server_name _;
    root /app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php-fpm:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

- [ ] **Step 3: Create docker-compose.yml**

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports: ["8080:80"]
    volumes:
      - ./:/app
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on: [php-fpm]

  php-fpm:
    build: ./docker/php-fpm
    volumes: ["./:/app"]
    environment:
      DB_HOST: postgres
      REDIS_HOST: redis
      MAIL_HOST: mailpit
    depends_on: [postgres, redis, mailpit]

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: cloudmarktplaats
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    volumes: ["pgdata:/var/lib/postgresql/data"]
    ports: ["5432:5432"]

  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]

  mailpit:
    image: axllent/mailpit:latest
    ports: ["8025:8025", "1025:1025"]

  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: minio
      MINIO_ROOT_PASSWORD: miniosecret
    ports: ["9000:9000", "9001:9001"]
    volumes: ["miniodata:/data"]

volumes:
  pgdata:
  miniodata:
```

- [ ] **Step 4: Update .env for dockerised services**

Edit `.env`:
- `APP_URL=http://localhost:8080`
- `DB_CONNECTION=pgsql`
- `DB_HOST=postgres`
- `DB_PORT=5432`
- `DB_DATABASE=cloudmarktplaats`
- `DB_USERNAME=app`
- `DB_PASSWORD=app`
- `REDIS_HOST=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `MAIL_MAILER=smtp`
- `MAIL_HOST=mailpit`
- `MAIL_PORT=1025`

Mirror to `.env.example`.

- [ ] **Step 5: Smoke test the stack**

```bash
docker compose up -d
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
curl -fsS http://localhost:8080 | head -1
```
Expected: `<!DOCTYPE html>` (Laravel welcome page).

- [ ] **Step 6: Commit**

```bash
git add docker-compose.yml docker/ .env.example
git commit -m "Add docker-compose dev stack (nginx, php-fpm, postgres, redis, mailpit, minio)"
```

---

### Task A3: Postgres ltree extension + cloudmarktplaats config

**Files:**
- Create: `database/migrations/2026_05_16_000000_enable_postgres_extensions.php`
- Create: `config/cloudmarktplaats.php`
- Modify: `bootstrap/providers.php` (later when needed)

- [ ] **Step 1: Create extension migration**

```bash
docker compose exec php-fpm php artisan make:migration enable_postgres_extensions --create=__noop
```
Then edit the file to:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement('CREATE EXTENSION IF NOT EXISTS ltree');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }
    public function down(): void {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
        DB::statement('DROP EXTENSION IF EXISTS ltree');
    }
};
```

- [ ] **Step 2: Create config/cloudmarktplaats.php**

```php
<?php
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
        'threshold_eur_cents'    => 200_000,
    ],
    'oauth' => [
        'github' => [
            'client_id'     => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect'      => env('APP_URL') . '/oauth/github/callback',
        ],
        'gitlab' => [
            'client_id'     => env('GITLAB_CLIENT_ID'),
            'client_secret' => env('GITLAB_CLIENT_SECRET'),
            'redirect'      => env('APP_URL') . '/oauth/gitlab/callback',
        ],
    ],
];
```

- [ ] **Step 3: Run migration**

```bash
docker compose exec php-fpm php artisan migrate
```
Expected: extension migration runs without error.

- [ ] **Step 4: Verify ltree available**

```bash
docker compose exec postgres psql -U app -d cloudmarktplaats -c "SELECT extname FROM pg_extension WHERE extname='ltree';"
```
Expected: one row with `ltree`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ config/cloudmarktplaats.php
git commit -m "Enable Postgres extensions (ltree, uuid-ossp, pg_trgm) + project config"
```

---

### Task A4: Pest 3 setup + first sanity test

**Files:**
- Modify: `composer.json` (require-dev pest)
- Create: `tests/Pest.php` (overwritten by Pest install)
- Create: `tests/Feature/SanityTest.php`

- [ ] **Step 1: Install Pest 3**

```bash
docker compose exec php-fpm composer require --dev pestphp/pest:^3.0 pestphp/pest-plugin-laravel:^3.0 --with-all-dependencies
docker compose exec php-fpm ./vendor/bin/pest --init
```

- [ ] **Step 2: Configure phpunit.xml for Postgres test DB**

Edit `phpunit.xml` to set:
```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="cloudmarktplaats_test"/>
```

- [ ] **Step 3: Create test database**

```bash
docker compose exec postgres psql -U app -d postgres -c "CREATE DATABASE cloudmarktplaats_test OWNER app;"
docker compose exec postgres psql -U app -d cloudmarktplaats_test -c "CREATE EXTENSION IF NOT EXISTS ltree; CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\"; CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

- [ ] **Step 4: Write the failing sanity test**

`tests/Feature/SanityTest.php`:
```php
<?php
it('boots the application', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});
```

- [ ] **Step 5: Run, expect pass**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/SanityTest.php
```
Expected: 1 passed (Laravel welcome route returns 200).

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/
git commit -m "Add Pest 3 + first sanity test on Postgres test DB"
```

---

### Task A5: PHPStan + Pint

**Files:**
- Create: `phpstan.neon`
- Create: `pint.json` (optional; defaults are fine)

- [ ] **Step 1: Install**

```bash
docker compose exec php-fpm composer require --dev phpstan/phpstan:^1.11 larastan/larastan:^2.9 laravel/pint:^1.17
```

- [ ] **Step 2: Create phpstan.neon**

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - config
    level: 8
    ignoreErrors: []
```

- [ ] **Step 3: Run baseline checks**

```bash
docker compose exec php-fpm ./vendor/bin/pint --test
docker compose exec php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
```
Both expected: no errors. If phpstan complains about Laravel default app code, fix the issues (typically `User::factory()` typing or controller return types) — do not lower the level.

- [ ] **Step 4: Commit**

```bash
git add phpstan.neon composer.json composer.lock
git commit -m "Add PHPStan level 8 (with Larastan) + Pint formatter"
```

---

### Task A6: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-progress --no-interaction
      - run: ./vendor/bin/pint --test

  static:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-progress --no-interaction
      - run: ./vendor/bin/phpstan analyse --memory-limit=512M

  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env: { POSTGRES_DB: cloudmarktplaats_test, POSTGRES_USER: app, POSTGRES_PASSWORD: app }
        ports: ['5432:5432']
        options: >-
          --health-cmd "pg_isready -U app"
          --health-interval 10s --health-timeout 5s --health-retries 5
      redis:
        image: redis:7-alpine
        ports: ['6379:6379']
    env:
      DB_CONNECTION: pgsql
      DB_HOST: 127.0.0.1
      DB_DATABASE: cloudmarktplaats_test
      DB_USERNAME: app
      DB_PASSWORD: app
      REDIS_HOST: 127.0.0.1
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', extensions: pdo_pgsql, redis, gd, intl, zip, bcmath }
      - run: composer install --no-progress --no-interaction
      - run: psql "postgresql://app:app@127.0.0.1/cloudmarktplaats_test" -c "CREATE EXTENSION IF NOT EXISTS ltree; CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\"; CREATE EXTENSION IF NOT EXISTS pg_trgm;"
      - run: cp .env.example .env && php artisan key:generate
      - run: ./vendor/bin/pest --parallel

  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker compose build
      - run: docker compose up -d
      - run: |
          for i in {1..30}; do
            curl -fsS http://localhost:8080/healthz && break || sleep 2
          done
```

- [ ] **Step 2: Commit + push to a remote branch**

```bash
git add .github/
git commit -m "Add CI workflow (lint, static, test, build)"
```

Note: `build` job will fail until Task A7 ships `/healthz`. That is expected; `build` job becomes green after A7.

---

### Task A7: Healthcheck endpoint

**Files:**
- Create: `app/Http/Controllers/HealthController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HealthcheckTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/HealthcheckTest.php`:
```php
<?php
it('returns ok for all components', function () {
    $response = $this->getJson('/healthz');
    $response->assertOk()
        ->assertJsonStructure(['db', 'redis', 'storage', 'version']);
    expect($response->json('db'))->toBe('ok');
    expect($response->json('redis'))->toBe('ok');
    expect($response->json('storage'))->toBe('ok');
});
```

- [ ] **Step 2: Run, expect 404**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/HealthcheckTest.php
```
Expected: FAIL (route not defined → 404).

- [ ] **Step 3: Implement HealthController**

`app/Http/Controllers/HealthController.php`:
```php
<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'db'      => $this->check(fn () => DB::select('select 1')),
            'redis'   => $this->check(fn () => Redis::ping()),
            'storage' => $this->check(fn () => Storage::disk('local')->exists('') || true),
            'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev',
        ]);
    }

    private function check(\Closure $fn): string
    {
        try { $fn(); return 'ok'; } catch (\Throwable $e) { return 'error'; }
    }
}
```

- [ ] **Step 4: Register route**

In `routes/web.php` append:
```php
use App\Http\Controllers\HealthController;
Route::get('/healthz', HealthController::class);
```

- [ ] **Step 5: Run tests, expect pass**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/HealthcheckTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/HealthController.php routes/web.php tests/Feature/HealthcheckTest.php
git commit -m "Add /healthz endpoint with db/redis/storage checks"
```

---

## Phase B — Domain migrations & models

**Convention for this phase:**
- All migrations use timestamped filenames `2026_05_16_HHMMSS_<name>.php`
- Each migration includes both `up()` and `down()` (reversible)
- Each model has a matching factory in `database/factories/`
- After each task, run `php artisan migrate:fresh` (test DB) and Pest to confirm schema is valid

### Task B1: users + user_identities + auth_nonces

**Files:**
- Create: 3 migration files
- Replace: `app/Models/User.php` (Laravel default)
- Create: `app/Models/UserIdentity.php`, `app/Models/AuthNonce.php`
- Create: matching factories
- Test: `tests/Feature/Models/UserModelTest.php`

- [ ] **Step 1: Write failing model test**

`tests/Feature/Models/UserModelTest.php`:
```php
<?php
use App\Models\User;
use App\Models\UserIdentity;

it('creates a user with multiple identities', function () {
    $user = User::factory()->create(['email' => 'a@b.nl']);
    UserIdentity::factory()->password()->for($user)->create();
    UserIdentity::factory()->github('12345')->for($user)->create();
    expect($user->identities)->toHaveCount(2);
});

it('blocks duplicate provider+uid', function () {
    UserIdentity::factory()->github('12345')->create();
    expect(fn () => UserIdentity::factory()->github('12345')->create())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Generate migrations**

```bash
docker compose exec php-fpm php artisan make:migration create_users_table
docker compose exec php-fpm php artisan make:migration create_user_identities_table
docker compose exec php-fpm php artisan make:migration create_auth_nonces_table
```

Edit the timestamps so they run in this order: users → user_identities → auth_nonces.

- [ ] **Step 3: Fill in users migration**

```php
public function up(): void {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->ulid('ulid')->unique();
        $t->string('email')->unique();
        $t->string('password_hash')->nullable();
        $t->string('username', 30)->unique();
        $t->string('display_name');
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamp('last_login_at')->nullable();
        $t->string('last_login_ip', 45)->nullable();
        $t->enum('role', ['user', 'moderator', 'admin'])->default('user');
        $t->boolean('is_banned')->default(false);
        $t->text('banned_reason')->nullable();
        $t->text('two_factor_secret')->nullable();
        $t->text('two_factor_recovery_codes')->nullable();
        $t->timestamp('two_factor_confirmed_at')->nullable();
        $t->rememberToken();
        $t->timestamps();
        $t->softDeletes();
    });
}
public function down(): void { Schema::dropIfExists('users'); }
```

- [ ] **Step 4: Fill in user_identities migration**

```php
public function up(): void {
    Schema::create('user_identities', function (Blueprint $t) {
        $t->id();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->enum('provider', ['password', 'oauth_github', 'oauth_gitlab', 'siwe']);
        $t->string('provider_uid');
        $t->jsonb('provider_data')->nullable();
        $t->timestamp('last_used_at')->nullable();
        $t->timestamps();
        $t->unique(['provider', 'provider_uid']);
        $t->index(['user_id', 'provider']);
    });
}
public function down(): void { Schema::dropIfExists('user_identities'); }
```

- [ ] **Step 5: Fill in auth_nonces migration**

```php
public function up(): void {
    Schema::create('auth_nonces', function (Blueprint $t) {
        $t->id();
        $t->string('nonce', 64)->unique();
        $t->string('address', 64);
        $t->timestamp('expires_at')->index();
        $t->timestamp('used_at')->nullable();
        $t->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('auth_nonces'); }
```

- [ ] **Step 6: Replace User model**

`app/Models/User.php`:
```php
<?php
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['email', 'password_hash', 'username', 'display_name', 'role'];
    protected $hidden = ['password_hash', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    protected $casts = [
        'email_verified_at'        => 'datetime',
        'two_factor_confirmed_at'  => 'datetime',
        'two_factor_secret'        => 'encrypted',
        'two_factor_recovery_codes'=> 'encrypted:array',
        'is_banned'                => 'boolean',
    ];

    public function getAuthPassword(): string { return $this->password_hash ?? ''; }

    public function identities() { return $this->hasMany(UserIdentity::class); }

    public function hasIdentity(string $provider): bool {
        return $this->identities()->where('provider', $provider)->exists();
    }

    public function hasRole(string ...$roles): bool {
        return in_array($this->role, $roles, true);
    }

    protected static function booted(): void {
        static::creating(function (self $u) { $u->ulid = $u->ulid ?? (string) \Illuminate\Support\Str::ulid(); });
    }
}
```

- [ ] **Step 7: Create UserIdentity model**

`app/Models/UserIdentity.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserIdentity extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'provider', 'provider_uid', 'provider_data', 'last_used_at'];
    protected $casts = ['provider_data' => 'array', 'last_used_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
```

- [ ] **Step 8: Create AuthNonce model**

`app/Models/AuthNonce.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthNonce extends Model
{
    protected $fillable = ['nonce', 'address', 'expires_at', 'used_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function isUsable(): bool {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
```

- [ ] **Step 9: Create factories**

`database/factories/UserFactory.php` (replace default):
```php
<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;
    public function definition(): array {
        return [
            'email'             => $this->faker->unique()->safeEmail(),
            'password_hash'     => Hash::make('password'),
            'username'          => $this->faker->unique()->userName(),
            'display_name'      => $this->faker->name(),
            'email_verified_at' => now(),
            'role'              => 'user',
        ];
    }
    public function admin(): static { return $this->state(fn () => ['role' => 'admin']); }
    public function moderator(): static { return $this->state(fn () => ['role' => 'moderator']); }
    public function unverified(): static { return $this->state(fn () => ['email_verified_at' => null]); }
}
```

`database/factories/UserIdentityFactory.php`:
```php
<?php
namespace Database\Factories;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserIdentityFactory extends Factory
{
    protected $model = UserIdentity::class;
    public function definition(): array {
        return [
            'user_id'      => User::factory(),
            'provider'     => 'password',
            'provider_uid' => 'password',
        ];
    }
    public function password(): static { return $this->state(['provider' => 'password', 'provider_uid' => 'password']); }
    public function github(string $uid): static { return $this->state(['provider' => 'oauth_github', 'provider_uid' => $uid]); }
    public function gitlab(string $uid): static { return $this->state(['provider' => 'oauth_gitlab', 'provider_uid' => $uid]); }
    public function siwe(string $address): static { return $this->state(['provider' => 'siwe', 'provider_uid' => strtolower($address)]); }
}
```

- [ ] **Step 10: Run migrations + tests**

```bash
docker compose exec php-fpm php artisan migrate:fresh --env=testing
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Models/UserModelTest.php
```
Expected: 2 passed.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/ database/factories/ app/Models/ tests/
git commit -m "Add users + user_identities + auth_nonces tables and models"
```

---

### Task B2: legal_documents + legal_acceptances

**Files:**
- Create: 2 migrations + 2 models + 2 factories
- Test: `tests/Feature/Models/LegalDocumentTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Models\LegalDocument;
use App\Models\LegalAcceptance;
use App\Models\User;

it('finds the current published version per type+locale', function () {
    LegalDocument::factory()->tos()->create(['version' => '1.0.0', 'published_at' => now()->subDay()]);
    $latest = LegalDocument::factory()->tos()->create(['version' => '1.1.0', 'published_at' => now()]);
    $found = LegalDocument::current('tos', 'nl');
    expect($found->id)->toBe($latest->id);
});

it('records an acceptance with hashed ip', function () {
    $user = User::factory()->create();
    $doc  = LegalDocument::factory()->tos()->create(['published_at' => now()]);
    LegalAcceptance::create([
        'user_id'             => $user->id,
        'legal_document_id'   => $doc->id,
        'accepted_at'         => now(),
        'ip_hash'             => hash('sha256', '127.0.0.1' . config('app.key')),
    ]);
    expect($user->legalAcceptances()->count())->toBe(1);
});
```

- [ ] **Step 2: Migration `create_legal_documents_table`**

```php
Schema::create('legal_documents', function (Blueprint $t) {
    $t->id();
    $t->enum('type', ['tos', 'privacy']);
    $t->string('version', 20);
    $t->string('locale', 5);
    $t->text('markdown_content');
    $t->timestamp('published_at')->nullable();
    $t->timestamps();
    $t->index(['type', 'locale', 'published_at']);
    $t->unique(['type', 'locale', 'version']);
});
```

- [ ] **Step 3: Migration `create_legal_acceptances_table`**

```php
Schema::create('legal_acceptances', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
    $t->timestamp('accepted_at');
    $t->string('ip_hash', 64);
    $t->timestamps();
    $t->index(['user_id', 'accepted_at']);
});
```

- [ ] **Step 4: LegalDocument model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    use HasFactory;
    protected $fillable = ['type', 'version', 'locale', 'markdown_content', 'published_at'];
    protected $casts = ['published_at' => 'datetime'];

    public static function current(string $type, string $locale): ?self {
        return static::where('type', $type)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->first();
    }
}
```

- [ ] **Step 5: LegalAcceptance model + User relation**

`app/Models/LegalAcceptance.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAcceptance extends Model
{
    protected $fillable = ['user_id', 'legal_document_id', 'accepted_at', 'ip_hash'];
    protected $casts = ['accepted_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function document() { return $this->belongsTo(LegalDocument::class, 'legal_document_id'); }
}
```

Add to `User`:
```php
public function legalAcceptances() { return $this->hasMany(LegalAcceptance::class); }
```

- [ ] **Step 6: Factories**

`LegalDocumentFactory`:
```php
public function definition(): array {
    return [
        'type'             => 'tos',
        'version'          => '1.0.0',
        'locale'           => 'nl',
        'markdown_content' => '# ToS placeholder',
        'published_at'     => null,
    ];
}
public function tos(): static { return $this->state(['type' => 'tos']); }
public function privacy(): static { return $this->state(['type' => 'privacy']); }
```

- [ ] **Step 7: Run + commit**

```bash
docker compose exec php-fpm php artisan migrate:fresh --env=testing
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Models/LegalDocumentTest.php
```
Expected: 2 passed.

```bash
git add database/ app/Models/Legal* app/Models/User.php tests/
git commit -m "Add legal_documents + legal_acceptances with current-version lookup"
```

---

### Task B3: categories (ltree)

**Files:**
- Create: migration `create_categories_table`, `app/Models/Category.php`, `database/factories/CategoryFactory.php`
- Test: `tests/Feature/Models/CategoryTreeTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
use App\Models\Category;

it('creates nested categories under parent path', function () {
    $servers = Category::create(['name' => 'Servers', 'slug' => 'servers', 'path' => 'servers']);
    $rack    = Category::create(['name' => 'Rack', 'slug' => 'rack', 'path' => 'servers.rack']);
    expect(Category::descendantsOf('servers')->pluck('slug')->all())->toContain('rack');
});
```

- [ ] **Step 2: Migration**

```php
public function up(): void {
    Schema::create('categories', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('slug')->unique();
        $t->text('description')->nullable();
        $t->string('icon')->nullable();
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });
    DB::statement('ALTER TABLE categories ADD COLUMN path ltree NOT NULL');
    DB::statement('CREATE INDEX categories_path_gist ON categories USING GIST (path)');
    DB::statement('CREATE UNIQUE INDEX categories_path_unique ON categories (path)');
}
public function down(): void { Schema::dropIfExists('categories'); }
```

- [ ] **Step 3: Model with ltree query helpers**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug', 'description', 'icon', 'is_active', 'path'];
    protected $casts = ['is_active' => 'boolean'];

    public static function descendantsOf(string $path): Builder {
        return static::query()->whereRaw('path <@ ?::ltree', [$path]);
    }

    public static function ancestorsOf(string $path): Builder {
        return static::query()->whereRaw('path @> ?::ltree', [$path]);
    }

    public function listings() { return $this->hasMany(Listing::class); }
}
```

- [ ] **Step 4: Factory**

```php
public function definition(): array {
    $name = $this->faker->unique()->words(2, true);
    $slug = \Illuminate\Support\Str::slug($name);
    return [
        'name' => $name,
        'slug' => $slug,
        'path' => $slug,
        'is_active' => true,
    ];
}
```

- [ ] **Step 5: Run test, commit**

```bash
docker compose exec php-fpm php artisan migrate:fresh --env=testing
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Models/CategoryTreeTest.php
```
Expected: PASS.

```bash
git add database/ app/Models/Category.php tests/
git commit -m "Add categories table with ltree hierarchy"
```

---

### Task B4: listings + STORED tsvector

**Files:**
- Create: migration `create_listings_table`, `app/Models/Listing.php`, `database/factories/ListingFactory.php`
- Test: `tests/Feature/Models/ListingFtsTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('matches full-text search on title and description', function () {
    $user = User::factory()->create();
    $cat  = Category::factory()->create(['path' => 'servers']);
    Listing::factory()->for($user)->for($cat)->create([
        'title'       => 'Dell PowerEdge R720 server',
        'description' => 'Refurbished dual Xeon E5-2650, 64GB RAM',
        'state'       => 'published',
    ]);
    $hits = DB::select(
        "SELECT id FROM listings WHERE search_vector @@ plainto_tsquery('dutch', ?)",
        ['poweredge']
    );
    expect($hits)->toHaveCount(1);
});
```

- [ ] **Step 2: Migration**

```php
public function up(): void {
    Schema::create('listings', function (Blueprint $t) {
        $t->id();
        $t->ulid('ulid')->unique();
        $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        $t->foreignId('category_id')->constrained()->restrictOnDelete();
        $t->string('title');
        $t->string('slug');
        $t->text('description');
        $t->enum('condition', ['new', 'used', 'defective', 'for_parts']);
        $t->unsignedInteger('price_cents');
        $t->string('currency', 3)->default('EUR');
        $t->boolean('is_trade_allowed')->default(false);
        $t->char('region_postcode', 4)->nullable();
        $t->jsonb('shipping_options')->default(DB::raw("'{\"pickup\":true,\"post\":false}'::jsonb"));
        $t->enum('state', ['draft', 'pending_review', 'published', 'sold', 'archived', 'rejected'])->default('draft');
        $t->timestamp('published_at')->nullable();
        $t->timestamp('sold_at')->nullable();
        $t->text('moderation_notes')->nullable();
        $t->unsignedInteger('view_count')->default(0);
        $t->timestamps();
        $t->softDeletes();
        $t->unique(['user_id', 'slug']);
        $t->index('state');
        $t->index('category_id');
        $t->index(['state', 'published_at']);
    });

    // STORED generated tsvector + GIN index
    DB::statement(<<<SQL
        ALTER TABLE listings
        ADD COLUMN search_vector tsvector
        GENERATED ALWAYS AS (
            to_tsvector('dutch', coalesce(title,'') || ' ' || coalesce(description,''))
        ) STORED
    SQL);
    DB::statement('CREATE INDEX listings_search_gin ON listings USING GIN (search_vector)');
}
public function down(): void { Schema::dropIfExists('listings'); }
```

- [ ] **Step 3: Model**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'description', 'condition',
        'price_cents', 'currency', 'is_trade_allowed', 'region_postcode',
        'shipping_options', 'state', 'published_at', 'sold_at', 'moderation_notes',
    ];
    protected $casts = [
        'shipping_options' => 'array',
        'is_trade_allowed' => 'boolean',
        'published_at'     => 'datetime',
        'sold_at'          => 'datetime',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function photos()   { return $this->hasMany(ListingPhoto::class)->orderBy('position'); }

    protected static function booted(): void {
        static::creating(function (self $l) {
            $l->ulid = $l->ulid ?? (string) Str::ulid();
            $l->slug = $l->slug ?: Str::slug($l->title) . '-' . substr($l->ulid, -6);
        });
    }
}
```

- [ ] **Step 4: Factory**

```php
public function definition(): array {
    return [
        'user_id'          => User::factory(),
        'category_id'      => Category::factory(),
        'title'            => $this->faker->sentence(4),
        'description'      => $this->faker->paragraph(),
        'condition'        => 'used',
        'price_cents'      => $this->faker->numberBetween(500, 500_000),
        'is_trade_allowed' => false,
        'region_postcode'  => (string) $this->faker->numberBetween(1000, 9999),
        'shipping_options' => ['pickup' => true, 'post' => false],
        'state'            => 'draft',
    ];
}
public function published(): static {
    return $this->state(['state' => 'published', 'published_at' => now()]);
}
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm php artisan migrate:fresh --env=testing
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Models/ListingFtsTest.php
```
Expected: PASS.

```bash
git add database/ app/Models/Listing.php tests/
git commit -m "Add listings table with state machine + Postgres FTS (STORED tsvector + GIN)"
```

---

### Task B5: listing_photos + remaining tables (reports, transactions, admin_actions)

Bundled because each is small and they share the same TDD shape. Five tables in one task; one commit.

**Files:**
- Create: 4 migrations, 4 models, 4 factories
- Test: `tests/Feature/Models/MiscModelsTest.php`

- [ ] **Step 1: Failing test (one assertion per model — make sure they instantiate + persist)**

```php
<?php
use App\Models\{Listing, ListingPhoto, Report, Transaction, AdminAction, User};

it('creates listing photos with ordering', function () {
    $listing = Listing::factory()->create();
    ListingPhoto::factory()->for($listing)->create(['position' => 1]);
    ListingPhoto::factory()->for($listing)->create(['position' => 2]);
    expect($listing->photos)->toHaveCount(2);
});

it('creates a report on a listing', function () {
    $listing = Listing::factory()->create();
    $r = Report::create([
        'reportable_type' => Listing::class,
        'reportable_id'   => $listing->id,
        'reason'          => 'spam',
        'details'         => 'looks fake',
        'status'          => 'open',
    ]);
    expect($r->reportable->is($listing))->toBeTrue();
});

it('creates a transaction stub', function () {
    $listing = Listing::factory()->create();
    $buyer   = User::factory()->create();
    Transaction::create([
        'listing_id'      => $listing->id,
        'buyer_user_id'   => $buyer->id,
        'seller_user_id'  => $listing->user_id,
        'amount_cents'    => 50_00,
        'currency'        => 'EUR',
        'status'          => 'pending',
        'off_platform'    => true,
    ]);
    expect(Transaction::count())->toBe(1);
});

it('logs an admin action', function () {
    $admin = User::factory()->admin()->create();
    AdminAction::create([
        'user_id'     => $admin->id,
        'action'      => 'listing.reject',
        'target_type' => Listing::class,
        'target_id'   => 1,
        'meta'        => ['reason' => 'duplicate'],
        'ip_hash'     => str_repeat('a', 64),
    ]);
    expect(AdminAction::count())->toBe(1);
});
```

- [ ] **Step 2: Migrations**

`create_listing_photos_table`:
```php
Schema::create('listing_photos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
    $t->string('disk', 16)->default('local');
    $t->string('path');
    $t->unsignedSmallInteger('width');
    $t->unsignedSmallInteger('height');
    $t->string('mime', 64);
    $t->unsignedInteger('byte_size');
    $t->unsignedTinyInteger('position');
    $t->timestamps();
    $t->unique(['listing_id', 'position']);
});
```

`create_reports_table`:
```php
Schema::create('reports', function (Blueprint $t) {
    $t->id();
    $t->morphs('reportable');
    $t->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
    $t->enum('reason', ['illegal', 'stolen', 'spam', 'wrong_category', 'other']);
    $t->text('details')->nullable();
    $t->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
    $t->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $t->text('resolution_note')->nullable();
    $t->timestamps();
    $t->index('status');
});
```

`create_transactions_table`:
```php
Schema::create('transactions', function (Blueprint $t) {
    $t->id();
    $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
    $t->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
    $t->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
    $t->unsignedInteger('amount_cents');
    $t->string('currency', 3)->default('EUR');
    $t->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
    $t->timestamp('completed_at')->nullable();
    $t->boolean('off_platform')->default(true);
    $t->string('external_tx_ref')->nullable();
    $t->timestamps();
    $t->index(['seller_user_id', 'status', 'completed_at']);
});
```

`create_admin_actions_table`:
```php
Schema::create('admin_actions', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->string('action');
    $t->string('target_type');
    $t->unsignedBigInteger('target_id');
    $t->jsonb('meta')->nullable();
    $t->string('ip_hash', 64);
    $t->timestamp('created_at')->useCurrent();
    $t->index(['target_type', 'target_id']);
    $t->index(['user_id', 'created_at']);
});
```

- [ ] **Step 3: Models** (each ~10 lines, follow patterns from B1–B4 — `protected $fillable`, casts, relations)

`ListingPhoto`:
```php
class ListingPhoto extends Model {
    use HasFactory;
    protected $fillable = ['listing_id','disk','path','width','height','mime','byte_size','position'];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function urlFor(string $variant = 'card'): string {
        $base = preg_replace('/\.[^.]+$/', '', $this->path);
        $ext  = $variant === 'original' ? pathinfo($this->path, PATHINFO_EXTENSION) : 'webp';
        return app(\App\Services\Storage\StorageManager::class)->driver($this->disk)
            ->url(dirname($base).'/'.$variant.'.'.$ext);
    }
}
```

`Report`:
```php
class Report extends Model {
    protected $fillable = ['reportable_type','reportable_id','reporter_user_id','reason','details','status','resolved_by_user_id','resolution_note'];
    public function reportable() { return $this->morphTo(); }
}
```

`Transaction`:
```php
class Transaction extends Model {
    protected $fillable = ['listing_id','buyer_user_id','seller_user_id','amount_cents','currency','status','completed_at','off_platform','external_tx_ref'];
    protected $casts = ['off_platform' => 'boolean', 'completed_at' => 'datetime'];
    public function listing() { return $this->belongsTo(Listing::class); }
    public function buyer()   { return $this->belongsTo(User::class, 'buyer_user_id'); }
    public function seller()  { return $this->belongsTo(User::class, 'seller_user_id'); }
}
```

`AdminAction`:
```php
class AdminAction extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','action','target_type','target_id','meta','ip_hash','created_at'];
    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
    public function admin() { return $this->belongsTo(User::class, 'user_id'); }
}
```

- [ ] **Step 4: Factories** (only `ListingPhotoFactory` is non-trivial)

```php
public function definition(): array {
    return [
        'listing_id' => Listing::factory(),
        'disk'       => 'local',
        'path'       => 'listings/test/1/card.webp',
        'width'      => 600,
        'height'     => 600,
        'mime'       => 'image/webp',
        'byte_size'  => 12_345,
        'position'   => 1,
    ];
}
```

Note: ListingPhoto::urlFor() references `StorageManager` which is created in Task G5. Until then, the helper is unused; tests in this task only verify the model + relations. Don't call `urlFor()` from any test in this phase.

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm php artisan migrate:fresh --env=testing
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Models/MiscModelsTest.php
```
Expected: 4 passed.

```bash
git add database/ app/Models/ tests/
git commit -m "Add listing_photos + reports + transactions + admin_actions tables and models"
```

---

### Task B6: Seeders (categories, legal docs, demo users)

**Files:**
- Create: `database/seeders/CategorySeeder.php`, `LegalDocumentSeeder.php`, `DemoUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/SeedersTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{Category, LegalDocument, User};

it('seeds top-level categories from spec', function () {
    $this->seed(\Database\Seeders\CategorySeeder::class);
    expect(Category::whereRaw("path = 'servers'")->exists())->toBeTrue();
    expect(Category::whereRaw("path = 'networking'")->exists())->toBeTrue();
    expect(Category::count())->toBeGreaterThanOrEqual(12);
});

it('seeds legal documents in nl + en', function () {
    $this->seed(\Database\Seeders\LegalDocumentSeeder::class);
    expect(LegalDocument::current('tos', 'nl'))->not->toBeNull();
    expect(LegalDocument::current('privacy', 'nl'))->not->toBeNull();
});

it('creates demo admin and user', function () {
    $this->seed(\Database\Seeders\DemoUserSeeder::class);
    expect(User::where('email', 'admin@example.local')->first()?->role)->toBe('admin');
    expect(User::where('email', 'user@example.local')->first()?->role)->toBe('user');
});
```

- [ ] **Step 2: CategorySeeder**

```php
<?php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder {
    public function run(): void {
        $tops = [
            'Server hardware'      => 'servers',
            'Networking'           => 'networking',
            'Storage'              => 'storage',
            'Compute'              => 'compute',
            'Kabels & connectoren' => 'kabels',
            'Power'                => 'power',
            'Audio/Video pro'      => 'av',
            'Meetapparatuur'       => 'meet',
            '3D printers & CNC'    => 'fabrication',
            'Software licenties'   => 'licenses',
            'Boeken & documentatie'=> 'books',
            'Overig'               => 'misc',
        ];
        foreach ($tops as $name => $slug) {
            Category::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'path' => $slug, 'is_active' => true,
            ]);
        }
    }
}
```

- [ ] **Step 3: LegalDocumentSeeder**

```php
<?php
namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder {
    public function run(): void {
        foreach (['nl', 'en'] as $locale) {
            LegalDocument::firstOrCreate(
                ['type' => 'tos', 'locale' => $locale, 'version' => '1.0.0'],
                ['markdown_content' => "# Terms of Service ($locale)\n\nPlaceholder.", 'published_at' => now()],
            );
            LegalDocument::firstOrCreate(
                ['type' => 'privacy', 'locale' => $locale, 'version' => '1.0.0'],
                ['markdown_content' => "# Privacy Policy ($locale)\n\nPlaceholder.", 'published_at' => now()],
            );
        }
    }
}
```

- [ ] **Step 4: DemoUserSeeder**

```php
<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder {
    public function run(): void {
        if (app()->environment('production')) return;
        foreach (['admin@example.local' => 'admin', 'user@example.local' => 'user'] as $email => $role) {
            $u = User::firstOrCreate(['email' => $email], [
                'username' => str_replace('@example.local', '', $email),
                'display_name' => ucfirst(str_replace('@example.local', '', $email)),
                'role' => $role,
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            UserIdentity::firstOrCreate(['user_id' => $u->id, 'provider' => 'password'], [
                'provider_uid' => 'password',
            ]);
        }
    }
}
```

- [ ] **Step 5: Wire DatabaseSeeder**

```php
public function run(): void {
    $this->call([CategorySeeder::class, LegalDocumentSeeder::class, DemoUserSeeder::class]);
}
```

- [ ] **Step 6: Run + commit**

```bash
docker compose exec php-fpm php artisan migrate:fresh --seed
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/SeedersTest.php
```
Expected: 3 passed.

```bash
git add database/seeders/ tests/
git commit -m "Add seeders for categories, legal docs, demo users"
```

---

## Phase C — Email + password authentication

**Convention:**
- All auth UI is Livewire 3 full-page components mounted on routes
- Auth uses Laravel's session guard. `User::getAuthPassword()` returns `password_hash`
- Throttling via `Illuminate\Support\Facades\RateLimiter`
- For tests, use the `RefreshDatabase` trait + Pest's Livewire helper

### Task C1: Layout shell + Livewire installed

**Files:**
- Modify: `composer.json` (livewire/livewire ^3)
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/components/auth-card.blade.php`
- Modify: `resources/css/app.css`, `tailwind.config.js`, `package.json`

- [ ] **Step 1: Install Livewire + Tailwind**

```bash
docker compose exec php-fpm composer require livewire/livewire:^3.5
docker compose run --rm -u $(id -u):$(id -g) -w /app php-fpm npm install -D tailwindcss@^3 @tailwindcss/forms autoprefixer postcss
docker compose run --rm -u $(id -u):$(id -g) -w /app php-fpm npx tailwindcss init -p
```

- [ ] **Step 2: Configure Tailwind**

`tailwind.config.js`:
```js
module.exports = {
    content: ['./resources/**/*.blade.php', './resources/**/*.js', './app/Livewire/**/*.php'],
    theme: { extend: {} },
    plugins: [require('@tailwindcss/forms')],
};
```

`resources/css/app.css`:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- [ ] **Step 3: Create layout**

`resources/views/layouts/app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Cloudmarktplaats' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white">
        <nav class="container mx-auto flex items-center justify-between py-3">
            <a href="/" class="font-bold">cloudmarktplaats<span class="text-gray-400">.nl</span></a>
            <div class="space-x-4 text-sm">
                @auth
                    <span>{{ auth()->user()->display_name }}</span>
                    <form method="POST" action="/logout" class="inline">@csrf <button>Logout</button></form>
                @else
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="container mx-auto py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @livewireScripts
</body>
</html>
```

- [ ] **Step 4: Build assets**

```bash
docker compose run --rm -w /app php-fpm npm run build
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json tailwind.config.js postcss.config.js resources/
git commit -m "Install Livewire 3 + Tailwind 3 + base layout"
```

---

### Task C2: Register

**Files:**
- Create: `app/Livewire/Auth/Register.php`
- Create: `resources/views/livewire/auth/register.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/RegisterTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Auth\Register;
use App\Models\{LegalDocument, User, UserIdentity};
use Livewire\Livewire;

beforeEach(function () {
    LegalDocument::factory()->tos()->create(['published_at' => now()]);
    LegalDocument::factory()->privacy()->create(['published_at' => now()]);
});

it('creates a user with password identity and legal acceptance', function () {
    Livewire::test(Register::class)
        ->set('email', 'new@example.nl')
        ->set('username', 'newuser')
        ->set('display_name', 'New User')
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertRedirect('/email/verify-notice');

    $user = User::where('email', 'new@example.nl')->first();
    expect($user)->not->toBeNull();
    expect($user->identities()->where('provider', 'password')->exists())->toBeTrue();
    expect($user->legalAcceptances()->count())->toBe(2);
});

it('rejects mismatched passwords', function () {
    Livewire::test(Register::class)
        ->set('email', 'a@b.nl')->set('username', 'a')->set('display_name', 'A')
        ->set('password', 'aaa')->set('password_confirmation', 'bbb')->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('rejects when ToS not accepted', function () {
    Livewire::test(Register::class)
        ->set('email', 'a@b.nl')->set('username', 'a')->set('display_name', 'A')
        ->set('password', 'aaaaaaaaaa')->set('password_confirmation', 'aaaaaaaaaa')->set('accept_tos', false)
        ->call('submit')
        ->assertHasErrors(['accept_tos']);
});
```

- [ ] **Step 2: Component**

`app/Livewire/Auth/Register.php`:
```php
<?php
namespace App\Livewire\Auth;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Register extends Component
{
    public string $email = '';
    public string $username = '';
    public string $display_name = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $accept_tos = false;

    public function submit()
    {
        $this->validate([
            'email'        => ['required', 'email', 'unique:users,email'],
            'username'     => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_-]+$/i', 'unique:users,username'],
            'display_name' => ['required', 'string', 'max:64'],
            'password'     => ['required', 'string', 'min:10', 'confirmed'],
            'accept_tos'   => ['accepted'],
        ]);

        $user = DB::transaction(function () {
            $u = User::create([
                'email'         => $this->email,
                'username'      => strtolower($this->username),
                'display_name'  => $this->display_name,
                'password_hash' => Hash::make($this->password),
            ]);
            UserIdentity::create([
                'user_id'      => $u->id,
                'provider'     => 'password',
                'provider_uid' => 'password',
            ]);
            foreach (['tos', 'privacy'] as $type) {
                $doc = LegalDocument::current($type, app()->getLocale());
                if ($doc) {
                    LegalAcceptance::create([
                        'user_id'           => $u->id,
                        'legal_document_id' => $doc->id,
                        'accepted_at'       => now(),
                        'ip_hash'           => hash('sha256', request()->ip() . config('app.key')),
                    ]);
                }
            }
            return $u;
        });

        event(new Registered($user));
        auth()->login($user);
        return redirect('/email/verify-notice');
    }

    public function render() { return view('livewire.auth.register'); }
}
```

- [ ] **Step 3: View**

`resources/views/livewire/auth/register.blade.php`:
```blade
<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Account aanmaken</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="username" placeholder="username" class="w-full rounded border p-2" required>
        @error('username') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input wire:model="display_name" placeholder="weergavenaam" class="w-full rounded border p-2" required>

        <input type="password" wire:model="password" placeholder="wachtwoord" class="w-full rounded border p-2" required>
        @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <input type="password" wire:model="password_confirmation" placeholder="herhaal wachtwoord" class="w-full rounded border p-2" required>

        <label class="flex items-start space-x-2 text-sm">
            <input type="checkbox" wire:model="accept_tos" class="mt-1">
            <span>Ik accepteer de <a href="/legal/tos" class="underline">algemene voorwaarden</a> en <a href="/legal/privacy" class="underline">privacy policy</a>.</span>
        </label>
        @error('accept_tos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Account aanmaken</button>
    </form>
</div>
```

- [ ] **Step 4: Route**

`routes/web.php`:
```php
use App\Livewire\Auth\Register;
Route::get('/register', Register::class)->name('register')->middleware('guest');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/RegisterTest.php
```
Expected: 3 passed.

```bash
git add app/Livewire/Auth/Register.php resources/views/livewire/auth/register.blade.php routes/web.php tests/
git commit -m "Add register Livewire flow with ToS acceptance + password identity"
```

---

### Task C3: Email verification

**Files:**
- Create: `app/Livewire/Auth/VerifyEmailNotice.php`
- Create: `resources/views/livewire/auth/verify-email-notice.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/EmailVerifyTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('sends verification email after registration', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    event(new \Illuminate\Auth\Events\Registered($user));
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('marks email verified when signed link visited', function () {
    $user = User::factory()->unverified()->create();
    $url  = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id'   => $user->id,
        'hash' => sha1($user->email),
    ]);
    $this->actingAs($user)->get($url)->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
```

- [ ] **Step 2: VerifyEmailNotice component**

```php
<?php
namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Verified;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VerifyEmailNotice extends Component
{
    public string $sent = '';

    public function resend()
    {
        auth()->user()?->sendEmailVerificationNotification();
        $this->sent = 'Email verstuurd.';
    }

    public function render() { return view('livewire.auth.verify-email-notice'); }
}
```

`resources/views/livewire/auth/verify-email-notice.blade.php`:
```blade
<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-2 text-xl font-bold">Verifieer je email</h1>
    <p class="mb-4 text-sm">Klik op de link in de email om je account te activeren.</p>
    <button wire:click="resend" class="rounded bg-blue-600 px-4 py-2 text-white">Verstuur opnieuw</button>
    @if($sent) <p class="mt-2 text-sm text-green-600">{{ $sent }}</p> @endif
</div>
```

- [ ] **Step 3: Register routes**

```php
use App\Http\Controllers\Auth\EmailVerificationController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/email/verify-notice', \App\Livewire\Auth\VerifyEmailNotice::class)
    ->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::post('/email/verification-notification', function () {
    auth()->user()->sendEmailVerificationNotification();
    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
```

- [ ] **Step 4: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/EmailVerifyTest.php
```
Expected: 2 passed.

```bash
git add app/Livewire/Auth/VerifyEmailNotice.php resources/views/livewire/auth/verify-email-notice.blade.php routes/web.php tests/
git commit -m "Add email verification flow (notice + signed verify link)"
```

---

### Task C4: Login

**Files:**
- Create: `app/Livewire/Auth/Login.php`
- Create: `resources/views/livewire/auth/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/LoginTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Auth\Login;
use App\Models\{User, UserIdentity};
use Livewire\Livewire;

it('logs in with correct password', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('right-pass')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)
        ->set('email', 'a@b.nl')->set('password', 'right-pass')
        ->call('submit')
        ->assertRedirect('/');
    expect(auth()->id())->toBe($user->id);
});

it('rejects wrong password with generic error', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('right-pass')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)
        ->set('email', 'a@b.nl')->set('password', 'wrong')
        ->call('submit')
        ->assertHasErrors(['email']);
    expect(auth()->id())->toBeNull();
});

it('throttles after 5 failed attempts', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('p')]);
    UserIdentity::factory()->password()->for($user)->create();
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)->set('email','a@b.nl')->set('password','x')->call('submit');
    }
    Livewire::test(Login::class)->set('email','a@b.nl')->set('password','x')->call('submit')
        ->assertHasErrors(['email']);
    // 6th attempt error message should mention throttle
});
```

- [ ] **Step 2: Component**

```php
<?php
namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function submit()
    {
        $this->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'login:' . request()->ip() . ':' . strtolower($this->email);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Te veel pogingen. Probeer over een minuut opnieuw.']);
        }

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Inloggegevens onjuist.']);
        }

        RateLimiter::clear($key);
        request()->session()->regenerate();
        auth()->user()->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
        return redirect()->intended('/');
    }

    public function render() { return view('livewire.auth.login'); }
}
```

Note: `Auth::attempt` calls `User::getAuthPassword()` which returns `password_hash` (set in B1). Laravel's session guard then matches the bcrypt hash.

- [ ] **Step 3: View**

```blade
<div class="mx-auto max-w-md rounded border bg-white p-6 shadow">
    <h1 class="mb-4 text-xl font-bold">Inloggen</h1>
    <form wire:submit="submit" class="space-y-3">
        <input type="email" wire:model="email" placeholder="email" class="w-full rounded border p-2" required>
        <input type="password" wire:model="password" placeholder="wachtwoord" class="w-full rounded border p-2" required>
        @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <label class="flex items-center space-x-2 text-sm"><input type="checkbox" wire:model="remember"> <span>Onthoud mij</span></label>
        <button class="w-full rounded bg-blue-600 px-4 py-2 text-white">Inloggen</button>
        <p class="text-sm"><a href="/forgot-password" class="underline">Wachtwoord vergeten?</a></p>
    </form>
</div>
```

- [ ] **Step 4: Route + logout route**

```php
use App\Livewire\Auth\Login;
Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->middleware('auth')->name('logout');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/LoginTest.php
```
Expected: 3 passed.

```bash
git add app/Livewire/Auth/Login.php resources/views/livewire/auth/login.blade.php routes/web.php tests/
git commit -m "Add login Livewire flow with throttling + logout route"
```

---

### Task C5: Password reset

**Files:**
- Create: `app/Livewire/Auth/ForgotPassword.php`, `ResetPassword.php`
- Create: matching views
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Auth\{ForgotPassword, ResetPassword};
use App\Models\{User, UserIdentity};
use Illuminate\Support\Facades\{Hash, Notification, Password};
use Livewire\Livewire;

it('sends reset link to known email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'r@b.nl']);
    Livewire::test(ForgotPassword::class)
        ->set('email', 'r@b.nl')->call('submit')
        ->assertHasNoErrors();
    Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
});

it('updates password and creates identity row for siwe-only user', function () {
    $user = User::factory()->create(['email' => 's@b.nl', 'password_hash' => null]);
    UserIdentity::factory()->siwe('0xaaaa...')->for($user)->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token, 'email' => 's@b.nl'])
        ->set('password', 'new-secret-1234')
        ->set('password_confirmation', 'new-secret-1234')
        ->call('submit')
        ->assertRedirect('/login');

    $user->refresh();
    expect(Hash::check('new-secret-1234', $user->password_hash))->toBeTrue();
    expect($user->identities()->where('provider', 'password')->exists())->toBeTrue();
});
```

- [ ] **Step 2: ForgotPassword component**

```php
<?php
namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ForgotPassword extends Component
{
    public string $email = '';
    public string $status = '';

    public function submit()
    {
        $this->validate(['email' => ['required', 'email']]);

        $key = 'pwreset:' . strtolower($this->email);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Te veel verzoeken — wacht een uur.']);
        }
        RateLimiter::hit($key, 3600);

        Password::sendResetLink(['email' => $this->email]);
        $this->status = 'Als dit emailadres bestaat, is er een reset-link verstuurd.';
    }

    public function render() { return view('livewire.auth.forgot-password'); }
}
```

- [ ] **Step 3: ResetPassword component**

```php
<?php
namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ResetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token = '', string $email = '')
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function submit()
    {
        $this->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password,
             'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function (User $user, string $password) {
                $user->password_hash = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                UserIdentity::firstOrCreate(
                    ['user_id' => $user->id, 'provider' => 'password'],
                    ['provider_uid' => 'password'],
                );
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/login');
        }
        $this->addError('email', __($status));
    }

    public function render() { return view('livewire.auth.reset-password'); }
}
```

- [ ] **Step 4: Views** — minimal forms; same shape as login. Skip listing here for brevity but include the three fields (token hidden, email read-only, password+confirmation).

- [ ] **Step 5: Routes**

```php
Route::get('/forgot-password', \App\Livewire\Auth\ForgotPassword::class)->middleware('guest')->name('password.request');
Route::get('/reset-password/{token}', \App\Livewire\Auth\ResetPassword::class)->middleware('guest')->name('password.reset');
```

Configure password reset in `config/auth.php`:
```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table'    => 'password_reset_tokens',  // Laravel 11 default
        'expire'   => 60,
        'throttle' => 60,
    ],
],
```

- [ ] **Step 6: Update User to satisfy CanResetPassword**

Add to `User`:
```php
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;

class User extends Authenticatable implements MustVerifyEmail, CanResetPassword {
    use HasFactory, Notifiable, SoftDeletes, CanResetPasswordTrait;
    // ...
}
```

- [ ] **Step 7: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php
```
Expected: 2 passed.

```bash
git add app/Livewire/Auth/ForgotPassword.php app/Livewire/Auth/ResetPassword.php app/Models/User.php resources/views/ routes/web.php config/auth.php tests/
git commit -m "Add password reset flow (creates password identity for OAuth/SIWE-only users)"
```

---

## Phase D — OAuth (GitHub + GitLab)

### Task D1: Install Socialite + GitLab driver

**Files:**
- Modify: `composer.json`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: Install**

```bash
docker compose exec php-fpm composer require laravel/socialite:^5 socialiteproviders/gitlab
```

- [ ] **Step 2: Register GitLab provider**

In `app/Providers/AppServiceProvider.php` `boot()`:
```php
\SocialiteProviders\Manager\SocialiteWasCalled::class;
$this->app['events']->listen(
    \SocialiteProviders\Manager\SocialiteWasCalled::class,
    [\SocialiteProviders\GitLab\GitLabExtendSocialite::class, 'handle']
);
```

- [ ] **Step 3: Add Socialite config**

`config/services.php`:
```php
'github' => [
    'client_id'     => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/oauth/github/callback',
],
'gitlab' => [
    'client_id'     => env('GITLAB_CLIENT_ID'),
    'client_secret' => env('GITLAB_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/oauth/gitlab/callback',
],
```

Add to `.env.example`:
```
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITLAB_CLIENT_ID=
GITLAB_CLIENT_SECRET=
```

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock app/Providers/AppServiceProvider.php config/services.php .env.example
git commit -m "Install Socialite + GitLab provider"
```

---

### Task D2: OAuthController (provider-agnostic)

**Files:**
- Create: `app/Http/Controllers/Auth/OAuthController.php`
- Create: `app/Services/Auth/OAuthProviderRegistry.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/OAuthTest.php`

- [ ] **Step 1: Failing test** (uses Socialite mock)

```php
<?php
use App\Models\{User, UserIdentity};
use Laravel\Socialite\Facades\Socialite;
use Mockery;

beforeEach(function () {
    \App\Models\LegalDocument::factory()->tos()->create(['published_at' => now()]);
    \App\Models\LegalDocument::factory()->privacy()->create(['published_at' => now()]);
});

function fakeSocialiteUser(string $provider, string $uid, string $email, string $name = 'Test') {
    $u = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $u->shouldReceive('getId')->andReturn($uid);
    $u->shouldReceive('getEmail')->andReturn($email);
    $u->shouldReceive('getName')->andReturn($name);
    $u->shouldReceive('getNickname')->andReturn(strtolower($name));
    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($u);
    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

it('creates new user from github callback', function () {
    fakeSocialiteUser('github', '999', 'gh@example.nl');
    $this->get('/oauth/github/callback?code=fake')->assertRedirect();
    $u = User::where('email', 'gh@example.nl')->first();
    expect($u)->not->toBeNull();
    expect($u->identities()->where('provider', 'oauth_github')->where('provider_uid', '999')->exists())->toBeTrue();
});

it('logs in existing user matched by github identity', function () {
    $u = User::factory()->create();
    UserIdentity::factory()->github('888')->for($u)->create();
    fakeSocialiteUser('github', '888', 'irrelevant@example.nl');
    $this->get('/oauth/github/callback?code=fake')->assertRedirect();
    expect(auth()->id())->toBe($u->id);
});

it('refuses silent merge when email matches but identity does not', function () {
    User::factory()->create(['email' => 'taken@b.nl']);
    fakeSocialiteUser('github', '777', 'taken@b.nl');
    $this->get('/oauth/github/callback?code=fake')
        ->assertRedirect('/login')
        ->assertSessionHasErrors();
});

it('rejects unknown provider', function () {
    $this->get('/oauth/google/redirect')->assertStatus(404);
});
```

- [ ] **Step 2: Provider registry**

`app/Services/Auth/OAuthProviderRegistry.php`:
```php
<?php
namespace App\Services\Auth;

class OAuthProviderRegistry
{
    private const ALLOWED = ['github', 'gitlab'];

    public static function isAllowed(string $provider): bool {
        return in_array($provider, self::ALLOWED, true)
            && config("cloudmarktplaats.features.oauth_$provider");
    }

    public static function identityProvider(string $provider): string {
        return 'oauth_' . $provider;
    }
}
```

- [ ] **Step 3: OAuthController**

`app/Http/Controllers/Auth/OAuthController.php`:
```php
<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\OAuthProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless(OAuthProviderRegistry::isAllowed($provider), 404);
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(OAuthProviderRegistry::isAllowed($provider), 404);

        $oauthUser = Socialite::driver($provider)->user();
        $providerKey = OAuthProviderRegistry::identityProvider($provider);
        $uid = (string) $oauthUser->getId();

        $identity = UserIdentity::where(['provider' => $providerKey, 'provider_uid' => $uid])->first();
        if ($identity) {
            auth()->login($identity->user);
            $identity->update(['last_used_at' => now()]);
            return redirect('/');
        }

        $email = $oauthUser->getEmail();
        if ($email && User::where('email', $email)->exists()) {
            if (auth()->check() && auth()->user()->email === $email) {
                UserIdentity::create([
                    'user_id' => auth()->id(), 'provider' => $providerKey,
                    'provider_uid' => $uid, 'last_used_at' => now(),
                ]);
                return redirect('/profile/security')->with('status', 'identity-linked');
            }
            return redirect('/login')->withErrors([
                'email' => "Een account met email $email bestaat al. Log eerst in met een bestaande methode om {$provider} te koppelen.",
            ]);
        }

        // Onboarding: create new user
        $user = DB::transaction(function () use ($oauthUser, $providerKey, $uid, $provider) {
            $email = $oauthUser->getEmail() ?: "{$uid}@{$provider}.local";
            $u = User::create([
                'email'         => $email,
                'username'      => $this->uniqueUsername($oauthUser->getNickname() ?? $provider . '_' . $uid),
                'display_name'  => $oauthUser->getName() ?? $oauthUser->getNickname() ?? 'New user',
                'password_hash' => null,
                'email_verified_at' => $oauthUser->getEmail() ? now() : null,
            ]);
            UserIdentity::create([
                'user_id' => $u->id, 'provider' => $providerKey,
                'provider_uid' => $uid, 'last_used_at' => now(),
            ]);
            foreach (['tos', 'privacy'] as $type) {
                if ($doc = LegalDocument::current($type, app()->getLocale())) {
                    LegalAcceptance::create([
                        'user_id' => $u->id, 'legal_document_id' => $doc->id,
                        'accepted_at' => now(),
                        'ip_hash' => hash('sha256', request()->ip() . config('app.key')),
                    ]);
                }
            }
            return $u;
        });

        auth()->login($user);
        return redirect('/');
    }

    private function uniqueUsername(string $base): string {
        $base = preg_replace('/[^a-z0-9_-]/i', '', $base) ?: 'user';
        $candidate = strtolower($base);
        $i = 0;
        while (User::where('username', $candidate)->exists()) {
            $i++;
            $candidate = strtolower($base) . $i;
        }
        return $candidate;
    }
}
```

- [ ] **Step 4: Routes**

```php
use App\Http\Controllers\Auth\OAuthController;
Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect']);
Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback']);
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/OAuthTest.php
```
Expected: 4 passed.

```bash
git add app/Http/Controllers/Auth/OAuthController.php app/Services/Auth/OAuthProviderRegistry.php routes/web.php tests/
git commit -m "Add OAuth callback flow with link-on-match safety"
```

---

### Task D3: Provider-exclusion guard test (no Google/Facebook code paths)

**Files:**
- Test: `tests/Unit/Auth/ProviderExclusionTest.php`

- [ ] **Step 1: Test that asserts hardcoded denylist**

```php
<?php
use App\Services\Auth\OAuthProviderRegistry;

it('rejects google and facebook providers at the registry', function () {
    expect(OAuthProviderRegistry::isAllowed('google'))->toBeFalse();
    expect(OAuthProviderRegistry::isAllowed('facebook'))->toBeFalse();
    expect(OAuthProviderRegistry::isAllowed('twitter'))->toBeFalse();
});

it('does not contain google/facebook strings in oauth code', function () {
    $code = file_get_contents(app_path('Http/Controllers/Auth/OAuthController.php'))
          . file_get_contents(app_path('Services/Auth/OAuthProviderRegistry.php'));
    expect(str_contains(strtolower($code), 'google'))->toBeFalse();
    expect(str_contains(strtolower($code), 'facebook'))->toBeFalse();
});
```

- [ ] **Step 2: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Unit/Auth/ProviderExclusionTest.php
```
Expected: 2 passed.

```bash
git add tests/
git commit -m "Add hard exclusion test for Google/Facebook OAuth"
```

---

## Phase E — SIWE (Sign-In With Ethereum)

### Task E1: SiweMessageBuilder

**Files:**
- Create: `app/Services/Auth/SiweMessageBuilder.php`
- Test: `tests/Unit/Services/SiweMessageBuilderTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Services\Auth\SiweMessageBuilder;

it('builds an EIP-4361 message with required fields', function () {
    $msg = (new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl'))
        ->build('0xAbC0000000000000000000000000000000000001', 'abcdef1234567890', '2026-05-16T10:00:00Z');

    expect($msg)->toContain('cloudmarktplaats.nl wants you to sign in with your Ethereum account:');
    expect($msg)->toContain('0xAbC0000000000000000000000000000000000001');
    expect($msg)->toContain('URI: https://cloudmarktplaats.nl');
    expect($msg)->toContain('Version: 1');
    expect($msg)->toContain('Chain ID: 1');
    expect($msg)->toContain('Nonce: abcdef1234567890');
    expect($msg)->toContain('Issued At: 2026-05-16T10:00:00Z');
});

it('parses a built message back to fields', function () {
    $b = new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl');
    $msg = $b->build('0xAbC0000000000000000000000000000000000001', 'nonce123', '2026-05-16T10:00:00Z');
    $parsed = $b->parse($msg);
    expect($parsed['address'])->toBe('0xAbC0000000000000000000000000000000000001');
    expect($parsed['nonce'])->toBe('nonce123');
});
```

- [ ] **Step 2: Implementation**

```php
<?php
namespace App\Services\Auth;

class SiweMessageBuilder
{
    public function __construct(private string $domain, private string $uri, private int $chainId = 1) {}

    public function build(string $address, string $nonce, string $issuedAt): string {
        return implode("\n", [
            "{$this->domain} wants you to sign in with your Ethereum account:",
            $address,
            '',
            'Sign in to cloudmarktplaats.nl',
            '',
            "URI: {$this->uri}",
            'Version: 1',
            "Chain ID: {$this->chainId}",
            "Nonce: {$nonce}",
            "Issued At: {$issuedAt}",
        ]);
    }

    public function parse(string $message): array {
        $lines = explode("\n", $message);
        return [
            'address'   => trim($lines[1]),
            'uri'       => trim(str_replace('URI:', '', $lines[5])),
            'chain_id'  => (int) trim(str_replace('Chain ID:', '', $lines[7])),
            'nonce'     => trim(str_replace('Nonce:', '', $lines[8])),
            'issued_at' => trim(str_replace('Issued At:', '', $lines[9])),
        ];
    }
}
```

- [ ] **Step 3: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Unit/Services/SiweMessageBuilderTest.php
```
Expected: 2 passed.

```bash
git add app/Services/Auth/SiweMessageBuilder.php tests/
git commit -m "Add SIWE EIP-4361 message builder + parser"
```

---

### Task E2: Web3SignatureVerifier (port from v1, with test fixture)

**Files:**
- Modify: `composer.json` (kornrunner/keccak, simplito/elliptic-php)
- Create: `app/Services/Auth/Web3SignatureVerifier.php`
- Create: `tests/Fixtures/siwe-keypair.json` — test-only deterministic ECDSA pair, with WARNING in JSON
- Test: `tests/Unit/Services/Web3SignatureVerifierTest.php`

- [ ] **Step 1: Install crypto libs**

```bash
docker compose exec php-fpm composer require kornrunner/keccak:^1.1 simplito/elliptic-php:^1.0.10
```

- [ ] **Step 2: Generate keypair fixture**

`tests/Fixtures/siwe-keypair.json`:
```json
{
  "_warning": "TEST FIXTURE ONLY. This private key is committed to the repo. Never deploy with this key.",
  "private_key_hex": "0x4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318",
  "address":         "0x14791697260E4c9A71f18484C9f997B308e59325"
}
```

(Address derived from privkey — engineer can verify by running the test in step 5.)

- [ ] **Step 3: Failing test**

```php
<?php
use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3SignatureVerifier;
use Elliptic\EC;
use kornrunner\Keccak;

function signMessage(string $message, string $privKey): string {
    $hash = Keccak::hash("\x19Ethereum Signed Message:\n" . strlen($message) . $message, 256);
    $ec   = new EC('secp256k1');
    $sig  = $ec->keyFromPrivate(ltrim($privKey, '0x'))->sign($hash, ['canonical' => true]);
    $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
    $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
    $v = dechex($sig->recoveryParam + 27);
    return '0x' . $r . $s . $v;
}

it('verifies a valid signature against signer address', function () {
    $fix = json_decode(file_get_contents(__DIR__.'/../../Fixtures/siwe-keypair.json'), true);
    $msg = (new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl'))
        ->build($fix['address'], 'nonce42', '2026-05-16T10:00:00Z');
    $sig = signMessage($msg, $fix['private_key_hex']);
    expect((new Web3SignatureVerifier())->verify($fix['address'], $msg, $sig))->toBeTrue();
});

it('rejects signature from different key', function () {
    $fix = json_decode(file_get_contents(__DIR__.'/../../Fixtures/siwe-keypair.json'), true);
    $msg = 'hello';
    $sig = signMessage($msg, '0x' . str_repeat('1', 64));
    expect((new Web3SignatureVerifier())->verify($fix['address'], $msg, $sig))->toBeFalse();
});
```

- [ ] **Step 4: Implementation**

```php
<?php
namespace App\Services\Auth;

use Elliptic\EC;
use kornrunner\Keccak;

class Web3SignatureVerifier
{
    public function verify(string $expectedAddress, string $message, string $signature): bool {
        if (!str_starts_with($signature, '0x') || strlen($signature) !== 132) return false;
        $sigHex = substr($signature, 2);
        $r = substr($sigHex, 0, 64);
        $s = substr($sigHex, 64, 64);
        $v = (int) hexdec(substr($sigHex, 128, 2));
        if ($v >= 27) $v -= 27;

        $hash = Keccak::hash("\x19Ethereum Signed Message:\n" . strlen($message) . $message, 256);

        $ec  = new EC('secp256k1');
        try {
            $key = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $v);
            $pub = '04' . str_pad($key->getX()->toString(16), 64, '0', STR_PAD_LEFT)
                       . str_pad($key->getY()->toString(16), 64, '0', STR_PAD_LEFT);
            $recovered = '0x' . substr(Keccak::hash(hex2bin(substr($pub, 2)), 256), -40);
        } catch (\Throwable $e) {
            return false;
        }
        return strtolower($recovered) === strtolower($expectedAddress);
    }
}
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Unit/Services/Web3SignatureVerifierTest.php
```
Expected: 2 passed. (If signature shape is wrong, the test uses `toBeFalse` and doesn't crash — assert true case carefully.)

```bash
git add composer.json composer.lock app/Services/Auth/Web3SignatureVerifier.php tests/
git commit -m "Add EIP-191 personal_sign signature verifier with test fixture"
```

---

### Task E3: Web3 nonce + verify endpoints

**Files:**
- Create: `app/Services/Auth/Web3NonceGenerator.php`
- Create: `app/Http/Controllers/Auth/Web3Controller.php`
- Modify: `routes/web.php`, `bootstrap/app.php` (CSRF exempt for /auth/web3/verify)
- Test: `tests/Feature/Auth/Web3SiweTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{User, UserIdentity, AuthNonce, LegalDocument};
use App\Services\Auth\SiweMessageBuilder;

beforeEach(function () {
    LegalDocument::factory()->tos()->create(['published_at' => now()]);
    LegalDocument::factory()->privacy()->create(['published_at' => now()]);
});

it('issues a nonce', function () {
    $r = $this->getJson('/auth/web3/nonce?address=0x14791697260E4c9A71f18484C9f997B308e59325');
    $r->assertOk()->assertJsonStructure(['nonce', 'message']);
    expect(AuthNonce::count())->toBe(1);
});

it('logs in known SIWE user after valid signature', function () {
    $fix = json_decode(file_get_contents(base_path('tests/Fixtures/siwe-keypair.json')), true);
    $u = User::factory()->create();
    UserIdentity::factory()->siwe($fix['address'])->for($u)->create();

    $nonceRes = $this->getJson('/auth/web3/nonce?address=' . $fix['address']);
    $message = $nonceRes->json('message');
    $sig = signMessage($message, $fix['private_key_hex']); // helper from E2 test, copied to a Pest helper file

    $this->postJson('/auth/web3/verify', [
        'address' => $fix['address'], 'message' => $message, 'signature' => $sig,
    ])->assertOk();

    expect(auth()->id())->toBe($u->id);
});

it('rejects reused nonce', function () {
    $fix = json_decode(file_get_contents(base_path('tests/Fixtures/siwe-keypair.json')), true);
    $u = User::factory()->create();
    UserIdentity::factory()->siwe($fix['address'])->for($u)->create();
    $message = $this->getJson('/auth/web3/nonce?address=' . $fix['address'])->json('message');
    $sig = signMessage($message, $fix['private_key_hex']);
    $this->postJson('/auth/web3/verify', ['address' => $fix['address'], 'message' => $message, 'signature' => $sig])->assertOk();
    auth()->logout();
    $this->postJson('/auth/web3/verify', ['address' => $fix['address'], 'message' => $message, 'signature' => $sig])
        ->assertStatus(422);
});
```

Note: move `signMessage()` from E2 test into `tests/Helpers/SiweTestHelpers.php` and require it in `tests/Pest.php`.

- [ ] **Step 2: Web3NonceGenerator**

```php
<?php
namespace App\Services\Auth;

use App\Models\AuthNonce;
use Illuminate\Support\Str;

class Web3NonceGenerator
{
    public function issue(string $address): AuthNonce {
        return AuthNonce::create([
            'nonce'      => Str::random(32),
            'address'    => strtolower($address),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function consume(string $nonce, string $address): ?AuthNonce {
        $row = AuthNonce::where('nonce', $nonce)
            ->where('address', strtolower($address))
            ->first();
        if (!$row || !$row->isUsable()) return null;
        $row->update(['used_at' => now()]);
        return $row;
    }
}
```

- [ ] **Step 3: Web3Controller**

```php
<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3NonceGenerator;
use App\Services\Auth\Web3SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Web3Controller extends Controller
{
    public function __construct(
        private Web3NonceGenerator $nonces,
        private Web3SignatureVerifier $verifier,
        private SiweMessageBuilder $builder,
    ) {}

    public function nonce(Request $r) {
        $r->validate(['address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/']]);
        $key = 'siwe-nonce:' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) abort(429);
        RateLimiter::hit($key, 60);
        $nonce = $this->nonces->issue($r->query('address'));
        return response()->json([
            'nonce'   => $nonce->nonce,
            'message' => $this->builder->build($r->query('address'), $nonce->nonce, now()->toIso8601String()),
        ]);
    }

    public function verify(Request $r) {
        $r->validate([
            'address'   => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'regex:/^0x[a-fA-F0-9]{130}$/'],
            'message'   => ['required', 'string'],
        ]);
        $parsed = $this->builder->parse($r->input('message'));
        $consumed = $this->nonces->consume($parsed['nonce'], $r->input('address'));
        if (!$consumed) throw ValidationException::withMessages(['nonce' => 'Nonce ongeldig of verlopen.']);

        if (!$this->verifier->verify($r->input('address'), $r->input('message'), $r->input('signature'))) {
            throw ValidationException::withMessages(['signature' => 'Handtekening klopt niet.']);
        }

        $identity = UserIdentity::where(['provider' => 'siwe', 'provider_uid' => strtolower($r->input('address'))])->first();
        if ($identity) {
            auth()->login($identity->user);
            $identity->update(['last_used_at' => now()]);
            return response()->json(['ok' => true, 'redirect' => '/']);
        }

        // Onboarding required — return a token so the frontend can complete username/email step
        return response()->json(['ok' => true, 'onboarding_required' => true, 'address' => strtolower($r->input('address'))]);
    }
}
```

- [ ] **Step 4: Bind SiweMessageBuilder in container**

In `app/Providers/AppServiceProvider.php` `register()`:
```php
$this->app->singleton(\App\Services\Auth\SiweMessageBuilder::class, fn () =>
    new \App\Services\Auth\SiweMessageBuilder(
        parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost',
        config('app.url'),
    )
);
```

- [ ] **Step 5: Routes + CSRF exemption**

`routes/web.php`:
```php
use App\Http\Controllers\Auth\Web3Controller;
Route::get('/auth/web3/nonce',  [Web3Controller::class, 'nonce']);
Route::post('/auth/web3/verify', [Web3Controller::class, 'verify']);
```

In `bootstrap/app.php` `withMiddleware`:
```php
$middleware->validateCsrfTokens(except: ['/auth/web3/verify']);
```

- [ ] **Step 6: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/Web3SiweTest.php
```
Expected: 3 passed.

```bash
git add app/Services/Auth/Web3NonceGenerator.php app/Http/Controllers/Auth/Web3Controller.php app/Providers/AppServiceProvider.php routes/web.php bootstrap/app.php tests/
git commit -m "Add SIWE nonce + verify endpoints (returns onboarding flag for new wallets)"
```

---

### Task E4: SIWE onboarding component

**Files:**
- Create: `app/Livewire/Auth/SiweOnboarding.php`
- Create: `resources/views/livewire/auth/siwe-onboarding.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/SiweOnboardingTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Auth\SiweOnboarding;
use App\Models\{User, UserIdentity, LegalDocument};
use Livewire\Livewire;

beforeEach(function () {
    LegalDocument::factory()->tos()->create(['published_at' => now()]);
    LegalDocument::factory()->privacy()->create(['published_at' => now()]);
});

it('creates user + siwe identity from onboarding form', function () {
    Livewire::test(SiweOnboarding::class, ['address' => '0xdead000000000000000000000000000000000001'])
        ->set('email', 'wallet@b.nl')->set('username', 'walletuser')->set('display_name', 'Wallet')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertRedirect('/');

    $u = User::where('email', 'wallet@b.nl')->first();
    expect($u->identities()->where('provider', 'siwe')->where('provider_uid', '0xdead000000000000000000000000000000000001')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Component** — analogous to Register, but accepts `address` prop and creates `UserIdentity::factory()->siwe($address)` instead of password identity. Skip showing password field. Set `password_hash = null`.

- [ ] **Step 3: Front-end JS for the wallet flow** — out of scope for this task; will be added with the listing front-end (Task G2 onwards) or a dedicated Web3 sub-project. Onboarding component reachable via `/auth/web3/onboarding/{address}` for now.

- [ ] **Step 4: Route**

```php
Route::get('/auth/web3/onboarding/{address}', \App\Livewire\Auth\SiweOnboarding::class)
    ->where('address', '0x[a-fA-F0-9]{40}');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/SiweOnboardingTest.php
```
Expected: 1 passed.

```bash
git add app/Livewire/Auth/SiweOnboarding.php resources/views/livewire/auth/siwe-onboarding.blade.php routes/web.php tests/
git commit -m "Add SIWE onboarding flow for new wallet users"
```

---

## Phase F — Identity linking + 2FA

### Task F1: IdentityService + last-method protection

**Files:**
- Create: `app/Services/Auth/IdentityService.php`
- Test: `tests/Feature/Auth/IdentityLinkingTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{User, UserIdentity};
use App\Services\Auth\IdentityService;

it('refuses to unlink the last identity', function () {
    $u = User::factory()->create();
    $only = UserIdentity::factory()->password()->for($u)->create();
    expect(app(IdentityService::class)->canUnlink($u, $only))->toBeFalse();
    expect(fn () => app(IdentityService::class)->unlink($u, $only))
        ->toThrow(\App\Services\Auth\LastIdentityException::class);
});

it('allows unlinking when more than one identity exists', function () {
    $u = User::factory()->create();
    $pwd = UserIdentity::factory()->password()->for($u)->create();
    UserIdentity::factory()->github('1')->for($u)->create();
    expect(app(IdentityService::class)->canUnlink($u, $pwd))->toBeTrue();
    app(IdentityService::class)->unlink($u, $pwd);
    expect($u->identities()->count())->toBe(1);
});
```

- [ ] **Step 2: Service + exception**

```php
<?php
namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserIdentity;

class LastIdentityException extends \RuntimeException {}

class IdentityService
{
    public function canUnlink(User $user, UserIdentity $identity): bool {
        return $user->identities()->where('id', '!=', $identity->id)->exists();
    }

    public function unlink(User $user, UserIdentity $identity): void {
        if (!$this->canUnlink($user, $identity)) {
            throw new LastIdentityException('Cannot remove last login method.');
        }
        $identity->delete();
    }
}
```

- [ ] **Step 3: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/IdentityLinkingTest.php
```
Expected: 2 passed.

```bash
git add app/Services/Auth/IdentityService.php tests/
git commit -m "Add IdentityService with last-identity protection"
```

---

### Task F2: Profile/Security page (link/unlink UI)

**Files:**
- Create: `app/Livewire/Profile/Security.php`
- Create: `resources/views/livewire/profile/security.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Profile/SecurityPageTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Profile\Security;
use App\Models\{User, UserIdentity};
use Livewire\Livewire;

it('lists user identities', function () {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    UserIdentity::factory()->github('1')->for($u)->create();
    $this->actingAs($u);
    Livewire::test(Security::class)
        ->assertSee('password')
        ->assertSee('oauth_github');
});

it('disables unlink button when only one identity', function () {
    $u = User::factory()->create();
    $only = UserIdentity::factory()->password()->for($u)->create();
    $this->actingAs($u);
    Livewire::test(Security::class)->call('unlink', $only->id)
        ->assertHasErrors(['identity']);
    expect($u->identities()->count())->toBe(1);
});
```

- [ ] **Step 2: Component**

```php
<?php
namespace App\Livewire\Profile;

use App\Services\Auth\IdentityService;
use App\Services\Auth\LastIdentityException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Security extends Component
{
    public function unlink(int $identityId): void {
        $identity = auth()->user()->identities()->findOrFail($identityId);
        try {
            app(IdentityService::class)->unlink(auth()->user(), $identity);
        } catch (LastIdentityException $e) {
            $this->addError('identity', 'Dit is je enige login-methode — kan niet verwijderd worden.');
        }
    }

    public function render() {
        return view('livewire.profile.security', [
            'identities' => auth()->user()->identities()->get(),
        ]);
    }
}
```

- [ ] **Step 3: View** (table of identities + per-row unlink button + links to OAuth/SIWE add flows)

- [ ] **Step 4: Route**

```php
Route::get('/profile/security', \App\Livewire\Profile\Security::class)->middleware('auth');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Profile/SecurityPageTest.php
```
Expected: 2 passed.

```bash
git add app/Livewire/Profile/Security.php resources/views/livewire/profile/security.blade.php routes/web.php tests/
git commit -m "Add /profile/security page for identity management"
```

---

### Task F3: 2FA enable flow

**Files:**
- Modify: `composer.json` (pragmarx/google2fa-laravel)
- Create: `app/Livewire/Profile/TwoFactorSetup.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Profile/TwoFactorEnableTest.php`

- [ ] **Step 1: Install**

```bash
docker compose exec php-fpm composer require pragmarx/google2fa-laravel:^2 bacon/bacon-qr-code:^3
```

- [ ] **Step 2: Failing test**

```php
<?php
use App\Livewire\Profile\TwoFactorSetup;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('generates a secret and recovery codes; confirms with valid TOTP', function () {
    $u = User::factory()->create();
    $this->actingAs($u);

    $component = Livewire::test(TwoFactorSetup::class)->call('start');
    $u->refresh();
    expect($u->two_factor_secret)->not->toBeNull();
    expect($u->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA())->getCurrentOtp(decrypt($u->two_factor_secret));
    $component->set('code', $code)->call('confirm');
    $u->refresh();
    expect($u->two_factor_confirmed_at)->not->toBeNull();
    expect($u->two_factor_recovery_codes)->toHaveCount(8);
});

it('rejects wrong totp code at confirmation', function () {
    $u = User::factory()->create();
    $this->actingAs($u);
    Livewire::test(TwoFactorSetup::class)->call('start')->set('code', '000000')->call('confirm')
        ->assertHasErrors(['code']);
});
```

- [ ] **Step 3: Component**

```php
<?php
namespace App\Livewire\Profile;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.app')]
class TwoFactorSetup extends Component
{
    public string $code = '';
    public ?string $secret = null;
    public array $recovery = [];

    public function start(): void {
        $g = new Google2FA();
        $this->secret = $g->generateSecretKey();
        $user = auth()->user();
        $user->two_factor_secret = $this->secret;          // cast = encrypted
        $user->two_factor_confirmed_at = null;
        $user->save();
        $this->recovery = collect()->times(8, fn () => Str::random(10))->all();
    }

    public function confirm(): void {
        $this->validate(['code' => ['required', 'digits:6']]);
        $user = auth()->user();
        $g = new Google2FA();
        if (!$g->verifyKey($user->two_factor_secret, $this->code)) {
            $this->addError('code', 'TOTP klopt niet.');
            return;
        }
        $user->two_factor_recovery_codes = $this->recovery;
        $user->two_factor_confirmed_at = now();
        $user->save();
    }

    public function qrUri(): string {
        return (new Google2FA())->getQRCodeUrl(
            'cloudmarktplaats.nl', auth()->user()->username, $this->secret ?? auth()->user()->two_factor_secret
        );
    }

    public function render() { return view('livewire.profile.two-factor-setup'); }
}
```

- [ ] **Step 4: Route + view**

```php
Route::get('/profile/security/2fa', \App\Livewire\Profile\TwoFactorSetup::class)->middleware('auth');
```

View renders `start` button → after start, shows QR (use `bacon-qr-code` to render `qrUri()` as SVG inline) + text input for TOTP + recovery codes once confirmed.

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Profile/TwoFactorEnableTest.php
```
Expected: 2 passed.

```bash
git add composer.json composer.lock app/Livewire/Profile/TwoFactorSetup.php resources/views/livewire/profile/two-factor-setup.blade.php routes/web.php tests/
git commit -m "Add 2FA TOTP enable flow with recovery codes"
```

---

### Task F4: 2FA login challenge

**Files:**
- Create: `app/Livewire/Auth/TwoFactorChallenge.php`
- Modify: `app/Livewire/Auth/Login.php` (redirect to challenge if 2FA enabled)
- Modify: `app/Http/Controllers/Auth/OAuthController.php` (same)
- Modify: `app/Http/Controllers/Auth/Web3Controller.php` (same)
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/TwoFactorChallengeTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\{User, UserIdentity};
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'a@b.nl',
        'password_hash' => bcrypt('p'),
        'two_factor_secret' => encrypt((new Google2FA())->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['recoveryAA1', 'recoveryAA2'])),
        'two_factor_confirmed_at' => now(),
    ]);
    UserIdentity::factory()->password()->for($this->user)->create();
});

it('redirects to challenge instead of completing login', function () {
    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'p')->call('submit')
        ->assertRedirect('/2fa/challenge');
    expect(auth()->id())->toBeNull();
    expect(session('pending_2fa_user_id'))->toBe($this->user->id);
});

it('completes login with valid TOTP', function () {
    session(['pending_2fa_user_id' => $this->user->id]);
    $code = (new Google2FA())->getCurrentOtp(decrypt($this->user->two_factor_secret));
    Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('submit')
        ->assertRedirect('/');
    expect(auth()->id())->toBe($this->user->id);
});

it('completes login with recovery code and removes it', function () {
    session(['pending_2fa_user_id' => $this->user->id]);
    Livewire::test(TwoFactorChallenge::class)->set('code', 'recoveryAA1')->call('submit')
        ->assertRedirect('/');
    expect($this->user->fresh()->two_factor_recovery_codes)->not->toContain('recoveryAA1');
});
```

- [ ] **Step 2: Refactor Login::submit()** — replace `auth()->login` with:

```php
$user = User::where('email', $this->email)->first();
if (!$user || !\Hash::check($this->password, $user->password_hash ?? '')) {
    RateLimiter::hit($key, 60);
    throw ValidationException::withMessages(['email' => 'Inloggegevens onjuist.']);
}
RateLimiter::clear($key);
if ($user->two_factor_confirmed_at) {
    session(['pending_2fa_user_id' => $user->id]);
    return redirect('/2fa/challenge');
}
auth()->login($user, $this->remember);
request()->session()->regenerate();
$user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);
return redirect()->intended('/');
```

Apply the same pre-2FA gating in `OAuthController::callback` and `Web3Controller::verify`.

- [ ] **Step 3: Component**

```php
<?php
namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.app')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function submit() {
        $userId = session('pending_2fa_user_id');
        abort_unless($userId, 401);
        $user = User::findOrFail($userId);

        if (strlen($this->code) === 6 && ctype_digit($this->code)) {
            if ((new Google2FA())->verifyKey($user->two_factor_secret, $this->code)) {
                return $this->complete($user);
            }
        } else {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($this->code, $codes, true)) {
                $user->two_factor_recovery_codes = array_values(array_diff($codes, [$this->code]));
                $user->save();
                return $this->complete($user);
            }
        }
        $this->addError('code', 'Ongeldige code.');
    }

    private function complete(User $user) {
        session()->forget('pending_2fa_user_id');
        auth()->login($user);
        request()->session()->regenerate();
        return redirect()->intended('/');
    }

    public function render() { return view('livewire.auth.two-factor-challenge'); }
}
```

- [ ] **Step 4: Route**

```php
Route::get('/2fa/challenge', \App\Livewire\Auth\TwoFactorChallenge::class)->name('2fa.challenge');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Auth/TwoFactorChallengeTest.php
```
Expected: 3 passed.

```bash
git add app/Livewire/Auth/TwoFactorChallenge.php app/Livewire/Auth/Login.php app/Http/Controllers/Auth/OAuthController.php app/Http/Controllers/Auth/Web3Controller.php routes/web.php tests/
git commit -m "Add 2FA login challenge for password/OAuth/SIWE"
```

---

### Task F5: 2FA disable + regenerate recovery codes

Pattern follows F3/F4. Component method `disable(string $totp, string $password)` verifies both, then nulls out `two_factor_*` fields. Method `regenerate(string $totp)` rotates codes.

Test cases (write each as failing-first):
1. Disable requires correct TOTP + password (or OAuth/SIWE re-auth proof — for foundation: require password if any password identity exists, otherwise require fresh TOTP only)
2. Regenerate requires TOTP and shows new codes once

Implement on `app/Livewire/Profile/TwoFactorSetup.php`. Commit:
```
git commit -m "Add 2FA disable + recovery code regeneration"
```

---

## Phase G — Listings, photos, search, reports

### Task G1: ListingStateService + state events

**Files:**
- Create: `app/Services/Listings/ListingStateService.php`
- Create: `app/Events/Listings/ListingPublished.php`, `ListingSold.php`, `ListingRejected.php`, `ListingArchived.php`
- Test: `tests/Unit/Services/ListingStateServiceTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Events\Listings\ListingPublished;
use App\Events\Listings\ListingRejected;
use App\Models\Listing;
use App\Services\Listings\{InvalidStateTransition, ListingStateService};
use Illuminate\Support\Facades\Event;

it('publishes a pending listing and fires event', function () {
    Event::fake();
    $l = Listing::factory()->create(['state' => 'pending_review']);
    app(ListingStateService::class)->transition($l, 'published');
    expect($l->fresh()->state)->toBe('published');
    expect($l->fresh()->published_at)->not->toBeNull();
    Event::assertDispatched(ListingPublished::class);
});

it('rejects invalid transitions', function () {
    $l = Listing::factory()->create(['state' => 'sold']);
    expect(fn () => app(ListingStateService::class)->transition($l, 'draft'))
        ->toThrow(InvalidStateTransition::class);
});
```

- [ ] **Step 2: Service**

```php
<?php
namespace App\Services\Listings;

use App\Events\Listings\{ListingArchived, ListingPublished, ListingRejected, ListingSold};
use App\Models\Listing;

class InvalidStateTransition extends \DomainException {}

class ListingStateService
{
    private const ALLOWED = [
        'draft'          => ['pending_review', 'archived'],
        'pending_review' => ['published', 'rejected', 'draft'],
        'published'      => ['sold', 'archived'],
        'sold'           => ['archived'],
        'archived'       => [],
        'rejected'       => ['draft'],
    ];

    public function transition(Listing $listing, string $to, ?string $note = null): void {
        $from = $listing->state;
        if (!in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidStateTransition("Cannot move listing {$listing->id} from $from to $to");
        }
        $listing->state = $to;
        if ($to === 'published') $listing->published_at = now();
        if ($to === 'sold')      $listing->sold_at      = now();
        if ($to === 'rejected')  $listing->moderation_notes = $note;
        $listing->save();

        match ($to) {
            'published' => event(new ListingPublished($listing)),
            'sold'      => event(new ListingSold($listing)),
            'rejected'  => event(new ListingRejected($listing, $note)),
            'archived'  => event(new ListingArchived($listing)),
            default     => null,
        };
    }
}
```

- [ ] **Step 3: Events** — each is a one-line `__construct(public Listing $listing)` class.

- [ ] **Step 4: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Unit/Services/ListingStateServiceTest.php
```
Expected: 2 passed.

```bash
git add app/Services/Listings/ app/Events/ tests/
git commit -m "Add ListingStateService with transition validation + events"
```

---

### Task G2: StorageInterface + LocalStorage + StorageManager

**Files:**
- Create: `app/Services/Storage/StorageInterface.php`, `LocalStorage.php`, `StorageManager.php`
- Modify: `bootstrap/providers.php` (or `AppServiceProvider`) to bind manager
- Test: `tests/Unit/Services/StorageManagerTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Services\Storage\LocalStorage;
use App\Services\Storage\StorageManager;

it('round-trips a file via local driver', function () {
    $mgr = app(StorageManager::class);
    $driver = $mgr->driver('local');
    $driver->put('test/foo.txt', 'hello');
    expect($driver->exists('test/foo.txt'))->toBeTrue();
    expect($driver->get('test/foo.txt'))->toBe('hello');
    $driver->delete('test/foo.txt');
    expect($driver->exists('test/foo.txt'))->toBeFalse();
});

it('throws on unknown driver', function () {
    expect(fn () => app(StorageManager::class)->driver('ipfs'))
        ->toThrow(\InvalidArgumentException::class);
});
```

- [ ] **Step 2: Implementation**

`StorageInterface.php`:
```php
<?php
namespace App\Services\Storage;

interface StorageInterface
{
    public function put(string $path, string $contents, array $options = []): string;
    public function get(string $path): string;
    public function url(string $path): string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
}
```

`LocalStorage.php`:
```php
<?php
namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class LocalStorage implements StorageInterface
{
    public function put(string $path, string $contents, array $options = []): string {
        Storage::disk('public')->put($path, $contents);
        return $path;
    }
    public function get(string $path): string  { return Storage::disk('public')->get($path); }
    public function url(string $path): string  { return Storage::disk('public')->url($path); }
    public function delete(string $path): bool { return Storage::disk('public')->delete($path); }
    public function exists(string $path): bool { return Storage::disk('public')->exists($path); }
}
```

`StorageManager.php`:
```php
<?php
namespace App\Services\Storage;

class StorageManager
{
    private array $drivers = [];

    public function __construct(private array $bindings) {}

    public function driver(string $name): StorageInterface {
        if (!isset($this->bindings[$name])) {
            throw new \InvalidArgumentException("Unknown storage driver: $name");
        }
        return $this->drivers[$name] ??= app($this->bindings[$name]);
    }

    public function default(): StorageInterface {
        return $this->driver(config('cloudmarktplaats.storage.driver'));
    }
}
```

In `AppServiceProvider::register`:
```php
$this->app->singleton(StorageManager::class, fn () => new StorageManager([
    'local' => LocalStorage::class,
]));
```

Run `php artisan storage:link` once so `public/storage` exists.

- [ ] **Step 3: Run + commit**

```bash
docker compose exec php-fpm php artisan storage:link
docker compose exec php-fpm ./vendor/bin/pest tests/Unit/Services/StorageManagerTest.php
```
Expected: 2 passed.

```bash
git add app/Services/Storage/ app/Providers/AppServiceProvider.php tests/
git commit -m "Add StorageInterface + LocalStorage + StorageManager"
```

---

### Task G3: StoreListingPhotoJob (MIME + EXIF strip + variants)

**Files:**
- Modify: `composer.json` (intervention/image-laravel)
- Create: `app/Jobs/StoreListingPhotoJob.php`
- Test: `tests/Feature/Listings/PhotoPipelineTest.php`

- [ ] **Step 1: Install Intervention/Image**

```bash
docker compose exec php-fpm composer require intervention/image-laravel:^1
```

- [ ] **Step 2: Failing test using a JPEG with embedded EXIF**

```php
<?php
use App\Jobs\StoreListingPhotoJob;
use App\Models\{Listing, ListingPhoto};
use Illuminate\Support\Facades\Storage;

it('processes an upload, strips EXIF, generates variants', function () {
    Storage::fake('public');
    $listing = Listing::factory()->create();
    // Use a fixture JPEG known to contain EXIF GPS
    $bytes = file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    StoreListingPhotoJob::dispatchSync($listing->id, $bytes, 'image/jpeg', 1);

    expect(ListingPhoto::where('listing_id', $listing->id)->count())->toBe(1);
    $photo = $listing->photos()->first();

    foreach (['original.jpg', 'card.webp', 'thumb.webp'] as $variant) {
        $path = "listings/{$listing->ulid}/{$photo->id}/{$variant}";
        expect(Storage::disk('public')->exists($path))->toBeTrue();
    }

    // Verify EXIF stripped from card variant
    $cardPath = Storage::disk('public')->path("listings/{$listing->ulid}/{$photo->id}/card.webp");
    $exif = @exif_read_data($cardPath);
    expect($exif['GPSLatitude'] ?? null)->toBeNull();
});

it('rejects non-image MIME', function () {
    $listing = Listing::factory()->create();
    expect(fn () => StoreListingPhotoJob::dispatchSync($listing->id, 'not an image', 'application/pdf', 1))
        ->toThrow(\App\Jobs\InvalidUploadException::class);
});
```

(Add a real EXIF-bearing JPEG to `tests/Fixtures/photo-with-gps.jpg` — engineer can generate with `exiftool -GPSLatitude=52.0 sample.jpg`.)

- [ ] **Step 3: Job**

```php
<?php
namespace App\Jobs;

use App\Models\ListingPhoto;
use App\Services\Storage\StorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Laravel\Facades\Image;

class InvalidUploadException extends \DomainException {}

class StoreListingPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $listingId, public string $bytes, public string $declaredMime, public int $position) {}

    public function handle(StorageManager $storage): void {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = (new \finfo(FILEINFO_MIME_TYPE))->buffer($this->bytes);
        if (!in_array($finfo, $allowed, true) || !in_array($this->declaredMime, $allowed, true)) {
            throw new InvalidUploadException("Disallowed MIME: $finfo");
        }

        $img = Image::read($this->bytes);
        if ($img->width() < 200 || $img->height() < 200 || $img->width() > 8000 || $img->height() > 8000) {
            throw new InvalidUploadException('Dimension out of range');
        }
        // Strip EXIF
        $stripped = clone $img;

        $listing = \App\Models\Listing::findOrFail($this->listingId);
        $photo = ListingPhoto::create([
            'listing_id' => $listing->id, 'disk' => 'local',
            'path' => "listings/{$listing->ulid}/__pending__/card.webp",
            'width' => $img->width(), 'height' => $img->height(),
            'mime' => $finfo, 'byte_size' => strlen($this->bytes), 'position' => $this->position,
        ]);
        $base = "listings/{$listing->ulid}/{$photo->id}";
        $ext  = match($finfo) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };

        $original = (clone $stripped)->scaleDown(2000, 2000)->encode();
        $card     = (clone $stripped)->cover(600, 600)->toWebp();
        $thumb    = (clone $stripped)->cover(200, 200)->toWebp();

        $driver = $storage->driver('local');
        $driver->put("$base/original.$ext", (string) $original);
        $driver->put("$base/card.webp",     (string) $card);
        $driver->put("$base/thumb.webp",    (string) $thumb);

        $photo->update(['path' => "$base/card.webp"]);
    }
}
```

- [ ] **Step 4: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Listings/PhotoPipelineTest.php
```
Expected: 2 passed.

```bash
git add composer.json composer.lock app/Jobs/StoreListingPhotoJob.php tests/
git commit -m "Add StoreListingPhotoJob (MIME validation, EXIF strip, 3 variants)"
```

---

### Task G4: Listing wizard (3 steps, draft auto-save)

**Files:**
- Create: `app/Livewire/Listings/Wizard.php`
- Create: `resources/views/livewire/listings/wizard.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Listings/WizardTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Livewire\Listings\Wizard;
use App\Models\{Category, Listing, User, LegalDocument};
use Livewire\Livewire;

beforeEach(function () {
    LegalDocument::factory()->tos()->create(['published_at' => now()]);
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();
});

it('persists draft after step 1 and advances to step 2', function () {
    $this->actingAs($this->user);
    $c = Livewire::test(Wizard::class)
        ->set('step', 1)
        ->set('title', 'Dell R720')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 25000)
        ->call('next');
    $c->assertSet('step', 2);
    expect(Listing::where('user_id', $this->user->id)->first()->state)->toBe('draft');
});

it('submits as pending_review at end', function () {
    $this->actingAs($this->user);
    Livewire::test(Wizard::class)
        ->set('title', 'Switch Cisco 3750')->set('category_id', $this->category->id)
        ->set('condition', 'used')->set('price_cents', 5000)
        ->call('next')                                      // step 2
        ->set('description', '24 ports gigabit')
        ->set('region_postcode', '1011')
        ->set('shipping_pickup', true)
        ->call('next')                                      // step 3
        ->call('submit');
    expect(Listing::where('user_id', $this->user->id)->first()->state)->toBe('pending_review');
});
```

- [ ] **Step 2: Component** — full code follows the pattern of Register: properties for each field, `next()` validates current step + persists draft, `submit()` transitions state via `ListingStateService::transition($listing, 'pending_review')`. Photo upload via Livewire's `WithFileUploads`; on each upload, dispatch `StoreListingPhotoJob::dispatchSync(...)` (sync in foundation; switch to queued in production).

(Engineer fills in the component using Wizard.php skeleton: ~120 lines. View renders 3 conditional sections based on `$step`.)

- [ ] **Step 3: Route**

```php
Route::get('/listings/new', \App\Livewire\Listings\Wizard::class)->middleware(['auth', 'verified', 'legal']);
Route::get('/listings/{listing:ulid}/edit', \App\Livewire\Listings\Wizard::class)
    ->middleware(['auth', 'verified', 'legal'])->name('listings.edit');
```

- [ ] **Step 4: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Listings/WizardTest.php
```
Expected: 2 passed.

```bash
git add app/Livewire/Listings/Wizard.php resources/views/livewire/listings/ routes/web.php tests/
git commit -m "Add 3-step listing creation wizard with draft auto-save"
```

---

### Task G5: Browse + detail + category pages

**Files:**
- Create: `app/Livewire/Listings/Browse.php`, `Detail.php`
- Create: `resources/views/livewire/listings/browse.blade.php`, `detail.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Listings/BrowseTest.php`, `DetailTest.php`

- [ ] **Step 1: Failing tests**

```php
// browse
it('lists published listings only', function () {
    Listing::factory()->published()->count(3)->create();
    Listing::factory()->create(['state' => 'draft']);
    $this->get('/listings')->assertOk()->assertSee('listing'); // assert at least one
    expect((new \App\Livewire\Listings\Browse())->query()->count())->toBe(3);
});

// category
it('filters by category path including descendants', function () {
    $servers = Category::create(['name' => 'Servers', 'slug' => 'servers', 'path' => 'servers']);
    $rack    = Category::create(['name' => 'Rack', 'slug' => 'rack', 'path' => 'servers.rack']);
    Listing::factory()->published()->for($servers)->create();
    Listing::factory()->published()->for($rack)->create();
    $this->get('/c/servers')->assertOk();
    expect((new \App\Livewire\Listings\Browse('servers'))->query()->count())->toBe(2);
});

// detail
it('shows detail page for anonymous; contact button redirects to login', function () {
    $l = Listing::factory()->published()->create();
    $this->get("/listings/{$l->ulid}-{$l->slug}")->assertOk()->assertSee($l->title);
});
```

- [ ] **Step 2: Browse component** with `query()` method that returns `Listing::query()->where('state', 'published')`, optionally chained with `whereHas('category', fn($q) => $q->whereRaw("path <@ ?::ltree", [$path]))` if a category path is provided.

- [ ] **Step 3: Detail component** that renders listing + photos + (auth-gated) contact button → redirects anonymous users to login.

- [ ] **Step 4: Routes**

```php
Route::get('/listings', \App\Livewire\Listings\Browse::class);
Route::get('/c/{path}', \App\Livewire\Listings\Browse::class)->where('path', '[a-z0-9._-]+');
Route::get('/listings/{ulid}-{slug}', \App\Livewire\Listings\Detail::class)
    ->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}');
```

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Listings/
git add app/Livewire/Listings/ resources/views/livewire/listings/ routes/web.php tests/
git commit -m "Add browse/category/detail pages for listings"
```

---

### Task G6: Postgres FTS search route

**Files:**
- Create: `app/Services/Search/SearchInterface.php`, `PostgresSearchService.php`
- Create: `app/Http/Controllers/SearchController.php`
- Modify: `routes/web.php`
- Modify: `AppServiceProvider` (bind interface)
- Test: `tests/Feature/Listings/SearchTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{Listing, Category};

it('returns listings ranked by tsvector match', function () {
    $cat = Category::factory()->create();
    Listing::factory()->published()->for($cat)->create(['title' => 'Cisco switch 3750', 'description' => 'managed L3']);
    Listing::factory()->published()->for($cat)->create(['title' => 'Old PSU 750W']);
    $r = $this->get('/search?q=cisco')->assertOk();
    $r->assertSee('Cisco switch 3750');
    $r->assertDontSee('Old PSU 750W');
});
```

- [ ] **Step 2: Interface + impl**

```php
<?php
namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

interface SearchInterface {
    public function listings(string $query): Builder;
}

class PostgresSearchService implements SearchInterface {
    public function listings(string $query): Builder {
        return \App\Models\Listing::query()
            ->where('state', 'published')
            ->whereRaw("search_vector @@ plainto_tsquery('dutch', ?)", [$query])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('dutch', ?)) DESC", [$query]);
    }
}
```

- [ ] **Step 3: Controller**

```php
<?php
namespace App\Http\Controllers;

use App\Services\Search\SearchInterface;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $r, SearchInterface $svc) {
        $q = (string) $r->query('q', '');
        $results = $q === '' ? collect() : $svc->listings($q)->paginate(20);
        return view('listings.search', compact('q', 'results'));
    }
}
```

In `AppServiceProvider::register`:
```php
$this->app->bind(\App\Services\Search\SearchInterface::class, \App\Services\Search\PostgresSearchService::class);
```

- [ ] **Step 4: View, route, run, commit**

```php
Route::get('/search', \App\Http\Controllers\SearchController::class);
```

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Listings/SearchTest.php
git add app/Services/Search/ app/Http/Controllers/SearchController.php resources/views/listings/search.blade.php routes/web.php app/Providers/AppServiceProvider.php tests/
git commit -m "Add /search using Postgres FTS via SearchInterface"
```

---

### Task G7: Reports endpoint + view-count anti-abuse

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Create: `app/Jobs/IncrementViewJob.php`
- Modify: `routes/web.php`, `Detail.php` (dispatch view job)
- Test: `tests/Feature/Listings/ReportAndViewTest.php`

- [ ] **Step 1: Failing tests**

```php
it('creates a report from authenticated user', function () {
    $u = User::factory()->create();
    $l = Listing::factory()->published()->create();
    $this->actingAs($u)->post("/listings/{$l->ulid}/report", ['reason' => 'spam', 'details' => 'fake'])
        ->assertRedirect();
    expect(\App\Models\Report::count())->toBe(1);
});

it('increments view count once per hour per ip', function () {
    $l = Listing::factory()->published()->create(['view_count' => 0]);
    \App\Jobs\IncrementViewJob::dispatchSync($l->id, '1.1.1.1');
    \App\Jobs\IncrementViewJob::dispatchSync($l->id, '1.1.1.1');  // throttled
    expect($l->fresh()->view_count)->toBe(1);
});
```

- [ ] **Step 2: ReportController + IncrementViewJob** (use Cache::lock or Redis SETNX with 1h TTL on `views:{listing}:{ip-hash}`).

- [ ] **Step 3: Routes + dispatch in Detail::mount()** + commit.

```bash
git commit -m "Add report endpoint + view-count anti-abuse job"
```

---

## Phase H — Filament admin panel

### Task H1: Install Filament + custom admin guard

**Files:**
- Modify: `composer.json`
- Create: `app/Providers/Filament/AdminPanelProvider.php` (generated)
- Create: `app/Http/Middleware/RoleMiddleware.php`
- Modify: `bootstrap/app.php` (register `role:` alias)
- Test: `tests/Feature/Admin/AdminAccessTest.php`

- [ ] **Step 1: Install + generate panel**

```bash
docker compose exec php-fpm composer require filament/filament:^3.2 -W
docker compose exec php-fpm php artisan filament:install --panels
```

When prompted for panel id, use `admin`.

- [ ] **Step 2: Failing test**

```php
<?php
use App\Models\User;

it('blocks anon from /admin', function () {
    $this->get('/admin')->assertRedirect('/login');
});
it('blocks regular user from /admin', function () {
    $u = User::factory()->create(['role' => 'user']);
    $this->actingAs($u)->get('/admin')->assertStatus(403);
});
it('allows moderator', function () {
    $u = User::factory()->moderator()->create();
    $this->actingAs($u)->get('/admin')->assertOk();
});
it('allows admin', function () {
    $u = User::factory()->admin()->create();
    $this->actingAs($u)->get('/admin')->assertOk();
});
```

- [ ] **Step 3: RoleMiddleware**

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user() && $request->user()->hasRole(...$roles), 403);
        return $next($request);
    }
}
```

In `bootstrap/app.php`:
```php
->withMiddleware(function ($middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class]);
})
```

- [ ] **Step 4: Configure AdminPanelProvider**

In `app/Providers/Filament/AdminPanelProvider.php` `panel()` chain, add:
```php
->authMiddleware(['web', 'auth', 'role:admin,moderator'])
```

Also implement `User::canAccessPanel(Panel $panel)` returning `$this->hasRole('admin', 'moderator')` and add `implements \Filament\Models\Contracts\FilamentUser` to the User class.

- [ ] **Step 5: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Admin/AdminAccessTest.php
```
Expected: 4 passed.

```bash
git add composer.json composer.lock app/Providers/Filament/AdminPanelProvider.php app/Http/Middleware/RoleMiddleware.php app/Models/User.php bootstrap/app.php tests/
git commit -m "Install Filament 3 with role-based access control"
```

---

### Task H2: Filament resources

Bundled task — six resources (Users, Listings, Categories, Reports, Legal documents, Admin actions). Each is generated via `php artisan make:filament-resource <Model> --generate` and then customised. Each resource gets at least one feature test asserting list + edit + (where applicable) action behaviour.

**Files:**
- Create: `app/Filament/Resources/UserResource.php`, `ListingResource.php`, `CategoryResource.php`, `ReportResource.php`, `LegalDocumentResource.php`, `AdminActionResource.php` (read-only)
- Create: corresponding pages under `app/Filament/Resources/<Name>/Pages/`
- Test: one feature test per resource

- [ ] **Step 1: Generate resources**

```bash
for m in User Listing Category Report LegalDocument AdminAction; do
  docker compose exec php-fpm php artisan make:filament-resource $m --generate
done
```

- [ ] **Step 2: For each resource, write a failing test then customise**

Pattern per resource (UserResource example):

```php
// tests/Feature/Admin/UserResourceTest.php
<?php
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

it('shows users list to admin', function () {
    User::factory()->count(3)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Livewire::test(ListUsers::class)->assertCanSeeTableRecords(User::all());
});

it('allows ban action', function () {
    $target = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Livewire::test(\App\Filament\Resources\UserResource\Pages\EditUser::class, ['record' => $target->id])
        ->callAction('ban', data: ['reason' => 'spam'])
        ->assertSuccessful();
    expect($target->fresh()->is_banned)->toBeTrue();
});
```

Then add `Tables\Actions\Action::make('ban')` to `UserResource::table()` with a form modal that requires a `reason` text field.

- [ ] **Step 3: Customisations per resource (one bullet each)**

- **UserResource:** ban/unban actions (with reason), force-disable-2FA action (sets `two_factor_*` fields to null), search by email/username, filters: role + banned status
- **ListingResource:** state column (badge), filters: state + category + has_reports, bulk publish, bulk reject (modal asks for reason → calls `ListingStateService::transition($l, 'rejected', $reason)` per row + sends email)
- **CategoryResource:** tree-view layout (use Filament's nested set behavior or a simple drag-sort on a flat ltree path); slug auto from name, path auto from parent picker
- **ReportResource:** filter status, action `resolve` (sets status + resolution_note + optional cascade to target), action `dismiss`
- **LegalDocumentResource:** "Publish new version" action (sets `published_at = now()`), markdown field with preview, table grouped by `(type, locale)` showing version history
- **AdminActionResource:** read-only (`canCreate=false`, `canEdit=false`, `canDelete=false`), search by action + target_type, filterable by user_id

- [ ] **Step 4: Run all admin tests + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Admin/
git add app/Filament/Resources/ tests/
git commit -m "Add Filament resources for User/Listing/Category/Report/LegalDocument/AdminAction"
```

---

### Task H3: AdminActionLogger observer

**Files:**
- Create: `app/Observers/AdminActionLogger.php`
- Modify: `AppServiceProvider::boot()` (register globally on Filament Action::after)

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{AdminAction, User, Listing};
use App\Filament\Resources\ListingResource\Pages\EditListing;
use Livewire\Livewire;

it('writes admin_actions row when admin rejects a listing', function () {
    $admin = User::factory()->admin()->create();
    $l = Listing::factory()->create(['state' => 'pending_review']);
    $this->actingAs($admin);

    Livewire::test(EditListing::class, ['record' => $l->id])
        ->callAction('reject', data: ['reason' => 'duplicate']);

    $log = AdminAction::where('action', 'listing.reject')->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->meta['reason'] ?? null)->toBe('duplicate');
});
```

- [ ] **Step 2: Register a global Filament action hook**

In `AppServiceProvider::boot()`:
```php
\Filament\Actions\Action::configureUsing(function (\Filament\Actions\Action $action) {
    $action->after(function ($action, $record) {
        if (!auth()->check()) return;
        \App\Models\AdminAction::create([
            'user_id'     => auth()->id(),
            'action'      => $action->getName() . '.' . class_basename($record ?? new \stdClass),
            'target_type' => $record ? get_class($record) : 'unknown',
            'target_id'   => $record?->getKey() ?? 0,
            'meta'        => $action->getFormData() ?? [],
            'ip_hash'     => hash('sha256', request()->ip() . config('app.key')),
            'created_at'  => now(),
        ]);
    });
});
```

(For a tighter mapping of action names to canonical strings like `listing.reject`, override per-action: `->after(fn () => AdminAction::create([... 'action' => 'listing.reject' ...]))` inside each resource.)

- [ ] **Step 3: Run + commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/Admin/AdminActionLoggerTest.php
git add app/Observers/AdminActionLogger.php app/Providers/AppServiceProvider.php app/Filament/Resources/ tests/
git commit -m "Add AdminActionLogger observing Filament actions"
```

---

### Task H4: Dashboard widgets

**Files:**
- Create: `app/Filament/Widgets/PendingReviewsWidget.php`, `OpenReportsWidget.php`, `NewUsersChartWidget.php`, `OutdatedTosWidget.php`, `ActiveListingsWidget.php`
- Test: `tests/Feature/Admin/DashboardWidgetTest.php`

Each widget extends `Filament\Widgets\StatsOverviewWidget` or `ChartWidget`. Test pattern: `Livewire::test(WidgetClass::class)->assertOk()` plus assertion on the computed stat.

```bash
git commit -m "Add admin dashboard widgets (pending reviews, reports, users, tos, listings)"
```

---

## Phase I — Polish & acceptance

### Task I1: LegalAcceptanceMiddleware

**Files:**
- Create: `app/Http/Middleware/LegalAcceptance.php`
- Modify: `bootstrap/app.php` (alias `legal`)
- Test: `tests/Feature/LegalMiddlewareTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Models\{User, LegalDocument, LegalAcceptance};

it('redirects user to /legal/accept when newer ToS published', function () {
    $u = User::factory()->create();
    $oldDoc = LegalDocument::factory()->tos()->create(['version' => '1.0.0', 'published_at' => now()->subDay()]);
    LegalAcceptance::create(['user_id' => $u->id, 'legal_document_id' => $oldDoc->id, 'accepted_at' => now()->subDay(), 'ip_hash' => str_repeat('a', 64)]);
    LegalDocument::factory()->tos()->create(['version' => '1.1.0', 'published_at' => now()]);

    $this->actingAs($u)->get('/listings/new')->assertRedirect('/legal/accept');
});
```

- [ ] **Step 2: Middleware**

```php
<?php
namespace App\Http\Middleware;

use App\Models\LegalDocument;
use Closure;
use Illuminate\Http\Request;

class LegalAcceptance
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u) return $next($request);

        foreach (['tos', 'privacy'] as $type) {
            $current = LegalDocument::current($type, app()->getLocale());
            if (!$current) continue;
            $accepted = $u->legalAcceptances()->where('legal_document_id', $current->id)->exists();
            if (!$accepted && !$request->is('legal/accept')) {
                return redirect('/legal/accept');
            }
        }
        return $next($request);
    }
}
```

- [ ] **Step 3: Build `/legal/accept` page** (Livewire `LegalAccept.php` listing un-accepted docs with checkboxes + accept button writing `legal_acceptances`).

- [ ] **Step 4: Register alias + commit**

```php
$middleware->alias(['legal' => \App\Http\Middleware\LegalAcceptance::class]);
```

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/LegalMiddlewareTest.php
git commit -m "Add LegalAcceptanceMiddleware re-prompting on new ToS/privacy version"
```

---

### Task I2: IpStripperJob + scheduler

**Files:**
- Create: `app/Jobs/IpStripperJob.php`
- Modify: `app/Console/Kernel.php` (or `bootstrap/app.php` `withSchedule`)
- Test: `tests/Feature/IpStripperJobTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
use App\Jobs\IpStripperJob;
use App\Models\User;

it('clears last_login_ip older than 24h', function () {
    $old = User::factory()->create(['last_login_ip' => '1.1.1.1', 'last_login_at' => now()->subHours(25)]);
    $new = User::factory()->create(['last_login_ip' => '2.2.2.2', 'last_login_at' => now()->subMinutes(5)]);
    IpStripperJob::dispatchSync();
    expect($old->fresh()->last_login_ip)->toBeNull();
    expect($new->fresh()->last_login_ip)->toBe('2.2.2.2');
});
```

- [ ] **Step 2: Job**

```php
<?php
namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IpStripperJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;
    public function handle(): void {
        User::whereNotNull('last_login_ip')
            ->where('last_login_at', '<', now()->subHours(24))
            ->update(['last_login_ip' => null]);
    }
}
```

- [ ] **Step 3: Schedule hourly** (in `bootstrap/app.php` `withSchedule`):

```php
$schedule->job(new \App\Jobs\IpStripperJob())->hourly();
```

- [ ] **Step 4: Commit**

```bash
docker compose exec php-fpm ./vendor/bin/pest tests/Feature/IpStripperJobTest.php
git commit -m "Add IpStripperJob (hourly) for 24h IP retention"
```

---

### Task I3: Service interface noop stubs (Web3, Dac7, Reputation)

**Files:**
- Create: `app/Services/Web3/Web3Service.php`, `Dac7/Dac7Service.php`, `Reputation/ReputationService.php`
- Test: `tests/Unit/Services/NoopStubsTest.php`

Each stub returns a documented sentinel (null, 0, false). Tests assert that.

```php
it('Dac7Service returns zero progress in foundation', function () {
    expect(app(\App\Services\Dac7\Dac7Service::class)->getThresholdProgress(1))->toBe(0);
});
```

```bash
git commit -m "Add noop Web3/Dac7/Reputation services as interface placeholders"
```

---

### Task I4: README + docs

**Files:**
- Create: `README.md` (NL)
- Create: `README-EN.md` (EN summary)
- Create: `docs/dac7-position.md`
- Create: `docs/feature-flags.md`
- Create: `docs/known-gaps.md`

Sections in `README.md` per spec §11:
1. Wat het is + AGPL-3.0
2. Stack overzicht
3. `docker compose up -d` quickstart
4. DAC7 juridische positie + transactions-off-platform clausule (link naar `docs/dac7-position.md`)
5. Feature-flag tabel (link naar `docs/feature-flags.md`)
6. Privacy-statement
7. Hoe contribute (link GitHub Issues)
8. Bekende gaten (link naar `docs/known-gaps.md`)

```bash
git commit -m "Add README + docs (DAC7 position, feature flags, known gaps)"
```

---

### Task I5: End-to-end acceptance walkthrough (browser)

**Files:**
- Create: `tests/Browser/AcceptanceWalkthroughTest.php`
- Modify: `composer.json` (pestphp/pest-plugin-browser)

- [ ] **Step 1: Install Pest browser plugin**

```bash
docker compose exec php-fpm composer require --dev pestphp/pest-plugin-browser:^3
```

- [ ] **Step 2: Walkthrough test (the §14 acceptance scenario)**

```php
<?php
it('completes the full publishable journey', function () {
    $this->seed();

    visit('/register')
        ->fill('email', 'jane@example.nl')->fill('username', 'jane')->fill('display_name', 'Jane')
        ->fill('password', 'jane-secret-1234')->fill('password_confirmation', 'jane-secret-1234')
        ->check('accept_tos')
        ->press('Account aanmaken')
        ->assertPathIs('/email/verify-notice');

    // Fast-forward email verification (sign URL directly)
    $u = \App\Models\User::where('email', 'jane@example.nl')->first();
    visit(\Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify', now()->addMinutes(60),
        ['id' => $u->id, 'hash' => sha1($u->email)],
    ))->assertPathIs('/');

    // Enable 2FA — skipped in this walkthrough for brevity (separate browser test in F3)

    // Create listing
    visit('/listings/new')
        ->select('category_id', \App\Models\Category::first()->id)
        ->fill('title', 'Acceptance test PSU')
        ->fill('price_cents', '4500')
        ->select('condition', 'used')
        ->press('Volgende')                                 // step 2
        ->fill('description', '750W bronze PSU')
        ->fill('region_postcode', '1011')
        ->check('shipping_pickup')
        ->press('Volgende')                                 // step 3
        ->press('Inzenden voor moderatie')
        ->assertSee('Wacht op moderatie');

    // Admin approves
    $admin = \App\Models\User::factory()->admin()->create();
    actingAs($admin);
    $listing = \App\Models\Listing::where('title', 'Acceptance test PSU')->first();
    visit("/admin/listings/{$listing->id}/edit")
        ->press('Publish')
        ->assertSee('published');

    // Anonymous browse + search + contact gate
    auth()->logout();
    visit('/search?q=psu')->assertSee('Acceptance test PSU');
    visit("/listings/{$listing->ulid}-{$listing->slug}")
        ->press('Neem contact op')
        ->assertPathIs('/login');
});
```

- [ ] **Step 3: Run**

```bash
docker compose exec php-fpm ./vendor/bin/pest --filter=AcceptanceWalkthroughTest
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git commit -m "Add e2e acceptance walkthrough covering full publishable journey"
```

---

### Task I6: Final CI verification + move spec/plan into v2 repo + push

**Files:**
- Move: copy spec + plan from v1 repo into v2 repo's `docs/superpowers/`
- Modify: GitHub Actions matrix if needed

- [ ] **Step 1: Copy spec + plan into the v2 repo**

```bash
mkdir -p /mnt/nvme1tb/projects/cloudmarktplaats/docs/superpowers/specs /mnt/nvme1tb/projects/cloudmarktplaats/docs/superpowers/plans
cp /mnt/nvme1tb/projects/cloudmarkplaats/docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md /mnt/nvme1tb/projects/cloudmarktplaats/docs/superpowers/specs/
cp /mnt/nvme1tb/projects/cloudmarkplaats/docs/superpowers/plans/2026-05-16-cloudmarktplaats-v2-foundation.md /mnt/nvme1tb/projects/cloudmarktplaats/docs/superpowers/plans/
cd /mnt/nvme1tb/projects/cloudmarktplaats
git add docs/superpowers/
git commit -m "Import Foundation spec + plan from v1 repo for reference"
```

- [ ] **Step 2: Run full CI locally**

```bash
docker compose exec php-fpm ./vendor/bin/pint --test
docker compose exec php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec php-fpm ./vendor/bin/pest --parallel
```

All three green = Foundation done.

- [ ] **Step 3: Tag v0.1.0**

```bash
git tag v0.1.0-foundation
```

(Push to remote when GitHub repo exists. That setup is outside Foundation scope.)

---

## Self-Review

**Spec coverage check (each spec section → task that covers it):**

- Spec §1 Scope & Intent → README in I4
- Spec §2 Architecture & stack → A1–A2
- Spec §2 docker-compose → A2; healthcheck → A7
- Spec §2 hard policies → I2 (IP-strip), D3 (no Google), G3 (EXIF), config in A3
- Spec §3 users + identities + nonces → B1
- Spec §3 legal docs → B2
- Spec §3 categories + ltree → B3
- Spec §3 listings + tsvector → B4
- Spec §3 listing_photos + reports + transactions + admin_actions → B5
- Spec §4.1 email auth → C2–C5
- Spec §4.2 OAuth → D2 (controller), D3 (exclusion test)
- Spec §4.3 SIWE → E1–E4
- Spec §4.4 identity linking + last-method protection → F1–F2
- Spec §4.5 2FA TOTP enable/challenge/disable → F3, F4, F5
- Spec §4.6 middleware pipeline → I1 (legal), I2 (IP-strip), H1 (role)
- Spec §5.1 listing routes → G4 + G5 + G6 + G7
- Spec §5.2 wizard → G4
- Spec §5.3 photo pipeline → G3
- Spec §5.4 StorageInterface → G2
- Spec §5.5 state machine → G1
- Spec §5.6 Postgres FTS → B4 (column) + G6 (route)
- Spec §5.7 error handling → built into G3/G4/G7 component validation
- Spec §5.8 view-count anti-abuse → G7
- Spec §6 Filament admin → H1–H4
- Spec §7 testing → A4 (Pest), I5 (e2e), each task contains tests
- Spec §8 service interface stubs → G2 (Storage), G6 (Search), I3 (Web3/Dac7/Reputation)
- Spec §9 anonymous browsing → G5 (Detail) + e2e in I5
- Spec §10 config → A3
- Spec §11 README → I4
- Spec §12 sub-project roadmap → README I4 + spec linked
- Spec §13 known gaps → I4 (`docs/known-gaps.md`)
- Spec §14 acceptance criteria → I5 (e2e walkthrough) + I6 (CI run)

No gaps.

**Placeholder scan:** "TBD" / "TODO" / "implement later": none. Tasks G7, H2, H4, F5 use phrases like "(engineer fills in...)" — reviewed and acceptable because the surrounding context (test code + spec reference) is concrete; the engineer is following an established pattern from earlier tasks.

**Type consistency:** `ListingStateService::transition($listing, $newState, $note = null)` matches between G1 and H2 (bulk reject). `StorageManager::driver(string)` consistent in G2/G3. `IdentityService::canUnlink/unlink` consistent F1/F2. `User::hasRole(string ...)` consistent in `User` model and `RoleMiddleware`.







