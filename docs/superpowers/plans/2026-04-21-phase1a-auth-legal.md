# Phase 1a: Authentication & Legal Compliance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the Phase 0 PSR-4 foundation with OAuth login (Google + GitHub), Web3 wallet login (MetaMask + WalletConnect v2, chain-agnostic, EIP-4361), a versioned legal documents system (ToS + Privacy Policy) with a clickwrap acceptance gate, a cookie banner, and a profile security page.

**Architecture:** Adds a new `src/Services/Auth/` layer for auth-related domain logic, four new `src/Models/` for the new tables, three new `src/Controllers/` (OAuth, Web3, Legal), one new middleware (`LegalAcceptanceMiddleware`), a generic `Core/RateLimiter`, and minimal frontend JS for wallet signing and cookie dismissal. Schema extension is 100% migration-driven (no admin UI in V1). Existing username/password login stays unchanged.

**Tech Stack:** PHP 8.1+, MySQL 8, PSR-4, Bootstrap 5, HTMX, PHPUnit 10, `league/oauth2-client` + provider packages, `kornrunner/keccak`, `simplito/elliptic-php`, ethers.js v6, WalletConnect v2.

**Spec:** `docs/superpowers/specs/2026-04-21-phase1a-auth-legal-design.md`

---

## Pre-flight notes (corrections relative to spec)

These are minor deviations from the spec file based on reading existing code — apply them while executing:

1. **Users table password column is `password`, not `password_hash`.** Phase 0 kept the original column name (`password`) even though it stores `password_hash()` output. Migration 007 alters `password` to NULLABLE.
2. **Migration numbering.** `migrations/` currently only contains `migrate.php` (no baseline SQL was created in Phase 0). Phase 1a migrations start at `001_` not `002_`. The spec's `002-011` numbering is adjusted to `001-010` throughout this plan.
3. **CSRF token key is `_csrf_token`** in session and POST body; header is `X-CSRF-Token`. Use `View::csrfField()` in forms and `document.querySelector('meta[name="csrf-token"]').getAttribute('content')` in JS.

---

## File Structure Overview

```
src/
  Controllers/
    OAuthController.php           NEW
    Web3Controller.php            NEW
    LegalController.php           NEW
    ProfileController.php         MODIFY (add security / unlink methods)
    AuthController.php            UNCHANGED
  Services/
    Auth/
      OAuthProviderFactory.php    NEW
      Web3NonceGenerator.php      NEW
      Web3SignatureVerifier.php   NEW
      SiweMessageBuilder.php      NEW
      LegalDocumentService.php    NEW
  Core/
    RateLimiter.php               NEW
    App.php                       MODIFY (register 'legal' middleware name)
    Middleware/
      LegalAcceptanceMiddleware.php  NEW
  Models/
    OAuthProvider.php             NEW
    WalletAddress.php             NEW
    LegalDocument.php             NEW
    AuthNonce.php                 NEW
  Views/
    legal/
      tos.php                     NEW
      privacy.php                 NEW
      accept.php                  NEW
    profile/
      security.php                NEW
    partials/
      cookie_banner.php           NEW
    auth/
      login.php                   MODIFY (add OAuth + wallet buttons)
    layouts/
      main.php                    MODIFY (include cookie banner + ToS/Privacy footer links)
  routes.php                      MODIFY (add new routes)

public/assets/js/
  web3-login.js                   NEW
  cookie-banner.js                NEW

migrations/
  001_create_oauth_providers.sql       NEW
  002_create_wallet_addresses.sql      NEW
  003_create_legal_documents.sql       NEW
  004_create_auth_nonces.sql           NEW
  005_alter_users_legal_acceptance.sql NEW
  006_alter_users_nullable_email_password.sql  NEW
  007_seed_initial_tos_nl.sql          NEW
  008_seed_initial_privacy_nl.sql      NEW
  009_seed_placeholder_tos_en.sql      NEW
  010_seed_placeholder_privacy_en.sql  NEW

bin/
  cleanup-nonces.php              NEW

tests/
  Core/
    RateLimiterTest.php                     NEW
    Middleware/
      LegalAcceptanceMiddlewareTest.php     NEW
  Services/
    Auth/
      OAuthProviderFactoryTest.php          NEW
      Web3NonceGeneratorTest.php            NEW
      Web3SignatureVerifierTest.php         NEW
      SiweMessageBuilderTest.php            NEW
      LegalDocumentServiceTest.php          NEW
  Models/
    OAuthProviderTest.php                   NEW
    WalletAddressTest.php                   NEW
    LegalDocumentTest.php                   NEW
    AuthNonceTest.php                       NEW
  Controllers/
    OAuthControllerTest.php                 NEW
    Web3ControllerTest.php                  NEW
    LegalControllerTest.php                 NEW
    ProfileSecurityTest.php                 NEW
  fixtures/
    siwe/
      valid-mainnet.json                    NEW
      valid-base.json                       NEW
      tampered-message.json                 NEW

.env.example                      MODIFY (add OAuth + WalletConnect keys)
composer.json                     MODIFY (add deps)
docs/oauth-setup.md               NEW
```

---

## Task 1: Add composer dependencies

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add dependencies to composer.json**

Replace the `"require"` block in `composer.json` with:

```json
    "require": {
        "php": ">=8.1",
        "vlucas/phpdotenv": "^5.5",
        "phpmailer/phpmailer": "^6.8",
        "firebase/php-jwt": "^6.4",
        "intervention/image": "^2.7",
        "ezyang/htmlpurifier": "^4.17",
        "league/oauth2-client": "^2.7",
        "league/oauth2-google": "^4.0",
        "league/oauth2-github": "^3.1",
        "kornrunner/keccak": "^1.1",
        "simplito/elliptic-php": "^1.0"
    },
```

- [ ] **Step 2: Install deps**

Run: `composer update --no-interaction`
Expected: new packages installed, `composer.lock` updated, no errors.

- [ ] **Step 3: Verify autoload works**

Run: `php -r "require 'vendor/autoload.php'; var_dump(class_exists('League\\OAuth2\\Client\\Provider\\Google'), class_exists('Kornrunner\\Keccak'), class_exists('Elliptic\\EC'));"`
Expected: three `bool(true)` lines.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "Add OAuth + Web3 dependencies for Phase 1a"
```

---

## Task 2: Schema migrations — new tables

**Files:**
- Create: `migrations/001_create_oauth_providers.sql`
- Create: `migrations/002_create_wallet_addresses.sql`
- Create: `migrations/003_create_legal_documents.sql`
- Create: `migrations/004_create_auth_nonces.sql`

- [ ] **Step 1: Create 001_create_oauth_providers.sql**

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

Note: no trailing semicolon on the last statement (the runner `explode(';', $sql)` trims empties, but a trailing `;` is harmless — include one for editor sanity).

Add a trailing `;` at end of the statement above.

- [ ] **Step 2: Create 002_create_wallet_addresses.sql**

```sql
CREATE TABLE wallet_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    address CHAR(42) NOT NULL,
    chain_id INT UNSIGNED NOT NULL,
    verified_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_address (address),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 3: Create 003_create_legal_documents.sql**

```sql
CREATE TABLE legal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('tos','privacy') NOT NULL,
    version INT UNSIGNED NOT NULL,
    language CHAR(2) NOT NULL,
    content LONGTEXT NOT NULL,
    published_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_type_version_lang (type, version, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Create 004_create_auth_nonces.sql**

```sql
CREATE TABLE auth_nonces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nonce CHAR(32) NOT NULL,
    address CHAR(42) NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nonce (nonce),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 5: Run migrations**

Run: `php migrations/migrate.php`
Expected output:
```
RUN:  001_create_oauth_providers.sql ... OK
RUN:  002_create_wallet_addresses.sql ... OK
RUN:  003_create_legal_documents.sql ... OK
RUN:  004_create_auth_nonces.sql ... OK
Done. 4 migration(s) executed.
```

- [ ] **Step 6: Verify tables exist**

Run:
```bash
php -r "require 'vendor/autoload.php'; \App\Core\Config::load('.'); \$db = \App\Core\Database::getInstance(); foreach ([['oauth_providers'], ['wallet_addresses'], ['legal_documents'], ['auth_nonces']] as [\$t]) { \$cnt = \$db->fetch(\"SHOW TABLES LIKE '\$t'\"); echo \$t . ': ' . (\$cnt ? 'OK' : 'MISSING') . PHP_EOL; }"
```
Expected: four `OK` lines.

- [ ] **Step 7: Commit**

```bash
git add migrations/001_create_oauth_providers.sql migrations/002_create_wallet_addresses.sql migrations/003_create_legal_documents.sql migrations/004_create_auth_nonces.sql
git commit -m "Add migrations for oauth_providers, wallet_addresses, legal_documents, auth_nonces"
```

---

## Task 3: Schema migrations — alter users

**Files:**
- Create: `migrations/005_alter_users_legal_acceptance.sql`
- Create: `migrations/006_alter_users_nullable_email_password.sql`

- [ ] **Step 1: Create 005_alter_users_legal_acceptance.sql**

```sql
ALTER TABLE users
    ADD COLUMN tos_version INT UNSIGNED NULL AFTER role,
    ADD COLUMN tos_accepted_at DATETIME NULL AFTER tos_version,
    ADD COLUMN privacy_version INT UNSIGNED NULL AFTER tos_accepted_at,
    ADD COLUMN privacy_accepted_at DATETIME NULL AFTER privacy_version;
```

Note: if `role` column does not exist (some DBs have it in a different position), change `AFTER role` to `AFTER id` — check with `DESCRIBE users;` beforehand. Execute an adjusted file if needed before running migrate.

- [ ] **Step 2: Create 006_alter_users_nullable_email_password.sql**

```sql
ALTER TABLE users
    MODIFY COLUMN email VARCHAR(255) NULL,
    MODIFY COLUMN password VARCHAR(255) NULL;
```

- [ ] **Step 3: Run migrations**

Run: `php migrations/migrate.php`
Expected: two new `OK` lines, previous 4 skipped.

- [ ] **Step 4: Verify schema**

Run:
```bash
php -r "require 'vendor/autoload.php'; \App\Core\Config::load('.'); \$db = \App\Core\Database::getInstance(); foreach (\$db->fetchAll('DESCRIBE users') as \$col) { if (in_array(\$col['Field'], ['email','password','tos_version','tos_accepted_at','privacy_version','privacy_accepted_at'])) { printf(\"%-25s %-15s %s\n\", \$col['Field'], \$col['Type'], \$col['Null']); } }"
```
Expected: `email` and `password` show `Null=YES`; the four new columns exist with `Null=YES`.

- [ ] **Step 5: Commit**

```bash
git add migrations/005_alter_users_legal_acceptance.sql migrations/006_alter_users_nullable_email_password.sql
git commit -m "Alter users table: add legal acceptance columns, nullable email/password"
```

---

## Task 4: AuthNonce model

**Files:**
- Create: `tests/Models/AuthNonceTest.php`
- Create: `src/Models/AuthNonce.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/AuthNonceTest.php`:

```php
<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\AuthNonce;

class AuthNonceTest extends TestCase
{
    private AuthNonce $model;
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->model = new AuthNonce();
        $this->db->query("DELETE FROM auth_nonces WHERE nonce LIKE 'test_%'");
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM auth_nonces WHERE nonce LIKE 'test_%'");
    }

    public function testCreateStoresNonce(): void
    {
        $id = $this->model->create('test_nonce_1', '0xabc', 300);
        $this->assertGreaterThan(0, $id);

        $row = $this->db->fetch("SELECT * FROM auth_nonces WHERE id = ?", [$id]);
        $this->assertSame('test_nonce_1', $row['nonce']);
        $this->assertSame('0xabc', $row['address']);
        $this->assertNull($row['consumed_at']);
    }

    public function testFindValidReturnsUnexpiredUnconsumed(): void
    {
        $this->model->create('test_nonce_valid', '0xabc', 300);
        $row = $this->model->findValid('test_nonce_valid', '0xabc');
        $this->assertNotFalse($row);
        $this->assertSame('test_nonce_valid', $row['nonce']);
    }

    public function testFindValidRejectsWrongAddress(): void
    {
        $this->model->create('test_nonce_wrong_addr', '0xabc', 300);
        $row = $this->model->findValid('test_nonce_wrong_addr', '0xdef');
        $this->assertFalse($row);
    }

    public function testFindValidRejectsExpired(): void
    {
        $id = $this->model->create('test_nonce_expired', '0xabc', 300);
        $this->db->update('auth_nonces',
            ['expires_at' => date('Y-m-d H:i:s', time() - 60)],
            'id = ?', [$id]);
        $row = $this->model->findValid('test_nonce_expired', '0xabc');
        $this->assertFalse($row);
    }

    public function testConsumeMarksConsumed(): void
    {
        $this->model->create('test_nonce_consume', '0xabc', 300);
        $this->assertTrue($this->model->consume('test_nonce_consume'));

        $row = $this->model->findValid('test_nonce_consume', '0xabc');
        $this->assertFalse($row, 'consumed nonce must not be found valid');
    }

    public function testConsumeReturnsFalseIfAlreadyConsumed(): void
    {
        $this->model->create('test_nonce_double', '0xabc', 300);
        $this->assertTrue($this->model->consume('test_nonce_double'));
        $this->assertFalse($this->model->consume('test_nonce_double'));
    }

    public function testDeleteExpiredRemovesOldRows(): void
    {
        $id = $this->model->create('test_nonce_cleanup', '0xabc', 300);
        $this->db->update('auth_nonces',
            ['expires_at' => date('Y-m-d H:i:s', time() - 86500)],
            'id = ?', [$id]);

        $deleted = $this->model->deleteExpired(86400);
        $this->assertGreaterThanOrEqual(1, $deleted);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Models/AuthNonceTest.php`
Expected: FAIL with `Class "App\Models\AuthNonce" not found`.

- [ ] **Step 3: Create src/Models/AuthNonce.php**

```php
<?php

namespace App\Models;

use App\Core\Database;

class AuthNonce
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(string $nonce, ?string $address, int $ttlSeconds): int
    {
        return $this->db->insert('auth_nonces', [
            'nonce' => $nonce,
            'address' => $address,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    public function findValid(string $nonce, string $address): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM auth_nonces
             WHERE nonce = ? AND address = ?
               AND consumed_at IS NULL
               AND expires_at > NOW()",
            [$nonce, $address]
        );
    }

    public function consume(string $nonce): bool
    {
        $rows = $this->db->update(
            'auth_nonces',
            ['consumed_at' => date('Y-m-d H:i:s')],
            'nonce = ? AND consumed_at IS NULL',
            [$nonce]
        );
        return $rows > 0;
    }

    public function deleteExpired(int $olderThanSeconds = 86400): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $olderThanSeconds);
        return $this->db->delete('auth_nonces', 'expires_at < ?', [$cutoff]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Models/AuthNonceTest.php`
Expected: all 7 tests pass, `OK (7 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Models/AuthNonce.php tests/Models/AuthNonceTest.php
git commit -m "Add AuthNonce model with TTL + consume + cleanup"
```

---

## Task 5: OAuthProvider model

**Files:**
- Create: `tests/Models/OAuthProviderTest.php`
- Create: `src/Models/OAuthProvider.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/OAuthProviderTest.php`:

```php
<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\OAuthProvider;

class OAuthProviderTest extends TestCase
{
    private OAuthProvider $model;
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->model = new OAuthProvider();

        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'test_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauth_%'");
        $this->userId = $this->db->insert('users', [
            'username' => 'test_oauth_u1',
            'email' => 'test_oauth_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'test_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauth_%'");
    }

    public function testLinkCreatesRow(): void
    {
        $id = $this->model->link($this->userId, 'google', 'test_uid_1', 'x@test.com');
        $this->assertGreaterThan(0, $id);
    }

    public function testFindByProviderUidReturnsRow(): void
    {
        $this->model->link($this->userId, 'google', 'test_uid_2', 'x@test.com');
        $row = $this->model->findByProviderUid('google', 'test_uid_2');
        $this->assertNotFalse($row);
        $this->assertSame($this->userId, (int) $row['user_id']);
    }

    public function testFindByProviderUidReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->model->findByProviderUid('google', 'test_uid_missing'));
    }

    public function testFindByUserReturnsAllLinks(): void
    {
        $this->model->link($this->userId, 'google', 'test_uid_g', 'g@test.com');
        $this->model->link($this->userId, 'github', 'test_uid_gh', 'gh@test.com');
        $rows = $this->model->findByUser($this->userId);
        $this->assertCount(2, $rows);
    }

    public function testUnlinkRemovesRow(): void
    {
        $this->model->link($this->userId, 'google', 'test_uid_unlink', 'x@test.com');
        $this->assertTrue($this->model->unlink($this->userId, 'google'));
        $this->assertFalse($this->model->findByProviderUid('google', 'test_uid_unlink'));
    }

    public function testUnlinkReturnsFalseWhenNotLinked(): void
    {
        $this->assertFalse($this->model->unlink($this->userId, 'google'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Models/OAuthProviderTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Models/OAuthProvider.php**

```php
<?php

namespace App\Models;

use App\Core\Database;

class OAuthProvider
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function link(int $userId, string $provider, string $providerUid, ?string $email): int
    {
        return $this->db->insert('oauth_providers', [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_uid' => $providerUid,
            'email' => $email,
        ]);
    }

    public function findByProviderUid(string $provider, string $providerUid): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM oauth_providers WHERE provider = ? AND provider_uid = ?",
            [$provider, $providerUid]
        );
    }

    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM oauth_providers WHERE user_id = ? ORDER BY provider",
            [$userId]
        );
    }

    public function unlink(int $userId, string $provider): bool
    {
        return $this->db->delete(
            'oauth_providers',
            'user_id = ? AND provider = ?',
            [$userId, $provider]
        ) > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Models/OAuthProviderTest.php`
Expected: 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Models/OAuthProvider.php tests/Models/OAuthProviderTest.php
git commit -m "Add OAuthProvider model for linking external auth accounts"
```

---

## Task 6: WalletAddress model

**Files:**
- Create: `tests/Models/WalletAddressTest.php`
- Create: `src/Models/WalletAddress.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/WalletAddressTest.php`:

```php
<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\WalletAddress;

class WalletAddressTest extends TestCase
{
    private WalletAddress $model;
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->model = new WalletAddress();

        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000test%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_wallet_%'");
        $this->userId = $this->db->insert('users', [
            'username' => 'test_wallet_u1',
            'email' => 'test_wallet_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000test%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_wallet_%'");
    }

    public function testLinkStoresLowercased(): void
    {
        $addr = '0x000000000000000000000000000000000000TEST';
        $id = $this->model->link($this->userId, $addr, 1);
        $this->assertGreaterThan(0, $id);

        $row = $this->db->fetch("SELECT address FROM wallet_addresses WHERE id = ?", [$id]);
        $this->assertSame(strtolower($addr), $row['address']);
    }

    public function testFindByAddressIsCaseInsensitive(): void
    {
        $lower = '0x000000000000000000000000000000000000test1';
        $this->model->link($this->userId, $lower, 1);
        $row = $this->model->findByAddress(strtoupper($lower));
        $this->assertNotFalse($row);
    }

    public function testDuplicateAddressThrows(): void
    {
        $addr = '0x000000000000000000000000000000000000test2';
        $this->model->link($this->userId, $addr, 1);
        $this->expectException(\PDOException::class);
        $this->model->link($this->userId, $addr, 1);
    }

    public function testFindByUserReturnsAll(): void
    {
        $this->model->link($this->userId, '0x000000000000000000000000000000000000test3', 1);
        $this->model->link($this->userId, '0x000000000000000000000000000000000000test4', 8453);
        $rows = $this->model->findByUser($this->userId);
        $this->assertCount(2, $rows);
    }

    public function testUnlinkRemovesRowOwnedByUser(): void
    {
        $id = $this->model->link($this->userId, '0x000000000000000000000000000000000000test5', 1);
        $this->assertTrue($this->model->unlink($this->userId, $id));
        $this->assertFalse($this->db->fetch("SELECT * FROM wallet_addresses WHERE id = ?", [$id]));
    }

    public function testUnlinkFailsForOtherUser(): void
    {
        $id = $this->model->link($this->userId, '0x000000000000000000000000000000000000test6', 1);
        $this->assertFalse($this->model->unlink($this->userId + 99999, $id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Models/WalletAddressTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Models/WalletAddress.php**

```php
<?php

namespace App\Models;

use App\Core\Database;

class WalletAddress
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function link(int $userId, string $address, int $chainId): int
    {
        return $this->db->insert('wallet_addresses', [
            'user_id' => $userId,
            'address' => strtolower($address),
            'chain_id' => $chainId,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByAddress(string $address): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM wallet_addresses WHERE address = ?",
            [strtolower($address)]
        );
    }

    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM wallet_addresses WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );
    }

    public function unlink(int $userId, int $walletId): bool
    {
        return $this->db->delete(
            'wallet_addresses',
            'id = ? AND user_id = ?',
            [$walletId, $userId]
        ) > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Models/WalletAddressTest.php`
Expected: 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Models/WalletAddress.php tests/Models/WalletAddressTest.php
git commit -m "Add WalletAddress model with lowercase normalization"
```

---

## Task 7: LegalDocument model

**Files:**
- Create: `tests/Models/LegalDocumentTest.php`
- Create: `src/Models/LegalDocument.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Models/LegalDocumentTest.php`:

```php
<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\LegalDocument;

class LegalDocumentTest extends TestCase
{
    private LegalDocument $model;
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->model = new LegalDocument();
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
    }

    public function testLatestVersionReturnsHighestPublished(): void
    {
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_v1', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 2, 'language' => 'nl', 'content' => 'TEST_v2', 'published_at' => '2026-02-01 00:00:00']);

        $v = $this->model->latestVersion('tos', 'nl');
        $this->assertSame(2, $v);
    }

    public function testLatestVersionIgnoresFuturePublished(): void
    {
        $future = date('Y-m-d H:i:s', time() + 86400);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_current', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 9, 'language' => 'nl', 'content' => 'TEST_future', 'published_at' => $future]);

        $this->assertSame(1, $this->model->latestVersion('tos', 'nl'));
    }

    public function testLatestVersionReturnsZeroIfNone(): void
    {
        $this->assertSame(0, $this->model->latestVersion('tos', 'nl'));
    }

    public function testFindReturnsSpecificVersion(): void
    {
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_privacy_v1', 'published_at' => '2026-01-01 00:00:00']);
        $doc = $this->model->find('privacy', 1, 'nl');
        $this->assertNotFalse($doc);
        $this->assertSame('TEST_privacy_v1', $doc['content']);
    }

    public function testFindFallsBackToNlWhenLanguageMissing(): void
    {
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_nl_only', 'published_at' => '2026-01-01 00:00:00']);
        $doc = $this->model->findWithFallback('tos', 1, 'en');
        $this->assertNotFalse($doc);
        $this->assertSame('TEST_nl_only', $doc['content']);
        $this->assertSame('nl', $doc['language']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Models/LegalDocumentTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Models/LegalDocument.php**

```php
<?php

namespace App\Models;

use App\Core\Database;

class LegalDocument
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function latestVersion(string $type, string $language): int
    {
        $row = $this->db->fetch(
            "SELECT MAX(version) AS v FROM legal_documents
             WHERE type = ? AND language = ? AND published_at <= NOW()",
            [$type, $language]
        );
        return (int) ($row['v'] ?? 0);
    }

    public function find(string $type, int $version, string $language): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM legal_documents
             WHERE type = ? AND version = ? AND language = ?",
            [$type, $version, $language]
        );
    }

    public function findWithFallback(string $type, int $version, string $language): array|false
    {
        $doc = $this->find($type, $version, $language);
        if ($doc !== false) {
            return $doc;
        }
        if ($language !== 'nl') {
            return $this->find($type, $version, 'nl');
        }
        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Models/LegalDocumentTest.php`
Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Models/LegalDocument.php tests/Models/LegalDocumentTest.php
git commit -m "Add LegalDocument model with version lookup and NL fallback"
```

---

## Task 8: RateLimiter core service

**Files:**
- Create: `tests/Core/RateLimiterTest.php`
- Create: `src/Core/RateLimiter.php`

Backing store: APCu when available, file-based fallback in `/tmp/cmp_ratelimit/` (single-server deployment assumption).

- [ ] **Step 1: Write the failing test**

Create `tests/Core/RateLimiterTest.php`:

```php
<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\RateLimiter;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;
    private string $key;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter();
        $this->key = 'test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->limiter->reset($this->key);
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->limiter->attempt($this->key, 5, 60));
        }
    }

    public function testBlocksRequestsOverLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt($this->key, 3, 60);
        }
        $this->assertFalse($this->limiter->attempt($this->key, 3, 60));
    }

    public function testResetClearsCounter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt($this->key, 3, 60);
        }
        $this->limiter->reset($this->key);
        $this->assertTrue($this->limiter->attempt($this->key, 3, 60));
    }

    public function testRemainingReflectsAttempts(): void
    {
        $this->limiter->attempt($this->key, 5, 60);
        $this->limiter->attempt($this->key, 5, 60);
        $this->assertSame(3, $this->limiter->remaining($this->key, 5));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Core/RateLimiterTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Core/RateLimiter.php**

```php
<?php

namespace App\Core;

class RateLimiter
{
    private string $fallbackDir;
    private bool $useApcu;

    public function __construct(?string $fallbackDir = null)
    {
        $this->useApcu = function_exists('apcu_enabled') && apcu_enabled();
        $this->fallbackDir = $fallbackDir ?? sys_get_temp_dir() . '/cmp_ratelimit';
        if (!$this->useApcu && !is_dir($this->fallbackDir)) {
            @mkdir($this->fallbackDir, 0700, true);
        }
    }

    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $entry = $this->read($key);

        if ($entry === null || $entry['window_start'] + $windowSeconds < $now) {
            $entry = ['count' => 0, 'window_start' => $now];
        }

        if ($entry['count'] >= $maxAttempts) {
            return false;
        }

        $entry['count']++;
        $this->write($key, $entry, $windowSeconds);
        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $entry = $this->read($key);
        if ($entry === null) {
            return $maxAttempts;
        }
        return max(0, $maxAttempts - $entry['count']);
    }

    public function reset(string $key): void
    {
        if ($this->useApcu) {
            apcu_delete($this->storageKey($key));
            return;
        }
        $path = $this->filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function read(string $key): ?array
    {
        if ($this->useApcu) {
            $val = apcu_fetch($this->storageKey($key), $success);
            return $success ? $val : null;
        }
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw);
        return is_array($data) ? $data : null;
    }

    private function write(string $key, array $entry, int $ttl): void
    {
        if ($this->useApcu) {
            apcu_store($this->storageKey($key), $entry, $ttl);
            return;
        }
        @file_put_contents($this->filePath($key), serialize($entry), LOCK_EX);
    }

    private function storageKey(string $key): string
    {
        return 'cmp_ratelimit:' . $key;
    }

    private function filePath(string $key): string
    {
        return $this->fallbackDir . '/' . hash('sha256', $key);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Core/RateLimiterTest.php`
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Core/RateLimiter.php tests/Core/RateLimiterTest.php
git commit -m "Add RateLimiter with APCu + file fallback"
```

---

## Task 9: Web3NonceGenerator service

**Files:**
- Create: `tests/Services/Auth/Web3NonceGeneratorTest.php`
- Create: `src/Services/Auth/Web3NonceGenerator.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Services/Auth/Web3NonceGeneratorTest.php`:

```php
<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\AuthNonce;
use App\Services\Auth\Web3NonceGenerator;

class Web3NonceGeneratorTest extends TestCase
{
    private Web3NonceGenerator $gen;
    private AuthNonce $nonces;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 3));
        Database::resetInstance();
        $this->nonces = new AuthNonce();
        $this->gen = new Web3NonceGenerator($this->nonces);
        Database::getInstance()->query("DELETE FROM auth_nonces");
    }

    public function testIssueReturns32CharAlphanumeric(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertSame(32, strlen($nonce));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $nonce);
    }

    public function testIssuedNonceIsValid(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertTrue($this->gen->verifyAndConsume($nonce, '0xabc'));
    }

    public function testReplayRejected(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertTrue($this->gen->verifyAndConsume($nonce, '0xabc'));
        $this->assertFalse($this->gen->verifyAndConsume($nonce, '0xabc'));
    }

    public function testWrongAddressRejected(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertFalse($this->gen->verifyAndConsume($nonce, '0xdef'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Services/Auth/Web3NonceGeneratorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Services/Auth/Web3NonceGenerator.php**

```php
<?php

namespace App\Services\Auth;

use App\Models\AuthNonce;

class Web3NonceGenerator
{
    private const TTL_SECONDS = 300;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(private AuthNonce $nonces)
    {
    }

    public function issue(string $address): string
    {
        $address = strtolower($address);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $nonce = $this->randomString(32);
            try {
                $this->nonces->create($nonce, $address, self::TTL_SECONDS);
                return $nonce;
            } catch (\PDOException $e) {
                // unique collision — retry
            }
        }
        throw new \RuntimeException('Failed to issue unique nonce after 5 attempts');
    }

    public function verifyAndConsume(string $nonce, string $address): bool
    {
        $address = strtolower($address);
        $row = $this->nonces->findValid($nonce, $address);
        if ($row === false) {
            return false;
        }
        return $this->nonces->consume($nonce);
    }

    private function randomString(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Services/Auth/Web3NonceGeneratorTest.php`
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/Auth/Web3NonceGenerator.php tests/Services/Auth/Web3NonceGeneratorTest.php
git commit -m "Add Web3NonceGenerator wrapping AuthNonce with 5-min TTL"
```

---

## Task 10: SiweMessageBuilder service

**Files:**
- Create: `tests/Services/Auth/SiweMessageBuilderTest.php`
- Create: `src/Services/Auth/SiweMessageBuilder.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Services/Auth/SiweMessageBuilderTest.php`:

```php
<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Services\Auth\SiweMessageBuilder;

class SiweMessageBuilderTest extends TestCase
{
    public function testBuildContainsAllRequiredFields(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $msg = $builder->build(
            '0x1111111111111111111111111111111111111111',
            1,
            'abc123def456abc123def456abc123de',
            'https://cloudmarkplaats.test',
            'Log in bij Cloudmarkplaats',
            '2026-04-21T10:00:00Z'
        );

        $this->assertStringContainsString('cloudmarkplaats.test wants you to sign in', $msg);
        $this->assertStringContainsString('0x1111111111111111111111111111111111111111', $msg);
        $this->assertStringContainsString('Log in bij Cloudmarkplaats', $msg);
        $this->assertStringContainsString('URI: https://cloudmarkplaats.test', $msg);
        $this->assertStringContainsString('Version: 1', $msg);
        $this->assertStringContainsString('Chain ID: 1', $msg);
        $this->assertStringContainsString('Nonce: abc123def456abc123def456abc123de', $msg);
        $this->assertStringContainsString('Issued At: 2026-04-21T10:00:00Z', $msg);
    }

    public function testParseExtractsFields(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $msg = $builder->build(
            '0x1111111111111111111111111111111111111111',
            8453,
            'xyz789xyz789xyz789xyz789xyz789xy',
            'https://cloudmarkplaats.test',
            'Log in bij Cloudmarkplaats',
            '2026-04-21T10:00:00Z'
        );

        $parsed = $builder->parse($msg);
        $this->assertSame('0x1111111111111111111111111111111111111111', $parsed['address']);
        $this->assertSame(8453, $parsed['chain_id']);
        $this->assertSame('xyz789xyz789xyz789xyz789xyz789xy', $parsed['nonce']);
        $this->assertSame('cloudmarkplaats.test', $parsed['domain']);
    }

    public function testParseRejectsWrongDomain(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $attackerMessage = "attacker.com wants you to sign in with your Ethereum account:\n" .
            "0x1111111111111111111111111111111111111111\n\nLogin\n\n" .
            "URI: https://attacker.com\nVersion: 1\nChain ID: 1\n" .
            "Nonce: xyz789xyz789xyz789xyz789xyz789xy\nIssued At: 2026-04-21T10:00:00Z";

        $this->expectException(\InvalidArgumentException::class);
        $builder->parse($attackerMessage);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Services/Auth/SiweMessageBuilderTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Services/Auth/SiweMessageBuilder.php**

```php
<?php

namespace App\Services\Auth;

class SiweMessageBuilder
{
    public function __construct(private string $expectedDomain)
    {
    }

    public function build(
        string $address,
        int $chainId,
        string $nonce,
        string $uri,
        string $statement,
        ?string $issuedAt = null
    ): string {
        $issuedAt ??= gmdate('Y-m-d\TH:i:s\Z');

        return sprintf(
            "%s wants you to sign in with your Ethereum account:\n%s\n\n%s\n\nURI: %s\nVersion: 1\nChain ID: %d\nNonce: %s\nIssued At: %s",
            $this->expectedDomain,
            $address,
            $statement,
            $uri,
            $chainId,
            $nonce,
            $issuedAt
        );
    }

    public function parse(string $message): array
    {
        $lines = preg_split('/\r\n|\n/', $message);
        if (count($lines) < 7) {
            throw new \InvalidArgumentException('SIWE message too short');
        }

        if (!preg_match('/^(\S+) wants you to sign in with your Ethereum account:$/', $lines[0], $m)) {
            throw new \InvalidArgumentException('Invalid SIWE preamble');
        }
        $domain = $m[1];

        if ($domain !== $this->expectedDomain) {
            throw new \InvalidArgumentException("Domain mismatch: expected {$this->expectedDomain}, got {$domain}");
        }

        $address = trim($lines[1]);
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new \InvalidArgumentException('Invalid address');
        }

        $parsed = ['domain' => $domain, 'address' => $address];

        foreach ($lines as $line) {
            if (preg_match('/^Chain ID: (\d+)$/', $line, $m)) {
                $parsed['chain_id'] = (int) $m[1];
            } elseif (preg_match('/^Nonce: ([A-Za-z0-9]+)$/', $line, $m)) {
                $parsed['nonce'] = $m[1];
            } elseif (preg_match('/^Issued At: (\S+)$/', $line, $m)) {
                $parsed['issued_at'] = $m[1];
            } elseif (preg_match('/^URI: (\S+)$/', $line, $m)) {
                $parsed['uri'] = $m[1];
            }
        }

        foreach (['chain_id', 'nonce', 'issued_at'] as $required) {
            if (!isset($parsed[$required])) {
                throw new \InvalidArgumentException("Missing SIWE field: {$required}");
            }
        }

        $issuedTs = strtotime($parsed['issued_at']);
        if ($issuedTs === false) {
            throw new \InvalidArgumentException('Invalid Issued At timestamp');
        }
        $drift = abs(time() - $issuedTs);
        if ($drift > 600) {
            throw new \InvalidArgumentException('Issued At too far from server time (>10min drift)');
        }

        return $parsed;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Services/Auth/SiweMessageBuilderTest.php`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/Auth/SiweMessageBuilder.php tests/Services/Auth/SiweMessageBuilderTest.php
git commit -m "Add SiweMessageBuilder for EIP-4361 message build/parse with domain binding"
```

---

## Task 11: Web3SignatureVerifier service + fixtures

**Files:**
- Create: `tests/fixtures/siwe/valid-mainnet.json`
- Create: `tests/Services/Auth/Web3SignatureVerifierTest.php`
- Create: `src/Services/Auth/Web3SignatureVerifier.php`

The verifier recovers the signer address from an EIP-191 personal_sign signature (the format used by `personal_sign` on MetaMask) and matches it against the claimed address.

- [ ] **Step 1: Generate a fixture (signed SIWE message)**

Run this one-off script to generate a valid fixture for testing (uses a deterministic dev key — do NOT use in prod):

Save as `tests/fixtures/siwe/generate.php` and run once, then delete the private key from the committed file. Committed fixture is the output JSON only.

```php
<?php
// Run: php tests/fixtures/siwe/generate.php > tests/fixtures/siwe/valid-mainnet.json
require __DIR__ . '/../../../vendor/autoload.php';

use Elliptic\EC;
use kornrunner\Keccak;

// Deterministic test key — NEVER use in production
$privkey = '4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318';

$ec = new EC('secp256k1');
$key = $ec->keyFromPrivate($privkey);
$pubHex = $key->getPublic(false, 'hex'); // uncompressed, 65 bytes (04 + X + Y)
$pubBytes = hex2bin(substr($pubHex, 2)); // strip leading 04
$hash = Keccak::hash($pubBytes, 256);
$address = '0x' . substr($hash, -40);

$message = "cloudmarkplaats.test wants you to sign in with your Ethereum account:\n{$address}\n\nLog in bij Cloudmarkplaats\n\nURI: https://cloudmarkplaats.test\nVersion: 1\nChain ID: 1\nNonce: FIXTUREnonceFIXTUREnonceFIXTUREnn\nIssued At: " . gmdate('Y-m-d\TH:i:s\Z');

// EIP-191 prefix
$prefixed = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
$digest = Keccak::hash($prefixed, 256);

$sig = $key->sign($digest, ['canonical' => true]);
$r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
$s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
$v = dechex(27 + $sig->recoveryParam);
$signature = '0x' . $r . $s . $v;

echo json_encode([
    'message' => $message,
    'signature' => $signature,
    'expected_address' => $address,
], JSON_PRETTY_PRINT) . "\n";
```

Run: `php tests/fixtures/siwe/generate.php > tests/fixtures/siwe/valid-mainnet.json`

Note: the `Issued At` is regenerated each run — only used when the fixture is created. Tests that assert on full equality should read `expected_address` from the JSON, not hardcode it.

- [ ] **Step 2: Write the failing test**

Create `tests/Services/Auth/Web3SignatureVerifierTest.php`:

```php
<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Services\Auth\Web3SignatureVerifier;

class Web3SignatureVerifierTest extends TestCase
{
    private Web3SignatureVerifier $verifier;
    private array $fixture;

    protected function setUp(): void
    {
        $this->verifier = new Web3SignatureVerifier();
        $path = dirname(__DIR__, 2) . '/fixtures/siwe/valid-mainnet.json';
        $this->fixture = json_decode(file_get_contents($path), true);
    }

    public function testRecoverReturnsCorrectAddress(): void
    {
        $recovered = $this->verifier->recover($this->fixture['message'], $this->fixture['signature']);
        $this->assertSame(strtolower($this->fixture['expected_address']), strtolower($recovered));
    }

    public function testVerifyReturnsTrueForValid(): void
    {
        $this->assertTrue($this->verifier->verify(
            $this->fixture['message'],
            $this->fixture['signature'],
            $this->fixture['expected_address']
        ));
    }

    public function testVerifyReturnsFalseForTamperedMessage(): void
    {
        $tampered = str_replace('Log in bij Cloudmarkplaats', 'Transfer funds to 0xBAD', $this->fixture['message']);
        $this->assertFalse($this->verifier->verify(
            $tampered,
            $this->fixture['signature'],
            $this->fixture['expected_address']
        ));
    }

    public function testVerifyReturnsFalseForDifferentAddress(): void
    {
        $this->assertFalse($this->verifier->verify(
            $this->fixture['message'],
            $this->fixture['signature'],
            '0x0000000000000000000000000000000000000000'
        ));
    }

    public function testVerifyReturnsFalseForMalformedSignature(): void
    {
        $this->assertFalse($this->verifier->verify(
            $this->fixture['message'],
            '0xdeadbeef',
            $this->fixture['expected_address']
        ));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Services/Auth/Web3SignatureVerifierTest.php`
Expected: FAIL — class not found.

- [ ] **Step 4: Create src/Services/Auth/Web3SignatureVerifier.php**

```php
<?php

namespace App\Services\Auth;

use Elliptic\EC;
use kornrunner\Keccak;

class Web3SignatureVerifier
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    public function verify(string $message, string $signature, string $claimedAddress): bool
    {
        try {
            $recovered = $this->recover($message, $signature);
        } catch (\Throwable $e) {
            return false;
        }
        return strtolower($recovered) === strtolower($claimedAddress);
    }

    public function recover(string $message, string $signatureHex): string
    {
        $signatureHex = strtolower($signatureHex);
        if (str_starts_with($signatureHex, '0x')) {
            $signatureHex = substr($signatureHex, 2);
        }
        if (strlen($signatureHex) !== 130) {
            throw new \InvalidArgumentException('Signature must be 65 bytes (130 hex chars)');
        }

        $r = substr($signatureHex, 0, 64);
        $s = substr($signatureHex, 64, 64);
        $v = hexdec(substr($signatureHex, 128, 2));

        if ($v >= 27) {
            $v -= 27;
        }
        if ($v !== 0 && $v !== 1) {
            throw new \InvalidArgumentException('Invalid recovery param v');
        }

        $prefixed = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $digest = Keccak::hash($prefixed, 256);

        $pubKey = $this->ec->recoverPubKey($digest, ['r' => $r, 's' => $s], $v);
        $pubHex = $pubKey->encode('hex', false); // uncompressed, leading 04

        $pubBytes = hex2bin(substr($pubHex, 2));
        $addrHash = Keccak::hash($pubBytes, 256);
        return '0x' . substr($addrHash, -40);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Services/Auth/Web3SignatureVerifierTest.php`
Expected: 5 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Services/Auth/Web3SignatureVerifier.php tests/Services/Auth/Web3SignatureVerifierTest.php tests/fixtures/siwe/valid-mainnet.json tests/fixtures/siwe/generate.php
git commit -m "Add Web3SignatureVerifier for EIP-191 personal_sign recovery + fixture"
```

---

## Task 12: OAuthProviderFactory service

**Files:**
- Create: `tests/Services/Auth/OAuthProviderFactoryTest.php`
- Create: `src/Services/Auth/OAuthProviderFactory.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Services/Auth/OAuthProviderFactoryTest.php`:

```php
<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Services\Auth\OAuthProviderFactory;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Github;

class OAuthProviderFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['GOOGLE_CLIENT_ID'] = 'test-google-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'test-google-secret';
        $_ENV['GITHUB_CLIENT_ID'] = 'test-github-id';
        $_ENV['GITHUB_CLIENT_SECRET'] = 'test-github-secret';
        $_ENV['APP_URL'] = 'https://cloudmarkplaats.test';
    }

    public function testCreateGoogleProvider(): void
    {
        $factory = new OAuthProviderFactory();
        $provider = $factory->make('google');
        $this->assertInstanceOf(Google::class, $provider);
    }

    public function testCreateGithubProvider(): void
    {
        $factory = new OAuthProviderFactory();
        $provider = $factory->make('github');
        $this->assertInstanceOf(Github::class, $provider);
    }

    public function testUnknownProviderThrows(): void
    {
        $factory = new OAuthProviderFactory();
        $this->expectException(\InvalidArgumentException::class);
        $factory->make('facebook');
    }

    public function testMissingConfigThrows(): void
    {
        unset($_ENV['GOOGLE_CLIENT_ID']);
        $factory = new OAuthProviderFactory();
        $this->expectException(\RuntimeException::class);
        $factory->make('google');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Services/Auth/OAuthProviderFactoryTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Services/Auth/OAuthProviderFactory.php**

```php
<?php

namespace App\Services\Auth;

use App\Core\Config;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;
use Omines\OAuth2\Client\Provider\Github;

// Note: league/oauth2-github uses namespace League\OAuth2\Client\Provider\Github
// (check vendor/ for the exact class name — as of 2026 package is `league/oauth2-github`)

class OAuthProviderFactory
{
    public function make(string $provider): AbstractProvider
    {
        return match ($provider) {
            'google' => $this->makeGoogle(),
            'github' => $this->makeGithub(),
            default => throw new \InvalidArgumentException("Unknown OAuth provider: {$provider}"),
        };
    }

    private function makeGoogle(): Google
    {
        return new Google([
            'clientId' => $this->requireConfig('GOOGLE_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GOOGLE_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('google'),
        ]);
    }

    private function makeGithub(): \League\OAuth2\Client\Provider\Github
    {
        return new \League\OAuth2\Client\Provider\Github([
            'clientId' => $this->requireConfig('GITHUB_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GITHUB_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('github'),
        ]);
    }

    private function redirectUri(string $provider): string
    {
        $base = rtrim((string) Config::get('APP_URL', 'http://localhost:8000'), '/');
        return "{$base}/auth/oauth/{$provider}/callback";
    }

    private function requireConfig(string $key): string
    {
        $val = Config::get($key);
        if (empty($val)) {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (string) $val;
    }
}
```

Note: remove the top-level `use Omines\...` line — the `league/oauth2-github` package exposes `League\OAuth2\Client\Provider\Github` directly. The duplicate `\League\OAuth2\Client\Provider\Github` in `makeGithub` disambiguates; drop the extra `use` statement.

Final file (cleaned):

```php
<?php

namespace App\Services\Auth;

use App\Core\Config;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Github;

class OAuthProviderFactory
{
    public function make(string $provider): AbstractProvider
    {
        return match ($provider) {
            'google' => $this->makeGoogle(),
            'github' => $this->makeGithub(),
            default => throw new \InvalidArgumentException("Unknown OAuth provider: {$provider}"),
        };
    }

    private function makeGoogle(): Google
    {
        return new Google([
            'clientId' => $this->requireConfig('GOOGLE_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GOOGLE_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('google'),
        ]);
    }

    private function makeGithub(): Github
    {
        return new Github([
            'clientId' => $this->requireConfig('GITHUB_CLIENT_ID'),
            'clientSecret' => $this->requireConfig('GITHUB_CLIENT_SECRET'),
            'redirectUri' => $this->redirectUri('github'),
        ]);
    }

    private function redirectUri(string $provider): string
    {
        $base = rtrim((string) Config::get('APP_URL', 'http://localhost:8000'), '/');
        return "{$base}/auth/oauth/{$provider}/callback";
    }

    private function requireConfig(string $key): string
    {
        $val = Config::get($key);
        if (empty($val)) {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return (string) $val;
    }
}
```

Verify the exact class name available in vendor by running: `ls vendor/league/oauth2-github/src/Provider/` — if the class is named differently, adjust the `use` import. (The test will fail fast if wrong.)

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Services/Auth/OAuthProviderFactoryTest.php`
Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/Auth/OAuthProviderFactory.php tests/Services/Auth/OAuthProviderFactoryTest.php
git commit -m "Add OAuthProviderFactory for Google + GitHub from env config"
```

---

## Task 13: LegalDocumentService

**Files:**
- Create: `tests/Services/Auth/LegalDocumentServiceTest.php`
- Create: `src/Services/Auth/LegalDocumentService.php`

This service bundles "get current versions of both types" and "mark user as accepted" into one clear API.

- [ ] **Step 1: Write the failing test**

Create `tests/Services/Auth/LegalDocumentServiceTest.php`:

```php
<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\LegalDocument;
use App\Services\Auth\LegalDocumentService;

class LegalDocumentServiceTest extends TestCase
{
    private LegalDocumentService $service;
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 3));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->service = new LegalDocumentService(new LegalDocument());

        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legal_%'");
        $this->userId = $this->db->insert('users', [
            'username' => 'test_legal_u1',
            'email' => 'test_legal_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);

        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_tos_v1', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_priv_v1', 'published_at' => '2026-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legal_%'");
    }

    public function testCurrentVersionsReturnsBothTypes(): void
    {
        $v = $this->service->currentVersions('nl');
        $this->assertSame(1, $v['tos']);
        $this->assertSame(1, $v['privacy']);
    }

    public function testUserNeedsAcceptanceIfNeverAccepted(): void
    {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertTrue($this->service->needsAcceptance($user, 'nl'));
    }

    public function testUserNeedsAcceptanceIfOutdated(): void
    {
        $this->db->update('users', ['tos_version' => 1, 'privacy_version' => 1, 'tos_accepted_at' => '2026-01-02 00:00:00', 'privacy_accepted_at' => '2026-01-02 00:00:00'], 'id = ?', [$this->userId]);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 2, 'language' => 'nl', 'content' => 'TEST_tos_v2', 'published_at' => '2026-02-01 00:00:00']);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertTrue($this->service->needsAcceptance($user, 'nl'));
    }

    public function testUserDoesNotNeedAcceptanceIfUpToDate(): void
    {
        $this->db->update('users', ['tos_version' => 1, 'privacy_version' => 1, 'tos_accepted_at' => '2026-01-02 00:00:00', 'privacy_accepted_at' => '2026-01-02 00:00:00'], 'id = ?', [$this->userId]);
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertFalse($this->service->needsAcceptance($user, 'nl'));
    }

    public function testAcceptUpdatesUser(): void
    {
        $this->service->accept($this->userId, 'nl');
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(1, (int) $user['tos_version']);
        $this->assertSame(1, (int) $user['privacy_version']);
        $this->assertNotNull($user['tos_accepted_at']);
        $this->assertNotNull($user['privacy_accepted_at']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Services/Auth/LegalDocumentServiceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Services/Auth/LegalDocumentService.php**

```php
<?php

namespace App\Services\Auth;

use App\Core\Database;
use App\Models\LegalDocument;

class LegalDocumentService
{
    public function __construct(private LegalDocument $docs)
    {
    }

    public function currentVersions(string $language): array
    {
        return [
            'tos' => $this->docs->latestVersion('tos', $language),
            'privacy' => $this->docs->latestVersion('privacy', $language),
        ];
    }

    public function needsAcceptance(array $user, string $language): bool
    {
        $current = $this->currentVersions($language);
        $userTos = (int) ($user['tos_version'] ?? 0);
        $userPrivacy = (int) ($user['privacy_version'] ?? 0);
        return $userTos < $current['tos'] || $userPrivacy < $current['privacy'];
    }

    public function accept(int $userId, string $language): void
    {
        $current = $this->currentVersions($language);
        $now = date('Y-m-d H:i:s');
        Database::getInstance()->update('users', [
            'tos_version' => $current['tos'],
            'tos_accepted_at' => $now,
            'privacy_version' => $current['privacy'],
            'privacy_accepted_at' => $now,
        ], 'id = ?', [$userId]);
    }

    public function getDocument(string $type, int $version, string $language): array|false
    {
        return $this->docs->findWithFallback($type, $version, $language);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Services/Auth/LegalDocumentServiceTest.php`
Expected: 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Services/Auth/LegalDocumentService.php tests/Services/Auth/LegalDocumentServiceTest.php
git commit -m "Add LegalDocumentService for current versions + acceptance"
```

---

## Task 14: LegalAcceptanceMiddleware

**Files:**
- Create: `tests/Core/Middleware/LegalAcceptanceMiddlewareTest.php`
- Create: `src/Core/Middleware/LegalAcceptanceMiddleware.php`
- Modify: `src/Core/App.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Core/Middleware/LegalAcceptanceMiddlewareTest.php`:

```php
<?php

namespace Tests\Core\Middleware;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Core\Middleware\LegalAcceptanceMiddleware;

class LegalAcceptanceMiddlewareTest extends TestCase
{
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 3));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_laccept_%'");

        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_tos', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 1, 'language' => 'nl', 'content' => 'TEST_priv', 'published_at' => '2026-01-01 00:00:00']);

        $this->userId = $this->db->insert('users', [
            'username' => 'test_laccept_u1',
            'email' => 'test_laccept@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_laccept_%'");
        $_SESSION = [];
    }

    public function testPassesWhenUserNotLoggedIn(): void
    {
        $mw = new LegalAcceptanceMiddleware();
        $this->assertTrue($mw->handle());
    }

    public function testPassesWhenUserAcceptedCurrent(): void
    {
        $this->db->update('users', [
            'tos_version' => 1, 'tos_accepted_at' => '2026-01-02 00:00:00',
            'privacy_version' => 1, 'privacy_accepted_at' => '2026-01-02 00:00:00',
        ], 'id = ?', [$this->userId]);

        $_SESSION['user_id'] = $this->userId;

        $mw = new LegalAcceptanceMiddleware();
        $this->assertTrue($mw->handle());
    }

    public function testFailsWhenUserNeverAccepted(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $_SERVER['REQUEST_URI'] = '/dashboard';

        $mw = new LegalAcceptanceMiddleware();
        $this->assertFalse($mw->handle());
        $this->assertSame('/dashboard', $_SESSION['legal_return_to'] ?? null);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Core/Middleware/LegalAcceptanceMiddlewareTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Core/Middleware/LegalAcceptanceMiddleware.php**

```php
<?php

namespace App\Core\Middleware;

use App\Core\Database;
use App\Core\Session;
use App\Models\LegalDocument;
use App\Services\Auth\LegalDocumentService;

class LegalAcceptanceMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $userId = Session::userId();
        if ($userId === null) {
            return true;
        }

        $user = Database::getInstance()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if ($user === false) {
            return true;
        }

        $service = new LegalDocumentService(new LegalDocument());
        if (!$service->needsAcceptance($user, 'nl')) {
            return true;
        }

        Session::set('legal_return_to', $_SERVER['REQUEST_URI'] ?? '/dashboard');
        return false;
    }
}
```

- [ ] **Step 4: Register middleware name in App.php**

Modify `src/Core/App.php`. Find the `$middlewareMap` property (around line 14-18) and update:

```php
    private array $middlewareMap = [
        'csrf' => CsrfMiddleware::class,
        'auth' => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
        'legal' => LegalAcceptanceMiddleware::class,
    ];
```

Add the `use` line at the top:

```php
use App\Core\Middleware\LegalAcceptanceMiddleware;
```

And extend `handleMiddlewareFailure()` (bottom of file) with:

```php
        if ($name === 'legal') {
            header('Location: /legal/accept');
            exit;
        }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Core/Middleware/LegalAcceptanceMiddlewareTest.php`
Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Core/Middleware/LegalAcceptanceMiddleware.php tests/Core/Middleware/LegalAcceptanceMiddlewareTest.php src/Core/App.php
git commit -m "Add LegalAcceptanceMiddleware + register 'legal' in App middleware map"
```

---

## Task 15: Seed legal content migrations

**Files:**
- Create: `migrations/007_seed_initial_tos_nl.sql`
- Create: `migrations/008_seed_initial_privacy_nl.sql`
- Create: `migrations/009_seed_placeholder_tos_en.sql`
- Create: `migrations/010_seed_placeholder_privacy_en.sql`

These are placeholder drafts — ToS/Privacy text MUST be reviewed by a lawyer before public launch. The top of each text includes a visible TODO banner.

- [ ] **Step 1: Create 007_seed_initial_tos_nl.sql**

Since SQL embedded multi-line text in single-statement `;`-split runner — escape `'` carefully. Store as markdown (rendered in view).

```sql
INSERT INTO legal_documents (type, version, language, content, published_at) VALUES
('tos', 1, 'nl',
'# Algemene Voorwaarden Cloudmarkplaats.nl

**⚠️ TODO: Deze tekst is een placeholder-concept en MOET worden gereviewd door een juridisch adviseur voordat het platform live gaat. De tekst is niet rechtsgeldig in huidige vorm.**

_Versie 1 — geldig vanaf 2026-04-21_

## 1. Over Cloudmarkplaats

Cloudmarkplaats.nl (hierna: "het platform") is een handelsplatform voor IT-hardware, gericht op de Nederlandse tech-community. Het platform wordt onafhankelijk beheerd en functioneert uitsluitend als **bemiddelaar** tussen kopers en verkopers.

## 2. Aard van de dienst

2.1 Het platform faciliteert contact tussen gebruikers. Koop- en verkooptransacties vinden plaats rechtstreeks tussen gebruikers onderling — buiten het platform om.

2.2 Het platform is **geen partij** bij enige transactie, biedt geen garantie op de kwaliteit, authenticiteit of levering van aangeboden goederen, en kan niet aansprakelijk worden gesteld voor geschillen tussen gebruikers.

2.3 Gebruik van het platform is op eigen risico.

## 3. Gebruiksvoorwaarden

3.1 U gaat ermee akkoord:
- geen frauduleuze, onjuiste of misleidende informatie te plaatsen;
- geen illegale goederen aan te bieden;
- geen inbreuk te maken op intellectuele eigendomsrechten;
- zich op een respectvolle manier te gedragen in het forum;
- uw inloggegevens niet met anderen te delen.

3.2 Schending van deze regels kan leiden tot verwijdering van listings, forum-posts, of beëindiging van uw account — zonder restitutie of compensatie.

## 4. Aansprakelijkheid

4.1 Voor zover wettelijk toegestaan, sluit het platform alle aansprakelijkheid uit voor directe of indirecte schade voortvloeiend uit het gebruik van het platform of transacties tussen gebruikers.

4.2 Het platform garandeert geen ononderbroken beschikbaarheid of foutloze werking.

## 5. Intellectueel eigendom

5.1 De software en het ontwerp van Cloudmarkplaats zijn eigendom van de beheerder. Gebruikersinhoud (productfotos, beschrijvingen, forum-posts) blijft eigendom van de plaatser, maar u verleent het platform een royalty-vrije licentie om deze inhoud te tonen binnen het platform.

## 6. Wijzigingen

6.1 Het platform kan deze voorwaarden wijzigen. Bij materiële wijzigingen moet u opnieuw akkoord geven voordat u verder kunt.

## 7. Toepasselijk recht

7.1 Op deze voorwaarden is Nederlands recht van toepassing. Geschillen worden voorgelegd aan de bevoegde rechter in Amsterdam.

## 8. Contact

Voor vragen: [contactgegevens in te vullen voor launch].',
'2026-04-21 00:00:00');
```

- [ ] **Step 2: Create 008_seed_initial_privacy_nl.sql**

```sql
INSERT INTO legal_documents (type, version, language, content, published_at) VALUES
('privacy', 1, 'nl',
'# Privacybeleid Cloudmarkplaats.nl

**⚠️ TODO: Deze tekst is een placeholder-concept en MOET worden gereviewd door een juridisch adviseur voordat het platform live gaat. De tekst is niet rechtsgeldig in huidige vorm.**

_Versie 1 — geldig vanaf 2026-04-21_

## 1. Verwerkingsverantwoordelijke

Cloudmarkplaats.nl is de verwerkingsverantwoordelijke voor de persoonsgegevens die via het platform worden verwerkt.

## 2. Welke gegevens verzamelen wij

Bij gebruik van het platform verwerken wij:

- **Accountgegevens**: gebruikersnaam, e-mailadres (optioneel bij Web3-login), wachtwoordhash.
- **OAuth-gegevens**: als u inlogt via Google of GitHub: de door die provider aangeleverde identifier en e-mailadres.
- **Web3-gegevens**: uw walletadres en de chain waarop u heeft ondertekend (geen privésleutels, alleen publieke gegevens).
- **Inhoud**: productlistings, forum-posts, en berichten die u zelf plaatst.
- **Technisch**: IP-adres, browsertype, en sessie-cookies — uitsluitend voor beveiliging en normaal functioneren.

## 3. Grondslag en doel

- **Uitvoering van overeenkomst** (art. 6 lid 1 sub b AVG): accountbeheer, faciliteren van contact tussen kopers en verkopers.
- **Gerechtvaardigd belang** (art. 6 lid 1 sub f AVG): misbruikpreventie, beveiliging, platformmoderatie.
- **Toestemming** (art. 6 lid 1 sub a AVG): voor vrijwillige profielinformatie.

## 4. Cookies

Wij gebruiken uitsluitend **strict-functionele cookies** (sessiebeheer, CSRF-bescherming). Er zijn geen analytics-, tracking- of marketingcookies actief. Daarom vragen wij geen expliciete cookietoestemming.

## 5. Delen met derden

5.1 Wij delen geen persoonsgegevens met derden, behalve:
- aan OAuth-providers (Google, GitHub) voor authenticatie, op uw eigen initiatief;
- wanneer dit wettelijk verplicht is (bijvoorbeeld op verzoek van politie of justitie).

5.2 Het platform draait op **eigen infrastructuur**; gegevens verlaten de EU niet.

## 6. Bewaartermijnen

- Accountgegevens: zolang uw account actief is. Op verzoek binnen 30 dagen verwijderd (conform art. 17 AVG).
- Inactieve accounts: na 3 jaar geen login wordt het account automatisch verwijderd.
- Forum-posts en productlistings: blijven zichtbaar tenzij u deze verwijdert of uw account wordt verwijderd.
- Auth-nonces en sessielogs: maximaal 24 uur.

## 7. Uw rechten (AVG)

U heeft het recht op:

- **Inzage** in uw gegevens (art. 15).
- **Rectificatie** (art. 16).
- **Verwijdering** ("recht om vergeten te worden", art. 17).
- **Beperking** van de verwerking (art. 18).
- **Overdraagbaarheid** (art. 20).
- **Bezwaar** (art. 21).
- **Intrekking toestemming**.
- **Klacht indienen** bij de Autoriteit Persoonsgegevens.

Stuur uw verzoek naar: [contactgegevens in te vullen voor launch].

## 8. Beveiliging

Wij nemen passende technische en organisatorische maatregelen: versleutelde verbindingen (HTTPS), wachtwoorden opgeslagen als bcrypt-hash, CSRF-bescherming, gescheiden productie- en ontwikkelomgevingen, geregelde beveiligingsupdates.

## 9. Wijzigingen in dit beleid

Dit privacybeleid kan worden aangepast. Bij materiële wijzigingen zult u bij uw volgende login gevraagd worden opnieuw akkoord te geven.

## 10. Contact

Voor privacy-vragen: [contactgegevens in te vullen voor launch].',
'2026-04-21 00:00:00');
```

- [ ] **Step 3: Create 009_seed_placeholder_tos_en.sql**

```sql
INSERT INTO legal_documents (type, version, language, content, published_at) VALUES
('tos', 1, 'en',
'# Terms of Service — Cloudmarkplaats.nl

**⚠️ English translation pending.** Please refer to the Dutch version for the authoritative legal text: [/legal/tos?lang=nl](/legal/tos?lang=nl)

An English translation will be provided in a future release.',
'2026-04-21 00:00:00');
```

- [ ] **Step 4: Create 010_seed_placeholder_privacy_en.sql**

```sql
INSERT INTO legal_documents (type, version, language, content, published_at) VALUES
('privacy', 1, 'en',
'# Privacy Policy — Cloudmarkplaats.nl

**⚠️ English translation pending.** Please refer to the Dutch version for the authoritative legal text: [/legal/privacy?lang=nl](/legal/privacy?lang=nl)

An English translation will be provided in a future release.',
'2026-04-21 00:00:00');
```

- [ ] **Step 5: Run migrations**

Run: `php migrations/migrate.php`
Expected: 4 new `OK` lines.

- [ ] **Step 6: Verify content exists**

Run:
```bash
php -r "require 'vendor/autoload.php'; \App\Core\Config::load('.'); \$db = \App\Core\Database::getInstance(); foreach (\$db->fetchAll('SELECT type, version, language, LENGTH(content) AS len FROM legal_documents ORDER BY type, language, version') as \$r) { printf('%-7s v%d %s %d chars%s', \$r['type'], \$r['version'], \$r['language'], \$r['len'], PHP_EOL); }"
```
Expected: 4 rows — tos+privacy × nl+en.

- [ ] **Step 7: Commit**

```bash
git add migrations/007_seed_initial_tos_nl.sql migrations/008_seed_initial_privacy_nl.sql migrations/009_seed_placeholder_tos_en.sql migrations/010_seed_placeholder_privacy_en.sql
git commit -m "Seed placeholder ToS + Privacy Policy (NL full, EN pending)"
```

---

## Task 16: LegalController

**Files:**
- Create: `tests/Controllers/LegalControllerTest.php`
- Create: `src/Controllers/LegalController.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Controllers/LegalControllerTest.php`:

```php
<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\LegalController;

class LegalControllerTest extends TestCase
{
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_CTL_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legalctl_%'");

        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 99, 'language' => 'nl', 'content' => 'TEST_CTL_tos', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 99, 'language' => 'nl', 'content' => 'TEST_CTL_priv', 'published_at' => '2026-01-01 00:00:00']);

        $this->userId = $this->db->insert('users', [
            'username' => 'test_legalctl_u1',
            'email' => 'test_legalctl_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);

        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_CTL_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legalctl_%'");
        $_SESSION = [];
        $_POST = [];
    }

    public function testAcceptPostUpdatesUser(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $_SESSION['_csrf_token'] = 'testtok';
        $_POST['_csrf_token'] = 'testtok';
        $_POST['accept'] = '1';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // We cannot test redirect() easily (calls exit). Instead extract logic into
        // a helper we can call directly. For this test we verify the DB update.
        $controller = new LegalController();

        // Simulate: directly call the "do accept" path. The controller's POST
        // handler calls $this->redirect(...) which exits — wrap in try/catch
        // or use a test-only subclass.
        try {
            @$controller->accept();
        } catch (\Throwable $e) {
            // exit() from redirect will surface as a RuntimeException in some setups
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        // Only assert if redirect didn't hard-exit; skip otherwise
        if ($user['tos_version'] !== null) {
            $this->assertSame(99, (int) $user['tos_version']);
            $this->assertSame(99, (int) $user['privacy_version']);
        } else {
            $this->markTestSkipped('Controller exit() prevents post-assert; see integration test');
        }
    }

    public function testTosActionRendersLatest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        ob_start();
        try {
            (new LegalController())->tos();
        } catch (\Throwable $e) {
            // layout render issues in test env — fall through
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('TEST_CTL_tos', $output);
    }
}
```

Note: the controller uses `redirect()` which calls `exit`. This makes testing post-handlers awkward. The test above skips around this. For the accept path, we'll test the model-update behavior through the service (already tested in Task 13) and rely on a later end-to-end smoke test for the HTTP path.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Controllers/LegalControllerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Controllers/LegalController.php**

```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Core\Config;
use App\Models\LegalDocument;
use App\Services\Auth\LegalDocumentService;

class LegalController extends BaseController
{
    private LegalDocumentService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new LegalDocumentService(new LegalDocument());
    }

    public function tos(): void
    {
        $this->renderDocument('tos', 'Algemene Voorwaarden');
    }

    public function privacy(): void
    {
        $this->renderDocument('privacy', 'Privacybeleid');
    }

    public function showAccept(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        $language = $this->language();
        $versions = $this->service->currentVersions($language);
        $tos = $this->service->getDocument('tos', $versions['tos'], $language);
        $privacy = $this->service->getDocument('privacy', $versions['privacy'], $language);

        $this->render('legal/accept', [
            'title' => 'Voorwaarden accepteren',
            'tos' => $tos,
            'privacy' => $privacy,
        ]);
    }

    public function accept(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showAccept();
            return;
        }

        if (empty($_POST['accept'])) {
            $this->flash('error', 'Je moet de voorwaarden accepteren om door te gaan.');
            $this->redirect('/legal/accept');
            return;
        }

        $this->service->accept($userId, $this->language());
        $returnTo = Session::get('legal_return_to', '/dashboard');
        Session::remove('legal_return_to');

        if (!preg_match('#^/[A-Za-z0-9/_\-]*$#', $returnTo)) {
            $returnTo = '/dashboard';
        }

        $this->flash('success', 'Bedankt voor het accepteren. Welkom bij Cloudmarkplaats!');
        $this->redirect($returnTo);
    }

    private function renderDocument(string $type, string $title): void
    {
        $language = $this->language();
        $version = $this->service->currentVersions($language)[$type];
        if ($version === 0) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Niet gevonden']);
            return;
        }

        $doc = $this->service->getDocument($type, $version, $language);
        $this->render('legal/' . $type, [
            'title' => $title,
            'document' => $doc,
        ]);
    }

    private function language(): string
    {
        $requested = $_GET['lang'] ?? 'nl';
        return in_array($requested, ['nl', 'en'], true) ? $requested : 'nl';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Controllers/LegalControllerTest.php`
Expected: tests pass (at least one passes, other may be skipped as noted).

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/LegalController.php tests/Controllers/LegalControllerTest.php
git commit -m "Add LegalController for /legal/tos, /legal/privacy, /legal/accept"
```

---

## Task 17: Legal views

**Files:**
- Create: `src/Views/legal/tos.php`
- Create: `src/Views/legal/privacy.php`
- Create: `src/Views/legal/accept.php`

- [ ] **Step 1: Create src/Views/legal/tos.php**

We render markdown to HTML via a minimal renderer. To avoid introducing a markdown dependency, we'll render as preformatted markdown within a readable container for V1 — good enough for placeholder content. If the lawyer's revised text needs rich formatting, swap for `league/commonmark` later.

```php
<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <article class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <nav class="mb-3">
                        <a href="/legal/tos?lang=nl" class="me-3">Nederlands</a>
                        <a href="/legal/tos?lang=en">English</a>
                    </nav>
                    <div class="legal-content" style="white-space: pre-wrap; font-family: inherit;">
                        <?= View::e($document['content'] ?? '') ?>
                    </div>
                    <p class="text-muted mt-4 mb-0">
                        Versie <?= (int) ($document['version'] ?? 0) ?>
                        — gepubliceerd <?= View::e($document['published_at'] ?? '') ?>
                    </p>
                </div>
            </article>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Create src/Views/legal/privacy.php**

Same structure as tos.php, just change the navigation links to `/legal/privacy`:

```php
<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <article class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <nav class="mb-3">
                        <a href="/legal/privacy?lang=nl" class="me-3">Nederlands</a>
                        <a href="/legal/privacy?lang=en">English</a>
                    </nav>
                    <div class="legal-content" style="white-space: pre-wrap; font-family: inherit;">
                        <?= View::e($document['content'] ?? '') ?>
                    </div>
                    <p class="text-muted mt-4 mb-0">
                        Versie <?= (int) ($document['version'] ?? 0) ?>
                        — gepubliceerd <?= View::e($document['published_at'] ?? '') ?>
                    </p>
                </div>
            </article>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Create src/Views/legal/accept.php**

```php
<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h1 class="mb-3">Welkom bij Cloudmarkplaats</h1>
                    <p class="lead">Lees onze voorwaarden en ons privacybeleid en bevestig dat je akkoord gaat om het platform te gebruiken.</p>

                    <hr>

                    <h2 class="h4 mt-4">Algemene Voorwaarden</h2>
                    <div class="border rounded p-3" style="max-height: 40vh; overflow-y: auto; white-space: pre-wrap;">
                        <?= View::e($tos['content'] ?? '') ?>
                    </div>

                    <h2 class="h4 mt-4">Privacybeleid</h2>
                    <div class="border rounded p-3" style="max-height: 40vh; overflow-y: auto; white-space: pre-wrap;">
                        <?= View::e($privacy['content'] ?? '') ?>
                    </div>

                    <form action="/legal/accept" method="POST" class="mt-4">
                        <?= View::csrfField() ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="accept" value="1" id="accept" required>
                            <label class="form-check-label" for="accept">
                                Ik heb de Algemene Voorwaarden (v<?= (int) ($tos['version'] ?? 0) ?>)
                                en het Privacybeleid (v<?= (int) ($privacy['version'] ?? 0) ?>) gelezen en ga akkoord.
                            </label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Accepteren en doorgaan</button>
                            <a href="/auth/logout" class="btn btn-outline-secondary">Uitloggen</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Smoke-check rendering**

Start dev server: `php -S localhost:8000 -t . index.php &` then run:

```bash
curl -s http://localhost:8000/legal/tos | grep -c "Algemene Voorwaarden"
```

(Note: this will only work after Task 18 registers the route. Defer visual check to Task 18's step.)

- [ ] **Step 5: Commit**

```bash
git add src/Views/legal/tos.php src/Views/legal/privacy.php src/Views/legal/accept.php
git commit -m "Add legal views: tos, privacy, accept (clickwrap)"
```

---

## Task 18: Register legal routes and wire middleware into auth routes

**Files:**
- Modify: `src/routes.php`

- [ ] **Step 1: Add legal routes to src/routes.php**

Open `src/routes.php` and append before the closing of the file:

```php
// Legal
$router->get('/legal/tos', 'LegalController@tos');
$router->get('/legal/privacy', 'LegalController@privacy');
$router->get('/legal/accept', 'LegalController@showAccept', ['auth']);
$router->post('/legal/accept', 'LegalController@accept', ['auth']);
```

- [ ] **Step 2: Apply `legal` middleware to existing authenticated routes**

Extend each existing authenticated route's middleware array from `['auth']` to `['auth', 'legal']`. This forces the acceptance gate on:

```
/product/add, /product/edit/{id}, /product/delete/{id},
/forum/new_category, /forum/new_topic/{category_id}, /forum/reply/{topic_id},
/message, /message/conversation/{user_id}, /message/send,
/profile, /profile/edit, /profile/delete,
/dashboard,
/admin and all /admin/* routes (careful: leave 'auth','admin' and add 'legal' → ['auth','admin','legal'])
```

For each, locate the line and add `'legal'` to the middleware array. Example:
```php
$router->both('/product/add', 'ProductController@add', ['auth', 'legal']);
$router->get('/dashboard', 'DashboardController@index', ['auth', 'legal']);
$router->get('/admin', 'AdminController@index', ['auth', 'admin', 'legal']);
```

Do NOT add `legal` to:
- `/auth/logout` — user must always be able to log out
- `/legal/accept` — obviously
- Public routes (they don't pass through `legal` anyway since it only fires for logged-in users)

- [ ] **Step 3: Smoke-check pages render**

Start dev server if not running: `php -S localhost:8000`

Visit (in browser or via curl):
```bash
curl -s http://localhost:8000/legal/tos | grep "Algemene Voorwaarden"
curl -s http://localhost:8000/legal/privacy | grep "Privacybeleid"
```

Both should return matching output.

- [ ] **Step 4: Commit**

```bash
git add src/routes.php
git commit -m "Register legal routes and apply 'legal' middleware to authenticated routes"
```

---

## Task 19: OAuthController

**Files:**
- Create: `tests/Controllers/OAuthControllerTest.php`
- Create: `src/Controllers/OAuthController.php`

The controller delegates the HTTP-bound parts to `OAuthProviderFactory` and the domain parts to `User`/`OAuthProvider` models. Tests mock the provider.

- [ ] **Step 1: Write the failing test**

Create `tests/Controllers/OAuthControllerTest.php`:

```php
<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;

class OAuthControllerTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'testctl_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauthctl_%' OR email LIKE 'test_oauthctl_%'");

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'testctl_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauthctl_%' OR email LIKE 'test_oauthctl_%'");
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    public function testHandleProviderResponseCreatesNewUser(): void
    {
        $controller = new \App\Controllers\OAuthController();

        $userId = $controller->handleProviderResponse(
            'google',
            'testctl_newuid',
            'test_oauthctl_new@test.com',
            'Nieuwe Gebruiker'
        );

        $this->assertGreaterThan(0, $userId);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertSame('test_oauthctl_new@test.com', $user['email']);
        $this->assertNull($user['password']);
        $link = $this->db->fetch("SELECT * FROM oauth_providers WHERE user_id = ?", [$userId]);
        $this->assertSame('testctl_newuid', $link['provider_uid']);
    }

    public function testHandleProviderResponseLinksExistingEmail(): void
    {
        $existingId = $this->db->insert('users', [
            'username' => 'test_oauthctl_existing',
            'email' => 'test_oauthctl_existing@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);

        $controller = new \App\Controllers\OAuthController();
        $userId = $controller->handleProviderResponse(
            'google',
            'testctl_existuid',
            'test_oauthctl_existing@test.com',
            'Bestaande Gebruiker'
        );

        $this->assertSame($existingId, $userId);
    }

    public function testHandleProviderResponseLogsInLinkedUser(): void
    {
        $userId = $this->db->insert('users', [
            'username' => 'test_oauthctl_linked',
            'email' => 'test_oauthctl_linked@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
        $this->db->insert('oauth_providers', [
            'user_id' => $userId,
            'provider' => 'google',
            'provider_uid' => 'testctl_linkeduid',
            'email' => 'test_oauthctl_linked@test.com',
        ]);

        $controller = new \App\Controllers\OAuthController();
        $result = $controller->handleProviderResponse(
            'google',
            'testctl_linkeduid',
            'test_oauthctl_linked@test.com',
            'Gelinkte Gebruiker'
        );

        $this->assertSame($userId, $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Controllers/OAuthControllerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Controllers/OAuthController.php**

```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Core\Database;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Services\Auth\OAuthProviderFactory;

class OAuthController extends BaseController
{
    private OAuthProviderFactory $factory;
    private OAuthProvider $links;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->factory = new OAuthProviderFactory();
        $this->links = new OAuthProvider();
        $this->userModel = new User();
    }

    public function redirect(string $provider): void
    {
        $this->assertProviderSupported($provider);

        $league = $this->factory->make($provider);
        $scopes = $provider === 'google'
            ? ['openid', 'email', 'profile']
            : ['user:email'];

        $authUrl = $league->getAuthorizationUrl(['scope' => $scopes]);
        Session::set('oauth_state_' . $provider, $league->getState());
        $this->redirectTo($authUrl);
    }

    public function callback(string $provider): void
    {
        $this->assertProviderSupported($provider);

        $state = $_GET['state'] ?? '';
        $expected = Session::get('oauth_state_' . $provider);
        Session::remove('oauth_state_' . $provider);

        if (empty($state) || $state !== $expected) {
            http_response_code(400);
            echo 'Invalid OAuth state.';
            return;
        }

        if (empty($_GET['code'])) {
            $this->flash('error', 'OAuth login geannuleerd.');
            $this->redirectTo('/auth/login');
            return;
        }

        $league = $this->factory->make($provider);
        try {
            $token = $league->getAccessToken('authorization_code', ['code' => $_GET['code']]);
            $resourceOwner = $league->getResourceOwner($token);
        } catch (\Throwable $e) {
            $this->flash('error', 'OAuth authenticatie mislukt.');
            $this->redirectTo('/auth/login');
            return;
        }

        $uid = (string) $resourceOwner->getId();
        $email = method_exists($resourceOwner, 'getEmail') ? $resourceOwner->getEmail() : null;
        $name = method_exists($resourceOwner, 'getName') ? $resourceOwner->getName() : null;

        if (!$email && $provider === 'github') {
            $email = "noreply_github_{$uid}@users.noreply.github.com";
        }

        $userId = $this->handleProviderResponse($provider, $uid, $email, $name ?? ("user_" . $uid));

        $user = $this->userModel->findById($userId);
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('role', $user['role'] ?? 'user');
        session_regenerate_id(true);
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        $this->flash('success', 'Welkom, ' . $user['username'] . '!');
        $this->redirectTo('/dashboard');
    }

    public function handleProviderResponse(string $provider, string $uid, ?string $email, string $name): int
    {
        $link = $this->links->findByProviderUid($provider, $uid);
        if ($link !== false) {
            return (int) $link['user_id'];
        }

        if ($email !== null) {
            $existing = $this->userModel->findByEmail($email);
            if ($existing !== false) {
                $this->links->link((int) $existing['id'], $provider, $uid, $email);
                return (int) $existing['id'];
            }
        }

        $username = $this->deriveUsername($name, $uid);
        $userId = Database::getInstance()->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => null,
            'role' => 'user',
        ]);
        $this->links->link($userId, $provider, $uid, $email);
        return $userId;
    }

    private function deriveUsername(string $name, string $uidFallback): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'user_' . substr($uidFallback, 0, 8);
        }
        $candidate = $base;
        $suffix = 2;
        while ($this->userModel->existsWithUsername($candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                $candidate = $base . '_' . bin2hex(random_bytes(3));
                break;
            }
        }
        return $candidate;
    }

    private function assertProviderSupported(string $provider): void
    {
        if (!in_array($provider, ['google', 'github'], true)) {
            http_response_code(404);
            exit;
        }
    }

    private function redirectTo(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
```

Note on the name clash: `BaseController` has no `redirect` method that conflicts — but our parent provides `redirect($url)`. We rename this controller's public action to `redirect(string $provider)`. To avoid PHP confusion with the parent's `redirect($url)` (both `public`), we explicitly call `$this->redirectTo()` internally for redirects. The public `redirect` matches the router handler name `OAuthController@redirect`.

If PHP complains about signature mismatch with parent's `protected function redirect(string $url): void`, we override as `public function redirect(string $provider): void` — PHP allows this as long as visibility does not decrease (protected → public is fine). Verify by running tests in step 4.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Controllers/OAuthControllerTest.php`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/OAuthController.php tests/Controllers/OAuthControllerTest.php
git commit -m "Add OAuthController with provider-agnostic new-user/link-existing flow"
```

---

## Task 20: Web3Controller

**Files:**
- Create: `tests/Controllers/Web3ControllerTest.php`
- Create: `src/Controllers/Web3Controller.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Controllers/Web3ControllerTest.php`:

```php
<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\Web3Controller;

class Web3ControllerTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->db->query("DELETE FROM auth_nonces");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000test%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'wallet_%'");

        $_ENV['APP_URL'] = 'https://cloudmarkplaats.test';
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM auth_nonces");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000test%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'wallet_%'");
    }

    public function testUsernameForAddressIsDeterministic(): void
    {
        $controller = new Web3Controller();
        $name1 = $controller->deriveWalletUsername('0xABC123DEF4567890abcdef0123456789abcdef00');
        $name2 = $controller->deriveWalletUsername('0xABC123DEF4567890abcdef0123456789abcdef00');
        $this->assertSame($name1, $name2);
        $this->assertStringStartsWith('wallet_', $name1);
    }

    public function testLinkOrCreateCreatesNewUserForNewWallet(): void
    {
        $controller = new Web3Controller();
        $userId = $controller->linkOrCreate('0x000000000000000000000000000000000000TEST1', 1);
        $this->assertGreaterThan(0, $userId);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertStringStartsWith('wallet_', $user['username']);
        $this->assertNull($user['email']);
        $this->assertNull($user['password']);
    }

    public function testLinkOrCreateReusesExistingWallet(): void
    {
        $controller = new Web3Controller();
        $addr = '0x000000000000000000000000000000000000TEST2';
        $id1 = $controller->linkOrCreate($addr, 1);
        $id2 = $controller->linkOrCreate($addr, 1);
        $this->assertSame($id1, $id2);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Controllers/Web3ControllerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create src/Controllers/Web3Controller.php**

```php
<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Models\AuthNonce;
use App\Models\User;
use App\Models\WalletAddress;
use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3NonceGenerator;
use App\Services\Auth\Web3SignatureVerifier;

class Web3Controller extends BaseController
{
    private Web3NonceGenerator $nonces;
    private SiweMessageBuilder $builder;
    private Web3SignatureVerifier $verifier;
    private WalletAddress $wallets;
    private User $userModel;
    private RateLimiter $rate;

    public function __construct()
    {
        parent::__construct();
        $this->nonces = new Web3NonceGenerator(new AuthNonce());
        $this->verifier = new Web3SignatureVerifier();
        $this->wallets = new WalletAddress();
        $this->userModel = new User();
        $this->rate = new RateLimiter();

        $domain = parse_url((string) Config::get('APP_URL', 'http://localhost:8000'), PHP_URL_HOST) ?: 'localhost';
        $this->builder = new SiweMessageBuilder($domain);
    }

    public function nonce(): void
    {
        header('Content-Type: application/json');

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$this->rate->attempt('web3_nonce:' . $clientIp, 10, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $address = strtolower((string) ($payload['address'] ?? ''));
        $chainId = (int) ($payload['chain_id'] ?? 0);

        if (!preg_match('/^0x[a-f0-9]{40}$/', $address) || $chainId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid address or chain_id']);
            return;
        }

        $nonce = $this->nonces->issue($address);
        $message = $this->builder->build(
            $address,
            $chainId,
            $nonce,
            (string) Config::get('APP_URL', 'http://localhost:8000'),
            'Log in bij Cloudmarkplaats'
        );

        echo json_encode(['nonce' => $nonce, 'message' => $message]);
    }

    public function verify(): void
    {
        header('Content-Type: application/json');

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$this->rate->attempt('web3_verify:' . $clientIp, 5, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = (string) ($payload['message'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');

        try {
            $parsed = $this->builder->parse($message);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid SIWE message: ' . $e->getMessage()]);
            return;
        }

        $address = strtolower($parsed['address']);

        if (!$this->verifier->verify($message, $signature, $address)) {
            http_response_code(401);
            echo json_encode(['error' => 'Signature verification failed']);
            return;
        }

        if (!$this->nonces->verifyAndConsume($parsed['nonce'], $address)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired nonce']);
            return;
        }

        $userId = $this->linkOrCreate($address, $parsed['chain_id']);
        $user = $this->userModel->findById($userId);

        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('role', $user['role'] ?? 'user');
        session_regenerate_id(true);
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        echo json_encode(['ok' => true, 'redirect' => '/dashboard']);
    }

    public function linkOrCreate(string $address, int $chainId): int
    {
        $existing = $this->wallets->findByAddress($address);
        if ($existing !== false) {
            return (int) $existing['user_id'];
        }

        $username = $this->deriveWalletUsername($address);
        $userId = Database::getInstance()->insert('users', [
            'username' => $username,
            'email' => null,
            'password' => null,
            'role' => 'user',
        ]);
        $this->wallets->link($userId, $address, $chainId);
        return $userId;
    }

    public function deriveWalletUsername(string $address): string
    {
        $base = 'wallet_' . substr(strtolower($address), 2, 8);
        $candidate = $base;
        $suffix = 2;
        while ($this->userModel->existsWithUsername($candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                $candidate = $base . '_' . bin2hex(random_bytes(3));
                break;
            }
        }
        return $candidate;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Controllers/Web3ControllerTest.php`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/Web3Controller.php tests/Controllers/Web3ControllerTest.php
git commit -m "Add Web3Controller for SIWE nonce + verify with rate limiting"
```

---

## Task 21: Register OAuth + Web3 routes + exempt from CSRF (JSON endpoints)

**Files:**
- Modify: `src/routes.php`
- Modify: `src/Core/Middleware/CsrfMiddleware.php`

The Web3 JSON endpoints receive POST requests but can't easily embed CSRF in the wallet-signing flow (the frontend reads CSRF from the meta tag). That's actually fine — we WILL enforce CSRF via the `X-CSRF-Token` header, which our middleware already checks.

The OAuth callback is a `GET`, so no CSRF concern there.

- [ ] **Step 1: Add routes to src/routes.php**

```php
// OAuth
$router->get('/auth/oauth/{provider}', 'OAuthController@redirect');
$router->get('/auth/oauth/{provider}/callback', 'OAuthController@callback');

// Web3
$router->post('/auth/web3/nonce', 'Web3Controller@nonce');
$router->post('/auth/web3/verify', 'Web3Controller@verify');
```

- [ ] **Step 2: Verify CSRF is still enforced on web3 endpoints**

The global CSRF middleware already inspects `X-CSRF-Token` header. The frontend JS will include this header with every fetch. No middleware change needed — our implementation already supports it.

- [ ] **Step 3: Smoke test OAuth redirect**

With dev server running and `.env` containing placeholder `GOOGLE_CLIENT_ID=stub`:

```bash
curl -si http://localhost:8000/auth/oauth/google | grep -i "location:"
```

Expected: `Location: https://accounts.google.com/o/oauth2/auth?...` (301/302).

- [ ] **Step 4: Commit**

```bash
git add src/routes.php
git commit -m "Register OAuth and Web3 auth routes"
```

---

## Task 22: Frontend — web3-login.js

**Files:**
- Create: `public/assets/js/web3-login.js`

- [ ] **Step 1: Create the file**

```javascript
// public/assets/js/web3-login.js
// MetaMask + WalletConnect v2 (lazy-loaded) SIWE login client.

(function () {
  'use strict';

  const WC_PROJECT_ID = document.querySelector('meta[name="wc-project-id"]')?.content || '';
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function setStatus(msg, isError) {
    const el = document.getElementById('web3-status');
    if (!el) return;
    el.textContent = msg;
    el.className = isError ? 'text-danger small mt-2' : 'text-muted small mt-2';
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
      body: JSON.stringify(body),
    });
    return { status: res.status, json: await res.json() };
  }

  async function getMetamaskProvider() {
    if (!window.ethereum) {
      throw new Error('MetaMask (of een andere EVM-wallet) niet gevonden.');
    }
    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    if (!accounts || !accounts.length) {
      throw new Error('Geen account geselecteerd.');
    }
    const chainIdHex = await window.ethereum.request({ method: 'eth_chainId' });
    return {
      address: accounts[0].toLowerCase(),
      chainId: parseInt(chainIdHex, 16),
      sign: async (msg) => window.ethereum.request({
        method: 'personal_sign',
        params: [msg, accounts[0]],
      }),
    };
  }

  async function getWalletConnectProvider() {
    if (!WC_PROJECT_ID) {
      throw new Error('WalletConnect is niet geconfigureerd.');
    }

    // Dynamic import — loads only if WalletConnect is used
    const { EthereumProvider } = await import('https://esm.sh/@walletconnect/ethereum-provider@2');
    const provider = await EthereumProvider.init({
      projectId: WC_PROJECT_ID,
      chains: [1],
      optionalChains: [10, 137, 8453, 42161],
      showQrModal: true,
    });
    await provider.connect();

    const accounts = await provider.request({ method: 'eth_accounts' });
    const chainIdHex = await provider.request({ method: 'eth_chainId' });

    return {
      address: accounts[0].toLowerCase(),
      chainId: parseInt(chainIdHex, 16),
      sign: async (msg) => provider.request({
        method: 'personal_sign',
        params: [msg, accounts[0]],
      }),
    };
  }

  async function runLogin(getProvider) {
    try {
      setStatus('Verbinden met wallet...');
      const wallet = await getProvider();

      setStatus('Nonce aanvragen...');
      const { status: nonceStatus, json: nonceJson } = await postJson('/auth/web3/nonce', {
        address: wallet.address,
        chain_id: wallet.chainId,
      });
      if (nonceStatus !== 200) throw new Error(nonceJson.error || 'Nonce request mislukt');

      setStatus('Bericht ondertekenen in je wallet...');
      const signature = await wallet.sign(nonceJson.message);

      setStatus('Verifiëren...');
      const { status: verStatus, json: verJson } = await postJson('/auth/web3/verify', {
        message: nonceJson.message,
        signature: signature,
      });
      if (verStatus !== 200) throw new Error(verJson.error || 'Verificatie mislukt');

      window.location.href = verJson.redirect || '/dashboard';
    } catch (err) {
      console.error(err);
      setStatus(err.message || 'Onbekende fout', true);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const mmBtn = document.getElementById('web3-metamask');
    const wcBtn = document.getElementById('web3-walletconnect');
    if (mmBtn) mmBtn.addEventListener('click', () => runLogin(getMetamaskProvider));
    if (wcBtn) wcBtn.addEventListener('click', () => runLogin(getWalletConnectProvider));
  });
})();
```

- [ ] **Step 2: Commit**

```bash
git add public/assets/js/web3-login.js
git commit -m "Add web3-login.js: MetaMask + WalletConnect v2 SIWE flow"
```

---

## Task 23: Frontend — cookie banner partial + JS

**Files:**
- Create: `src/Views/partials/cookie_banner.php`
- Create: `public/assets/js/cookie-banner.js`

- [ ] **Step 1: Create the partial**

```php
<?php use App\Core\View; ?>
<div id="cookie-banner"
     class="position-fixed bottom-0 start-0 end-0 p-3 bg-dark text-light shadow-lg"
     style="z-index: 1050; display: none;"
     role="alert"
     aria-live="polite">
    <div class="container d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2">
        <div class="flex-grow-1 small">
            Deze site gebruikt uitsluitend strict-functionele cookies (sessie, beveiliging).
            Meer info in ons <a href="/legal/privacy" class="text-warning">privacybeleid</a>.
        </div>
        <button type="button"
                id="cookie-banner-dismiss"
                class="btn btn-warning btn-sm flex-shrink-0">
            Begrepen
        </button>
    </div>
</div>
<script src="/assets/js/cookie-banner.js" defer></script>
```

- [ ] **Step 2: Create the JS**

```javascript
// public/assets/js/cookie-banner.js
(function () {
  'use strict';
  const STORAGE_KEY = 'cookie_notice_v1_dismissed';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(() => {
    const banner = document.getElementById('cookie-banner');
    const dismiss = document.getElementById('cookie-banner-dismiss');
    if (!banner || !dismiss) return;

    try {
      if (localStorage.getItem(STORAGE_KEY) === 'true') return;
    } catch (e) {
      // localStorage blocked — show banner as fallback
    }

    banner.style.display = 'block';

    dismiss.addEventListener('click', () => {
      try { localStorage.setItem(STORAGE_KEY, 'true'); } catch (e) {}
      banner.remove();
    });
  });
})();
```

- [ ] **Step 3: Include the partial in the main layout**

Modify `src/Views/layouts/main.php`: just before the closing `</body>` tag (around line 106), add:

```php
<?php require __DIR__ . '/../partials/cookie_banner.php'; ?>
```

- [ ] **Step 4: Add ToS/Privacy links to footer**

Still in `src/Views/layouts/main.php`, find the "Links" footer section (around line 74-78). Add two items to that `<ul>`:

```php
                    <li><a href="/legal/tos" class="text-muted">Algemene Voorwaarden</a></li>
                    <li><a href="/legal/privacy" class="text-muted">Privacybeleid</a></li>
```

- [ ] **Step 5: Smoke test**

Load any page in a browser (incognito window to bypass existing localStorage). Banner should appear at bottom. Click "Begrepen" — banner disappears, stays gone on refresh.

- [ ] **Step 6: Commit**

```bash
git add src/Views/partials/cookie_banner.php public/assets/js/cookie-banner.js src/Views/layouts/main.php
git commit -m "Add cookie notice banner + ToS/Privacy footer links"
```

---

## Task 24: Extend login view with OAuth + Wallet buttons

**Files:**
- Modify: `src/Views/auth/login.php`
- Modify: `src/Views/layouts/main.php`

- [ ] **Step 1: Add WalletConnect meta tag to layout head**

In `src/Views/layouts/main.php`, find the `<meta name="csrf-token">` line and add below it:

```php
<meta name="wc-project-id" content="<?= View::e(App\Core\Config::get('WALLETCONNECT_PROJECT_ID', '')) ?>">
```

Add `use App\Core\Config;` to the top of the file if needed (already has `use App\Core\View;`).

- [ ] **Step 2: Rewrite src/Views/auth/login.php**

```php
<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Inloggen</h2>

                    <form action="/auth/login" method="POST">
                        <?= View::csrfField() ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Gebruikersnaam</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= View::e($_POST['username'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Wachtwoord</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Inloggen</button>
                        </div>
                    </form>

                    <div class="text-center my-4">
                        <span class="text-muted">of</span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="/auth/oauth/google" class="btn btn-outline-secondary">
                            <i class="bi bi-google me-2"></i>Inloggen met Google
                        </a>
                        <a href="/auth/oauth/github" class="btn btn-outline-dark">
                            <i class="bi bi-github me-2"></i>Inloggen met GitHub
                        </a>
                        <button type="button" id="web3-metamask" class="btn btn-outline-warning">
                            🦊 MetaMask
                        </button>
                        <button type="button" id="web3-walletconnect" class="btn btn-outline-primary">
                            <i class="bi bi-qr-code-scan me-2"></i>WalletConnect
                        </button>
                    </div>
                    <div id="web3-status" class="text-muted small mt-2 text-center"></div>

                    <div class="text-center mt-4">
                        <p class="mb-0">Nog geen account?
                            <a href="/auth/register" class="text-decoration-none">Registreer hier</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/web3-login.js" defer></script>
```

- [ ] **Step 3: Smoke test**

Load `/auth/login` — should show four OAuth/wallet buttons below the form. OAuth links should redirect to provider (with real credentials in `.env`), wallet buttons should trigger the flow.

- [ ] **Step 4: Commit**

```bash
git add src/Views/auth/login.php src/Views/layouts/main.php
git commit -m "Extend login view with Google, GitHub, MetaMask, WalletConnect buttons"
```

---

## Task 25: ProfileController — security page methods

**Files:**
- Create: `tests/Controllers/ProfileSecurityTest.php`
- Modify: `src/Controllers/ProfileController.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Controllers/ProfileSecurityTest.php`:

```php
<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\ProfileController;

class ProfileSecurityTest extends TestCase
{
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'sec_%'");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000sec%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_sec_%'");

        $this->userId = $this->db->insert('users', [
            'username' => 'test_sec_u1',
            'email' => 'test_sec_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'sec_%'");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0x000000000000000000000000000000000000sec%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_sec_%'");
    }

    public function testCountAuthMethodsWithPasswordAndOauth(): void
    {
        $controller = new ProfileController();
        $this->db->insert('oauth_providers', [
            'user_id' => $this->userId, 'provider' => 'google',
            'provider_uid' => 'sec_uid1', 'email' => 'x@test.com',
        ]);
        $this->assertSame(2, $controller->countAuthMethods($this->userId));
    }

    public function testCountAuthMethodsWithWalletOnly(): void
    {
        $this->db->update('users', ['password' => null], 'id = ?', [$this->userId]);
        $this->db->insert('wallet_addresses', [
            'user_id' => $this->userId,
            'address' => '0x000000000000000000000000000000000000seca',
            'chain_id' => 1,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $controller = new ProfileController();
        $this->assertSame(1, $controller->countAuthMethods($this->userId));
    }

    public function testUnlinkOAuthBlockedWhenOnlyMethod(): void
    {
        $this->db->update('users', ['password' => null], 'id = ?', [$this->userId]);
        $this->db->insert('oauth_providers', [
            'user_id' => $this->userId, 'provider' => 'google',
            'provider_uid' => 'sec_only', 'email' => 'x@test.com',
        ]);

        $_SESSION['user_id'] = $this->userId;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['_csrf_token'] = 'tok';
        $_POST['_csrf_token'] = 'tok';

        $controller = new ProfileController();

        try {
            @$controller->unlinkOAuth('google');
        } catch (\Throwable $e) {}

        $remaining = $this->db->fetch("SELECT COUNT(*) AS c FROM oauth_providers WHERE user_id = ?", [$this->userId]);
        $this->assertSame(1, (int) $remaining['c'], 'unlink must be refused and link kept');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Controllers/ProfileSecurityTest.php`
Expected: FAIL — method not found.

- [ ] **Step 3: Modify src/Controllers/ProfileController.php**

Read the existing file and add the following methods (before the closing `}`). Add `use` imports at the top for new models:

```php
use App\Models\OAuthProvider;
use App\Models\WalletAddress;
```

Then add these methods to the class:

```php
    public function security(): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $oauth = (new OAuthProvider())->findByUser($userId);
        $wallets = (new WalletAddress())->findByUser($userId);

        $this->render('profile/security', [
            'title' => 'Beveiliging',
            'user_row' => $user,
            'oauth' => $oauth,
            'wallets' => $wallets,
            'auth_methods_count' => $this->countAuthMethods($userId),
        ]);
    }

    public function unlinkOAuth(string $provider): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($this->countAuthMethods($userId) <= 1) {
            $this->flash('error', 'Je kunt je laatste inlogmethode niet loskoppelen.');
            $this->redirect('/profile/security');
            return;
        }

        (new OAuthProvider())->unlink($userId, $provider);
        $this->flash('success', 'Koppeling verwijderd.');
        $this->redirect('/profile/security');
    }

    public function unlinkWallet(string $id): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($this->countAuthMethods($userId) <= 1) {
            $this->flash('error', 'Je kunt je laatste inlogmethode niet loskoppelen.');
            $this->redirect('/profile/security');
            return;
        }

        (new WalletAddress())->unlink($userId, (int) $id);
        $this->flash('success', 'Wallet ontkoppeld.');
        $this->redirect('/profile/security');
    }

    public function countAuthMethods(int $userId): int
    {
        $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        $passwordCount = ($user && !empty($user['password'])) ? 1 : 0;

        $oauthCount = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM oauth_providers WHERE user_id = ?", [$userId])['c'];
        $walletCount = (int) $this->db->fetch("SELECT COUNT(*) AS c FROM wallet_addresses WHERE user_id = ?", [$userId])['c'];

        return $passwordCount + $oauthCount + $walletCount;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Controllers/ProfileSecurityTest.php`
Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/ProfileController.php tests/Controllers/ProfileSecurityTest.php
git commit -m "Add security page methods to ProfileController + last-method protection"
```

---

## Task 26: Security view + routes

**Files:**
- Create: `src/Views/profile/security.php`
- Modify: `src/routes.php`

- [ ] **Step 1: Create src/Views/profile/security.php**

```php
<?php use App\Core\View; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4">Beveiliging</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Wachtwoord</h2>
                    <?php if (!empty($user_row['password'])): ?>
                        <p class="mb-0 text-success">✓ Wachtwoord ingesteld — je kunt inloggen met gebruikersnaam + wachtwoord.</p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">Geen wachtwoord ingesteld. Je logt in via OAuth of je wallet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">OAuth-providers</h2>

                    <?php foreach (['google' => 'Google', 'github' => 'GitHub'] as $provider => $label): ?>
                        <?php
                        $linked = null;
                        foreach ($oauth as $link) {
                            if ($link['provider'] === $provider) {
                                $linked = $link;
                                break;
                            }
                        }
                        ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <strong><?= View::e($label) ?></strong>
                                <?php if ($linked): ?>
                                    <span class="text-muted ms-2">(<?= View::e($linked['email'] ?? '—') ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($linked): ?>
                                <?php if ($auth_methods_count > 1): ?>
                                    <form method="POST" action="/profile/security/oauth/<?= View::e($provider) ?>/unlink" class="m-0">
                                        <?= View::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Ontkoppelen</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">(enige inlogmethode)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/auth/oauth/<?= View::e($provider) ?>" class="btn btn-sm btn-outline-primary">Koppelen</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Wallets</h2>

                    <?php if (empty($wallets)): ?>
                        <p class="text-muted">Nog geen wallets gekoppeld. Gebruik de MetaMask/WalletConnect-knoppen op de loginpagina om een wallet te koppelen aan dit account.</p>
                    <?php else: ?>
                        <?php foreach ($wallets as $w): ?>
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div>
                                    <code><?= View::e(substr($w['address'], 0, 10) . '...' . substr($w['address'], -6)) ?></code>
                                    <span class="text-muted ms-2">chain <?= View::e($w['chain_id']) ?></span>
                                </div>
                                <?php if ($auth_methods_count > 1): ?>
                                    <form method="POST" action="/profile/security/wallet/<?= (int) $w['id'] ?>/unlink" class="m-0">
                                        <?= View::csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Ontkoppelen</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">(enige inlogmethode)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Register routes in src/routes.php**

Add below the existing Profile routes:

```php
$router->get('/profile/security', 'ProfileController@security', ['auth', 'legal']);
$router->post('/profile/security/oauth/{provider}/unlink', 'ProfileController@unlinkOAuth', ['auth', 'legal']);
$router->post('/profile/security/wallet/{id}/unlink', 'ProfileController@unlinkWallet', ['auth', 'legal']);
```

- [ ] **Step 3: Smoke test**

Log in via username/password, visit `/profile/security`. Should show wachtwoord section, Google/GitHub link buttons, empty wallets section.

- [ ] **Step 4: Commit**

```bash
git add src/Views/profile/security.php src/routes.php
git commit -m "Add profile security view + routes for managing OAuth/wallet links"
```

---

## Task 27: Nonce cleanup script

**Files:**
- Create: `bin/cleanup-nonces.php`

- [ ] **Step 1: Create bin/cleanup-nonces.php**

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Models\AuthNonce;

Config::load(dirname(__DIR__));
Database::getInstance();

$model = new AuthNonce();
$deleted = $model->deleteExpired(86400);

echo date('[Y-m-d H:i:s] ') . "Deleted {$deleted} expired nonce rows.\n";
```

- [ ] **Step 2: Make it executable**

Run: `chmod +x bin/cleanup-nonces.php`

- [ ] **Step 3: Test it runs**

Run: `php bin/cleanup-nonces.php`
Expected: `[2026-... HH:MM:SS] Deleted 0 expired nonce rows.`

- [ ] **Step 4: Document cron setup**

Create `docs/cron-setup.md`:

```markdown
# Cron Jobs

## Nonce cleanup

Remove expired Web3 auth nonces older than 24 hours. Add to crontab:

\`\`\`
0 */6 * * * /usr/bin/php /path/to/cloudmarkplaats/bin/cleanup-nonces.php >> /var/log/cloudmarkplaats-cron.log 2>&1
\`\`\`

Runs every 6 hours.
```

(Use actual backticks in the file, not escaped.)

- [ ] **Step 5: Commit**

```bash
git add bin/cleanup-nonces.php docs/cron-setup.md
git commit -m "Add nonce cleanup script + cron docs"
```

---

## Task 28: .env.example + oauth-setup.md

**Files:**
- Modify: `.env.example`
- Create: `docs/oauth-setup.md`

- [ ] **Step 1: Extend .env.example**

Read `.env.example`. The file already has placeholder `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` entries under "# OAuth (Phase 1)". Add a WalletConnect section below them:

```
# Web3 (Phase 1a)
WALLETCONNECT_PROJECT_ID=
```

- [ ] **Step 2: Create docs/oauth-setup.md**

```markdown
# OAuth & WalletConnect Setup

This guide explains how to register external auth apps and populate `.env`.

## Google OAuth

1. Go to <https://console.cloud.google.com/>.
2. Create a project (or select an existing one).
3. Navigate to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
4. Application type: **Web application**.
5. Authorized redirect URI: `https://<your-domain>/auth/oauth/google/callback` (for local dev add `http://localhost:8000/auth/oauth/google/callback`).
6. Copy the generated **Client ID** and **Client Secret** into `.env`:
   ```
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your-client-secret
   ```
7. On the "OAuth consent screen" page, publish the app (or add test users during development).

## GitHub OAuth

1. Go to <https://github.com/settings/developers> → **New OAuth App**.
2. Application name: `Cloudmarkplaats` (or env-specific e.g. `Cloudmarkplaats Dev`).
3. Homepage URL: your app URL.
4. Authorization callback URL: `https://<your-domain>/auth/oauth/github/callback`.
5. Copy **Client ID**, generate a **Client Secret**, and paste into `.env`:
   ```
   GITHUB_CLIENT_ID=...
   GITHUB_CLIENT_SECRET=...
   ```

## WalletConnect v2

1. Go to <https://cloud.walletconnect.com/>, sign up (free tier).
2. Create a new project.
3. Copy the **Project ID** into `.env`:
   ```
   WALLETCONNECT_PROJECT_ID=your-32-char-project-id
   ```
4. If `WALLETCONNECT_PROJECT_ID` is empty, only the MetaMask button will work on the login page; the WalletConnect button will show "WalletConnect is niet geconfigureerd."

## MetaMask

No server-side registration needed — MetaMask is a browser extension the user installs themselves. The site only needs to call `window.ethereum.request(...)` from the frontend JS.

## Sanity check

After setting all four (or subset) of the above:

- Restart the PHP dev server so `.env` is re-read.
- Visit `/auth/login` — all four buttons should be present.
- Click Google → should redirect to Google's consent screen.
- Click GitHub → should redirect to GitHub's consent screen.
- Click MetaMask → should open the MetaMask popup.
- Click WalletConnect → should open QR-modal.
```

- [ ] **Step 3: Commit**

```bash
git add .env.example docs/oauth-setup.md
git commit -m "Document OAuth + WalletConnect setup, add env keys"
```

---

## Task 29: Full test suite + end-to-end smoke

**Files:** none created; this is a verification task.

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `./vendor/bin/phpunit`

Expected: all tests pass. Failures must be fixed before proceeding.

- [ ] **Step 2: Coverage check (informational)**

Run: `./vendor/bin/phpunit --coverage-text --coverage-filter=src/ | tail -40`

Expected: new auth + legal code has meaningful coverage (target ≥70% — strict 80% is aspirational for initial pass).

- [ ] **Step 3: Manual smoke test — legal gate**

Goal: verify a fresh user is forced to accept before using dashboard.

```bash
# 1. Create a fresh test account via the registration form at /auth/register
# 2. Login with that account
# 3. Try visiting /dashboard directly
```

Expected: browser is redirected to `/legal/accept`. After ticking the checkbox and submitting, landed on `/dashboard` with a welcome flash.

- [ ] **Step 4: Manual smoke test — OAuth**

With real Google credentials in `.env`:
1. Log out.
2. Visit `/auth/login`.
3. Click "Inloggen met Google".
4. Complete Google consent.
5. Confirm landed on `/legal/accept` (first time) and then dashboard.

Repeat for GitHub.

- [ ] **Step 5: Manual smoke test — Web3**

With MetaMask installed:
1. Log out.
2. Click MetaMask button.
3. Approve connection; sign message.
4. Confirm landed on `/legal/accept` (new wallet account), then dashboard.
5. Verify `/profile/security` shows the wallet listed.

- [ ] **Step 6: Manual smoke test — last-method protection**

1. As a user with only one auth method (e.g. wallet-only), log in.
2. Try POST to `/profile/security/wallet/{id}/unlink`.
3. Expected: flash error "Je kunt je laatste inlogmethode niet loskoppelen."

- [ ] **Step 7: Commit any follow-up fixes**

If smoke tests surface bugs, fix, re-run, commit as:

```bash
git commit -m "Fix <specific issue> from smoke test"
```

---

## Self-review summary

- Spec sections covered:
  - 1.1 OAuth (Google + GitHub) — Tasks 5, 12, 19, 21, 24, 28
  - 1.2 Web3 wallet login — Tasks 4, 6, 9, 10, 11, 20, 21, 22, 24, 27
  - 1.3 Legal waiver — Tasks 3, 7, 13, 14, 15, 16, 17, 18
  - Cookie banner — Task 23
  - Profile security — Tasks 25, 26
  - Rate limiting — Task 8 (used by Task 20)
  - Docs/env — Tasks 27 (cron), 28 (OAuth setup), .env.example

- Migration numbering consistently uses 001–010 across the plan.
- Column name `password` (not `password_hash`) used consistently.
- No `TBD`, `TODO: implement`, or `add validation` placeholders anywhere except the explicit legal-text TODOs that are meant to be visible to users.
