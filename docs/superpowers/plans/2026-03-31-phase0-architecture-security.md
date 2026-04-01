# Phase 0: Architecture & Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate to PSR-4 architecture, fix all security vulnerabilities, unify the codebase, and establish a solid foundation for feature development.

**Architecture:** Full PSR-4 namespaced MVC under `src/`. Thin `index.php` bootstrap → `App.php` → `Router` → `Middleware` pipeline → `Controller`. Models encapsulate database queries. Views rendered via `View` engine with auto-escaping.

**Tech Stack:** PHP 8.1+, MySQL 8.0, Composer PSR-4 autoloading, vlucas/phpdotenv, HTMLPurifier, Bootstrap 5, HTMX

---

## File Structure

```
src/
  Core/
    App.php                    # Application bootstrap and request lifecycle
    Config.php                 # Environment-based configuration (rewrite existing)
    Database.php               # Unified PDO wrapper (merge 3 existing)
    Router.php                 # URL routing with named routes (rewrite existing)
    Session.php                # Secure session management
    View.php                   # Template renderer with auto-escaping
    Middleware/
      MiddlewareInterface.php  # Contract for all middleware
      CsrfMiddleware.php       # CSRF token generation and validation
      AuthMiddleware.php       # Login requirement check
      AdminMiddleware.php      # Admin role check
  Controllers/
    BaseController.php         # Shared controller logic (merge 2 existing)
    AuthController.php         # Login, register, logout
    HomeController.php         # Homepage
    ProductController.php      # Product CRUD
    ForumController.php        # Forum categories, topics, replies
    MessageController.php      # User messaging
    ProfileController.php      # User profiles
    DashboardController.php    # User dashboard
    AdminController.php        # Admin panel (merge admin/*.php)
  Models/
    User.php                   # User queries
    Product.php                # Product + image + tag queries
    Message.php                # Message queries
    Forum.php                  # Forum category/topic/reply queries
    Review.php                 # Review queries
  Views/
    layouts/main.php           # Main layout with header/footer
    home/index.php
    auth/login.php
    auth/register.php
    dashboard/index.php
    product/index.php
    product/view.php
    product/add.php
    product/edit.php
    forum/index.php
    forum/category.php
    forum/topic.php
    forum/new_category.php
    forum/new_topic.php
    messages/index.php
    profile/index.php
    profile/view.php
    profile/edit.php
    profile/_products.php
    profile/_topics.php
    errors/404.php
migrations/
  001_initial_schema.sql       # Full schema from database.sql
  migrate.php                  # Migration runner CLI script
tests/
  bootstrap.php                # PHPUnit bootstrap with test DB
  Core/
    DatabaseTest.php
    RouterTest.php
    CsrfMiddlewareTest.php
  Models/
    UserModelTest.php
    ProductModelTest.php
```

---

## Task 1: Environment Configuration & Composer Setup

**Files:**
- Create: `.env.example`
- Create: `.env` (local, gitignored)
- Modify: `composer.json`
- Modify: `.gitignore`
- Delete: `config.php` (after migration complete, Task 12)

- [ ] **Step 1: Update composer.json with full PSR-4 config and new dependencies**

```json
{
    "name": "cloudmarkplaats/platform",
    "description": "Privacy-first IT hardware marketplace for the tech community",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "vlucas/phpdotenv": "^5.5",
        "phpmailer/phpmailer": "^6.8",
        "firebase/php-jwt": "^6.4",
        "intervention/image": "^2.7",
        "ezyang/htmlpurifier": "^4.17"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2: Create .env.example**

```ini
# Application
APP_NAME=Cloudmarkplaats
APP_URL=http://localhost:8000
APP_DEBUG=true
APP_ENV=development

# Database
DB_HOST=localhost
DB_NAME=cloudmarkplaats1
DB_USER=root
DB_PASS=

# Security
JWT_SECRET=change-me-to-a-random-64-char-string
SESSION_SECURE=0

# Features
MAX_PRODUCT_IMAGES=5
MAX_PRODUCT_TAGS=5
REQUIRE_APPROVAL=true

# Mail (PHPMailer)
MAIL_HOST=
MAIL_PORT=587
MAIL_USER=
MAIL_PASS=
MAIL_FROM=noreply@cloudmarkplaats.nl
MAIL_FROM_NAME=Cloudmarkplaats

# Payments (Phase 3)
MOLLIE_API_KEY=
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=

# OAuth (Phase 1)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

- [ ] **Step 3: Create .env from example with actual dev credentials**

Copy `.env.example` to `.env` and fill in the database credentials from current `config.php`:
```ini
DB_HOST=localhost
DB_NAME=cloudmarkplaats1
DB_USER=root
DB_PASS=
APP_DEBUG=true
```

- [ ] **Step 4: Update .gitignore to include .env**

Add these lines to `.gitignore`:
```
.env
vendor/
.phpunit.result.cache
```

- [ ] **Step 5: Run composer install**

```bash
composer install
```

Expected: Dependencies installed, `vendor/` directory created with autoloader.

- [ ] **Step 6: Commit**

```bash
git add composer.json .env.example .gitignore
git commit -m "Configure Composer PSR-4 autoloading and environment setup"
```

---

## Task 2: Core Config Class

**Files:**
- Rewrite: `src/Core/Config.php`

- [ ] **Step 1: Write test for Config**

Create `tests/Core/ConfigTest.php`:
```php
<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset Config state between tests
        Config::reset();
    }

    public function testLoadReadsEnvFile(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertNotEmpty(Config::get('APP_NAME'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertNull(Config::get('NONEXISTENT_KEY'));
        $this->assertEquals('fallback', Config::get('NONEXISTENT_KEY', 'fallback'));
    }

    public function testGetReturnsBoolForTrueValues(): void
    {
        Config::load(dirname(__DIR__, 2));
        $debug = Config::get('APP_DEBUG');
        $this->assertIsBool($debug);
    }

    public function testIsDebugReturnsBoolean(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertIsBool(Config::isDebug());
    }

    public function testDatabaseConfigReturnsArray(): void
    {
        Config::load(dirname(__DIR__, 2));
        $db = Config::database();
        $this->assertArrayHasKey('host', $db);
        $this->assertArrayHasKey('name', $db);
        $this->assertArrayHasKey('user', $db);
        $this->assertArrayHasKey('pass', $db);
    }
}
```

- [ ] **Step 2: Create PHPUnit bootstrap**

Create `tests/bootstrap.php`:
```php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
```

Create `phpunit.xml`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamedSaceLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 3: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Core/ConfigTest.php
```

Expected: FAIL — `Config` class doesn't have `reset()`, `get()`, `isDebug()`, `database()` methods yet.

- [ ] **Step 4: Implement Config class**

Rewrite `src/Core/Config.php`:
```php
<?php

namespace App\Core;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();
        $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

        self::$config = $_ENV;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$config[$key] ?? $default;

        if ($value === null) {
            return $default;
        }

        // Cast string booleans
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
        }

        return $value;
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('APP_DEBUG', false);
    }

    public static function database(): array
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME'),
            'user' => self::get('DB_USER'),
            'pass' => self::get('DB_PASS', ''),
        ];
    }

    public static function reset(): void
    {
        self::$config = [];
        self::$loaded = false;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Core/ConfigTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Core/Config.php tests/Core/ConfigTest.php tests/bootstrap.php phpunit.xml
git commit -m "Implement Config class with .env support and tests"
```

---

## Task 3: Unified Database Class

**Files:**
- Rewrite: `src/Core/Database.php`
- Create: `tests/Core/DatabaseTest.php`

- [ ] **Step 1: Write Database test**

Create `tests/Core/DatabaseTest.php`:
```php
<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $db2 = Database::getInstance();
        $this->assertSame($this->db, $db2);
    }

    public function testQueryExecutesPreparedStatement(): void
    {
        $result = $this->db->query("SELECT 1 AS val");
        $this->assertNotNull($result);
    }

    public function testFetchReturnsSingleRow(): void
    {
        $row = $this->db->fetch("SELECT 1 AS val");
        $this->assertEquals(1, $row['val']);
    }

    public function testFetchAllReturnsArray(): void
    {
        $rows = $this->db->fetchAll("SELECT 1 AS val UNION SELECT 2");
        $this->assertCount(2, $rows);
    }

    public function testInsertReturnsId(): void
    {
        // Create a temporary test table
        $this->db->query("CREATE TEMPORARY TABLE _test_insert (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $id = $this->db->insert('_test_insert', ['name' => 'test']);
        $this->assertEquals(1, $id);
    }

    public function testUpdateModifiesRows(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_update (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_update', ['name' => 'old']);
        $affected = $this->db->update('_test_update', ['name' => 'new'], 'id = ?', [1]);
        $this->assertEquals(1, $affected);

        $row = $this->db->fetch("SELECT name FROM _test_update WHERE id = 1");
        $this->assertEquals('new', $row['name']);
    }

    public function testDeleteRemovesRows(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_delete (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_delete', ['name' => 'gone']);
        $affected = $this->db->delete('_test_delete', 'id = ?', [1]);
        $this->assertEquals(1, $affected);

        $row = $this->db->fetch("SELECT * FROM _test_delete WHERE id = 1");
        $this->assertFalse($row);
    }

    public function testTransactionCommit(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_tx (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->beginTransaction();
        $this->db->insert('_test_tx', ['name' => 'committed']);
        $this->db->commit();

        $row = $this->db->fetch("SELECT name FROM _test_tx WHERE id = 1");
        $this->assertEquals('committed', $row['name']);
    }

    public function testTransactionRollback(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_rb (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_rb', ['name' => 'keep']);
        $this->db->beginTransaction();
        $this->db->insert('_test_rb', ['name' => 'discard']);
        $this->db->rollBack();

        $rows = $this->db->fetchAll("SELECT * FROM _test_rb");
        $this->assertCount(1, $rows);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Core/DatabaseTest.php
```

Expected: FAIL — `Database` class doesn't have `resetInstance()`, `insert()`, `update()`, `delete()` methods.

- [ ] **Step 3: Implement unified Database class**

Rewrite `src/Core/Database.php`:
```php
<?php

namespace App\Core;

use PDO;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = Config::database();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['name']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$sets} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Core/DatabaseTest.php
```

Expected: All 9 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Database.php tests/Core/DatabaseTest.php
git commit -m "Implement unified Database class with CRUD helpers and transactions"
```

---

## Task 4: Session Management

**Files:**
- Create: `src/Core/Session.php`

- [ ] **Step 1: Implement Session class**

Create `src/Core/Session.php`:
```php
<?php

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure = (bool) Config::get('SESSION_SECURE', false);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');

        session_start();
        self::$started = true;

        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::has('user_id');
    }

    public static function isAdmin(): bool
    {
        return self::get('role') === 'admin';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Core/Session.php
git commit -m "Add Session class with secure defaults and flash messages"
```

---

## Task 5: View Renderer

**Files:**
- Create: `src/Core/View.php`

- [ ] **Step 1: Implement View renderer**

Create `src/Core/View.php`:
```php
<?php

namespace App\Core;

class View
{
    private static string $viewPath = __DIR__ . '/../Views';
    private static string $layoutPath = __DIR__ . '/../Views/layouts';

    /**
     * Render a view with optional layout.
     * For HTMX requests, renders without layout.
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile = self::$viewPath . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        // Extract data as local variables for the view
        extract($data);

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // HTMX requests get content only, no layout
        if (self::isHtmxRequest()) {
            echo $content;
            return;
        }

        // Render within layout
        $layoutFile = self::$layoutPath . '/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        require $layoutFile;
    }

    /**
     * Escape output for safe HTML display.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check if the current request is an HTMX request.
     */
    public static function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']);
    }

    /**
     * Get the CSRF token field HTML.
     */
    public static function csrfField(): string
    {
        $token = Session::get('_csrf_token', '');
        return '<input type="hidden" name="_csrf_token" value="' . self::e($token) . '">';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Core/View.php
git commit -m "Add View renderer with auto-escaping and HTMX support"
```

---

## Task 6: Middleware System

**Files:**
- Create: `src/Core/Middleware/MiddlewareInterface.php`
- Create: `src/Core/Middleware/CsrfMiddleware.php`
- Create: `src/Core/Middleware/AuthMiddleware.php`
- Create: `src/Core/Middleware/AdminMiddleware.php`
- Create: `tests/Core/CsrfMiddlewareTest.php`

- [ ] **Step 1: Write CSRF middleware test**

Create `tests/Core/CsrfMiddlewareTest.php`:
```php
<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Session;

class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = '';
    }

    public function testHandleGeneratesTokenOnGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
        $this->assertNotEmpty($_SESSION['_csrf_token']);
    }

    public function testHandleAcceptsValidPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'valid-token-123';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
    }

    public function testHandleRejectsMissingPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertFalse($result);
    }

    public function testHandleRejectsInvalidPostToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'wrong-token';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertFalse($result);
    }

    public function testHandleAcceptsHeaderToken(): void
    {
        $_SESSION['_csrf_token'] = 'valid-token-123';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid-token-123';

        $middleware = new CsrfMiddleware();
        $result = $middleware->handle();
        $this->assertTrue($result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Core/CsrfMiddlewareTest.php
```

Expected: FAIL — classes don't exist yet.

- [ ] **Step 3: Implement middleware interface and all middleware**

Create `src/Core/Middleware/MiddlewareInterface.php`:
```php
<?php

namespace App\Core\Middleware;

interface MiddlewareInterface
{
    /**
     * Handle the middleware check.
     * Return true to continue, false to abort.
     */
    public function handle(): bool;
}
```

Create `src/Core/Middleware/CsrfMiddleware.php`:
```php
<?php

namespace App\Core\Middleware;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Generate token if not exists
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        // Only validate on state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }

        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
```

Create `src/Core/Middleware/AuthMiddleware.php`:
```php
<?php

namespace App\Core\Middleware;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
```

Create `src/Core/Middleware/AdminMiddleware.php`:
```php
<?php

namespace App\Core\Middleware;

class AdminMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Core/CsrfMiddlewareTest.php
```

Expected: All 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Middleware/ tests/Core/CsrfMiddlewareTest.php
git commit -m "Add middleware system with CSRF, Auth, and Admin middleware"
```

---

## Task 7: Router

**Files:**
- Rewrite: `src/Core/Router.php`
- Create: `tests/Core/RouterTest.php`

- [ ] **Step 1: Write Router test**

Create `tests/Core/RouterTest.php`:
```php
<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRegisterGetRoute(): void
    {
        $this->router->get('/test', 'TestController@index');
        $match = $this->router->match('GET', '/test');
        $this->assertNotNull($match);
        $this->assertEquals('TestController', $match['controller']);
        $this->assertEquals('index', $match['action']);
    }

    public function testRegisterPostRoute(): void
    {
        $this->router->post('/submit', 'TestController@store');
        $match = $this->router->match('POST', '/submit');
        $this->assertNotNull($match);
        $this->assertEquals('store', $match['action']);
    }

    public function testParameterExtraction(): void
    {
        $this->router->get('/product/{id}', 'ProductController@view');
        $match = $this->router->match('GET', '/product/42');
        $this->assertNotNull($match);
        $this->assertEquals('42', $match['params']['id']);
    }

    public function testMultipleParameters(): void
    {
        $this->router->get('/forum/{category_id}/topic/{id}', 'ForumController@topic');
        $match = $this->router->match('GET', '/forum/5/topic/12');
        $this->assertNotNull($match);
        $this->assertEquals('5', $match['params']['category_id']);
        $this->assertEquals('12', $match['params']['id']);
    }

    public function testNoMatchReturnsNull(): void
    {
        $this->router->get('/exists', 'TestController@index');
        $match = $this->router->match('GET', '/not-exists');
        $this->assertNull($match);
    }

    public function testMethodMismatchReturnsNull(): void
    {
        $this->router->get('/only-get', 'TestController@index');
        $match = $this->router->match('POST', '/only-get');
        $this->assertNull($match);
    }

    public function testMiddlewareAttachment(): void
    {
        $this->router->get('/admin', 'AdminController@index', ['auth', 'admin']);
        $match = $this->router->match('GET', '/admin');
        $this->assertNotNull($match);
        $this->assertEquals(['auth', 'admin'], $match['middleware']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Core/RouterTest.php
```

Expected: FAIL — Router doesn't have `match()` method or middleware support.

- [ ] **Step 3: Implement Router**

Rewrite `src/Core/Router.php`:
```php
<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, string $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, string $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function both(string $path, string $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, string $handler, array $middleware): self
    {
        [$controller, $action] = explode('@', $handler);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function match(string $method, string $uri): ?array
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');
        if ($uri === '/') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);
            if ($params !== null) {
                return [
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'middleware' => $route['middleware'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function matchPath(string $routePath, string $uri): ?array
    {
        // Convert route path to regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        // Extract only named parameters
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Core/RouterTest.php
```

Expected: All 7 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Router.php tests/Core/RouterTest.php
git commit -m "Implement Router with parameter extraction and middleware support"
```

---

## Task 8: Application Bootstrap (App.php)

**Files:**
- Create: `src/Core/App.php`
- Create: `src/routes.php`

- [ ] **Step 1: Create route definitions**

Create `src/routes.php`:
```php
<?php

use App\Core\Router;

/** @var Router $router */

// Public routes
$router->get('/', 'HomeController@index');
$router->get('/home', 'HomeController@index');

// Auth routes
$router->both('/auth/login', 'AuthController@login');
$router->both('/auth/register', 'AuthController@register');
$router->get('/auth/logout', 'AuthController@logout');

// Products
$router->get('/product', 'ProductController@index');
$router->get('/product/view/{id}', 'ProductController@view');
$router->both('/product/add', 'ProductController@add', ['auth']);
$router->both('/product/edit/{id}', 'ProductController@edit', ['auth']);
$router->post('/product/delete/{id}', 'ProductController@delete', ['auth']);

// Forum
$router->get('/forum', 'ForumController@index');
$router->get('/forum/category/{id}', 'ForumController@category');
$router->get('/forum/topic/{id}', 'ForumController@topic');
$router->both('/forum/new_category', 'ForumController@new_category', ['auth', 'admin']);
$router->both('/forum/new_topic/{category_id}', 'ForumController@new_topic', ['auth']);
$router->post('/forum/reply/{topic_id}', 'ForumController@reply', ['auth']);

// Messages
$router->get('/message', 'MessageController@index', ['auth']);
$router->get('/message/conversation/{user_id}', 'MessageController@index', ['auth']);
$router->post('/message/send', 'MessageController@send', ['auth']);

// Profile
$router->get('/profile', 'ProfileController@index', ['auth']);
$router->get('/profile/view/{id}', 'ProfileController@view');
$router->both('/profile/edit', 'ProfileController@edit', ['auth']);
$router->post('/profile/delete', 'ProfileController@delete', ['auth']);
$router->get('/profile/products/{id}', 'ProfileController@products');
$router->get('/profile/topics/{id}', 'ProfileController@topics');

// Dashboard
$router->get('/dashboard', 'DashboardController@index', ['auth']);

// Admin
$router->get('/admin', 'AdminController@index', ['auth', 'admin']);
$router->get('/admin/products', 'AdminController@products', ['auth', 'admin']);
$router->post('/admin/products/approve/{id}', 'AdminController@approveProduct', ['auth', 'admin']);
$router->post('/admin/products/reject/{id}', 'AdminController@rejectProduct', ['auth', 'admin']);
$router->post('/admin/products/delete/{id}', 'AdminController@deleteProduct', ['auth', 'admin']);
$router->get('/admin/users', 'AdminController@users', ['auth', 'admin']);
$router->post('/admin/users/toggle-admin/{id}', 'AdminController@toggleAdmin', ['auth', 'admin']);
$router->post('/admin/users/delete/{id}', 'AdminController@deleteUser', ['auth', 'admin']);
```

- [ ] **Step 2: Create App.php bootstrap**

Create `src/Core/App.php`:
```php
<?php

namespace App\Core;

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\AdminMiddleware;

class App
{
    private Router $router;
    private string $basePath;

    private array $middlewareMap = [
        'csrf' => CsrfMiddleware::class,
        'auth' => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
    ];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->router = new Router();
    }

    public function run(): void
    {
        // Boot
        Config::load($this->basePath);
        Session::start();

        // Configure error reporting
        if (Config::isDebug()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // Load routes
        $router = $this->router;
        require $this->basePath . '/src/routes.php';

        // Match current request
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $match = $this->router->match($method, $uri);

        if ($match === null) {
            $this->render404();
            return;
        }

        // Run global CSRF middleware
        $csrf = new CsrfMiddleware();
        if (!$csrf->handle()) {
            http_response_code(403);
            echo 'Invalid CSRF token. Please go back and try again.';
            return;
        }

        // Run route-specific middleware
        foreach ($match['middleware'] as $name) {
            if (!isset($this->middlewareMap[$name])) {
                continue;
            }
            $middlewareClass = $this->middlewareMap[$name];
            $middleware = new $middlewareClass();
            if (!$middleware->handle()) {
                $this->handleMiddlewareFailure($name);
                return;
            }
        }

        // Dispatch to controller
        $controllerClass = 'App\\Controllers\\' . $match['controller'];
        if (!class_exists($controllerClass)) {
            $this->render404();
            return;
        }

        $controller = new $controllerClass();
        $action = $match['action'];

        if (!method_exists($controller, $action)) {
            $this->render404();
            return;
        }

        // Call controller action with route parameters
        $controller->$action(...array_values($match['params']));
    }

    private function handleMiddlewareFailure(string $name): void
    {
        if ($name === 'auth') {
            Session::flash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            header('Location: /auth/login');
            exit;
        }

        if ($name === 'admin') {
            Session::flash('error', 'Geen toegang.');
            header('Location: /');
            exit;
        }
    }

    private function render404(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Pagina niet gevonden']);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Core/App.php src/routes.php
git commit -m "Add App bootstrap with middleware pipeline and route definitions"
```

---

## Task 9: Models Layer

**Files:**
- Create: `src/Models/User.php`
- Create: `src/Models/Product.php`
- Create: `src/Models/Message.php`
- Create: `src/Models/Forum.php`
- Create: `src/Models/Review.php`
- Create: `tests/Models/UserModelTest.php`
- Create: `tests/Models/ProductModelTest.php`

- [ ] **Step 1: Write User model test**

Create `tests/Models/UserModelTest.php`:
```php
<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\User;

class UserModelTest extends TestCase
{
    private User $user;
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->user = new User();

        // Clean up test data
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_%'");
    }

    public function testFindByIdReturnsUser(): void
    {
        $id = $this->db->insert('users', [
            'username' => 'test_findbyid',
            'email' => 'test_findbyid@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);

        $user = $this->user->findById($id);
        $this->assertNotNull($user);
        $this->assertEquals('test_findbyid', $user['username']);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testFindByUsernameReturnsUser(): void
    {
        $id = $this->db->insert('users', [
            'username' => 'test_findbyname',
            'email' => 'test_findbyname@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);

        $user = $this->user->findByUsername('test_findbyname');
        $this->assertNotNull($user);
        $this->assertEquals($id, $user['id']);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $user = $this->user->findById(999999);
        $this->assertFalse($user);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->user->create('test_create', 'test_create@test.com', 'securepass123');
        $this->assertGreaterThan(0, $id);

        $user = $this->user->findById($id);
        $this->assertEquals('test_create', $user['username']);
        $this->assertTrue(password_verify('securepass123', $user['password']));

        $this->db->delete('users', 'id = ?', [$id]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_%'");
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Models/UserModelTest.php
```

Expected: FAIL — `User` model class doesn't exist.

- [ ] **Step 3: Implement User model**

Create `src/Models/User.php`:
```php
<?php

namespace App\Models;

use App\Core\Database;

class User
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findByUsername(string $username): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function create(string $username, string $email, string $password): int
    {
        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function updateProfile(int $id, array $data): int
    {
        return $this->db->update('users', $data, 'id = ?', [$id]);
    }

    public function updatePassword(int $id, string $newPassword): int
    {
        return $this->db->update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ], 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('messages', 'sender_id = ? OR receiver_id = ?', [$id, $id]);
        $this->db->delete('reviews', 'user_id = ?', [$id]);
        $this->db->delete('favorites', 'user_id = ?', [$id]);

        // Delete user's product images, tags, then products
        $products = $this->db->fetchAll("SELECT id FROM products WHERE user_id = ?", [$id]);
        foreach ($products as $product) {
            $this->db->delete('product_images', 'product_id = ?', [$product['id']]);
            $this->db->delete('product_tags', 'product_id = ?', [$product['id']]);
        }
        $this->db->delete('products', 'user_id = ?', [$id]);

        // Delete forum content
        $this->db->delete('forum_replies', 'user_id = ?', [$id]);
        $this->db->delete('forum_topics', 'user_id = ?', [$id]);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function existsWithUsername(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE username = ? AND id != ?",
                [$username, $excludeId]
            );
        } else {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE username = ?",
                [$username]
            );
        }
        return $row['cnt'] > 0;
    }

    public function existsWithEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE email = ? AND id != ?",
                [$email, $excludeId]
            );
        } else {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM users WHERE email = ?",
                [$email]
            );
        }
        return $row['cnt'] > 0;
    }

    public function toggleAdmin(int $id): void
    {
        $user = $this->findById($id);
        if ($user) {
            $newRole = $user['role'] === 'admin' ? 'user' : 'admin';
            $this->db->update('users', ['role' => $newRole], 'id = ?', [$id]);
        }
    }
}
```

- [ ] **Step 4: Run User model test**

```bash
./vendor/bin/phpunit tests/Models/UserModelTest.php
```

Expected: All 4 tests PASS.

- [ ] **Step 5: Implement Product model**

Create `src/Models/Product.php`:
```php
<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT p.*, u.username FROM products p
             JOIN users u ON p.user_id = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getImages(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = ?",
            [$productId]
        );
    }

    public function getTags(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT tag FROM product_tags WHERE product_id = ?",
            [$productId]
        );
    }

    public function getApproved(array $filters = []): array
    {
        $sql = "SELECT p.*, u.username,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
                FROM products p
                JOIN users u ON p.user_id = u.id
                WHERE p.approved = 1";
        $params = [];

        if (!empty($filters['category'])) {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['state'])) {
            $sql .= " AND p.state = ?";
            $params[] = $filters['state'];
        }

        $sort = $filters['sort'] ?? 'newest';
        $sql .= match ($sort) {
            'price_asc' => " ORDER BY p.price ASC",
            'price_desc' => " ORDER BY p.price DESC",
            'oldest' => " ORDER BY p.created_at ASC",
            default => " ORDER BY p.created_at DESC",
        };

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function getByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*,
             (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
             FROM products p WHERE p.user_id = ? ORDER BY p.created_at DESC",
            [$userId]
        );
    }

    public function getRecent(int $limit = 8): array
    {
        return $this->getApproved(['limit' => $limit, 'sort' => 'newest']);
    }

    public function getCategoryCounts(): array
    {
        return $this->db->fetchAll(
            "SELECT category, COUNT(*) as count FROM products
             WHERE approved = 1 GROUP BY category ORDER BY count DESC LIMIT 6"
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('products', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('products', $data, 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('product_tags', 'product_id = ?', [$id]);
        $this->db->delete('product_images', 'product_id = ?', [$id]);
        $this->db->delete('favorites', 'product_id = ?', [$id]);
        $this->db->delete('reviews', 'product_id = ?', [$id]);
        $this->db->delete('products', 'id = ?', [$id]);
    }

    public function addImage(int $productId, string $imageUrl): int
    {
        return $this->db->insert('product_images', [
            'product_id' => $productId,
            'image_url' => $imageUrl,
        ]);
    }

    public function deleteImages(int $productId): void
    {
        $this->db->delete('product_images', 'product_id = ?', [$productId]);
    }

    public function addTag(int $productId, string $tag): void
    {
        $this->db->insert('product_tags', [
            'product_id' => $productId,
            'tag' => $tag,
        ]);
    }

    public function deleteTags(int $productId): void
    {
        $this->db->delete('product_tags', 'product_id = ?', [$productId]);
    }

    public function approve(int $id): int
    {
        return $this->db->update('products', ['approved' => 1], 'id = ?', [$id]);
    }

    public function reject(int $id): int
    {
        return $this->db->update('products', ['approved' => 0], 'id = ?', [$id]);
    }

    /**
     * Get products for admin view with filters.
     */
    public function getForAdmin(array $filters = []): array
    {
        $sql = "SELECT p.*, u.username FROM products p
                JOIN users u ON p.user_id = u.id WHERE 1=1";
        $params = [];

        if (isset($filters['approved'])) {
            $sql .= " AND p.approved = ?";
            $params[] = $filters['approved'];
        }
        if (!empty($filters['category'])) {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY p.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
```

- [ ] **Step 6: Implement Message model**

Create `src/Models/Message.php`:
```php
<?php

namespace App\Models;

use App\Core\Database;

class Message
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getConversations(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT
                CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_user_id,
                u.username as other_username,
                MAX(m.created_at) as last_message_at,
                SUM(CASE WHEN m.receiver_id = ? AND m.read_at IS NULL THEN 1 ELSE 0 END) as unread_count,
                (SELECT m2.message FROM messages m2
                 WHERE (m2.sender_id = ? AND m2.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
                    OR (m2.receiver_id = ? AND m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
                 ORDER BY m2.created_at DESC LIMIT 1) as last_message
             FROM messages m
             JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
             WHERE m.sender_id = ? OR m.receiver_id = ?
             GROUP BY other_user_id, u.username
             ORDER BY last_message_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
    }

    public function getMessages(int $userId, int $otherUserId): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.username as sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC",
            [$userId, $otherUserId, $otherUserId, $userId]
        );
    }

    public function markAsRead(int $userId, int $senderId): void
    {
        $this->db->query(
            "UPDATE messages SET read_at = NOW() WHERE receiver_id = ? AND sender_id = ? AND read_at IS NULL",
            [$userId, $senderId]
        );
    }

    public function send(int $senderId, int $receiverId, string $message, ?int $productId = null): int
    {
        $data = [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $message,
        ];
        if ($productId) {
            $data['product_id'] = $productId;
        }
        return $this->db->insert('messages', $data);
    }

    public function getUnreadCount(int $userId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM messages WHERE receiver_id = ? AND read_at IS NULL",
            [$userId]
        );
        return (int) $row['cnt'];
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.username as sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.receiver_id = ?
             ORDER BY m.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }
}
```

- [ ] **Step 7: Implement Forum model**

Create `src/Models/Forum.php`:
```php
<?php

namespace App\Models;

use App\Core\Database;

class Forum
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT fc.*,
                (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id) as topic_count,
                (SELECT COUNT(*) FROM forum_replies fr
                 JOIN forum_topics ft2 ON fr.topic_id = ft2.id
                 WHERE ft2.category_id = fc.id) as reply_count
             FROM forum_categories fc ORDER BY fc.name ASC"
        );
    }

    public function findCategoryById(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM forum_categories WHERE id = ?", [$id]);
    }

    public function createCategory(string $name, string $description): int
    {
        return $this->db->insert('forum_categories', [
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function getTopics(int $categoryId): array
    {
        return $this->db->fetchAll(
            "SELECT ft.*, u.username,
                (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id) as reply_count,
                (SELECT MAX(fr2.created_at) FROM forum_replies fr2 WHERE fr2.topic_id = ft.id) as last_reply_at
             FROM forum_topics ft
             JOIN users u ON ft.user_id = u.id
             WHERE ft.category_id = ?
             ORDER BY ft.updated_at DESC",
            [$categoryId]
        );
    }

    public function findTopicById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT ft.*, u.username, fc.name as category_name, fc.id as category_id
             FROM forum_topics ft
             JOIN users u ON ft.user_id = u.id
             JOIN forum_categories fc ON ft.category_id = fc.id
             WHERE ft.id = ?",
            [$id]
        );
    }

    public function createTopic(int $categoryId, int $userId, string $title, string $content): int
    {
        return $this->db->insert('forum_topics', [
            'category_id' => $categoryId,
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function incrementViews(int $topicId): void
    {
        $this->db->query("UPDATE forum_topics SET views = views + 1 WHERE id = ?", [$topicId]);
    }

    public function getReplies(int $topicId): array
    {
        return $this->db->fetchAll(
            "SELECT fr.*, u.username FROM forum_replies fr
             JOIN users u ON fr.user_id = u.id
             WHERE fr.topic_id = ?
             ORDER BY fr.created_at ASC",
            [$topicId]
        );
    }

    public function createReply(int $topicId, int $userId, string $content): int
    {
        $id = $this->db->insert('forum_replies', [
            'topic_id' => $topicId,
            'user_id' => $userId,
            'content' => $content,
        ]);
        $this->db->query(
            "UPDATE forum_topics SET updated_at = NOW() WHERE id = ?",
            [$topicId]
        );
        return $id;
    }

    public function getTopicsByUser(int $userId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT ft.*, fc.name as category_name FROM forum_topics ft
             JOIN forum_categories fc ON ft.category_id = fc.id
             WHERE ft.user_id = ? ORDER BY ft.created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function getUserStats(int $userId): array
    {
        $topics = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM forum_topics WHERE user_id = ?", [$userId]
        );
        $replies = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM forum_replies WHERE user_id = ?", [$userId]
        );
        return [
            'topics' => (int) $topics['cnt'],
            'replies' => (int) $replies['cnt'],
        ];
    }
}
```

- [ ] **Step 8: Implement Review model**

Create `src/Models/Review.php`:
```php
<?php

namespace App\Models;

use App\Core\Database;

class Review
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getForProduct(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, u.username FROM reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.product_id = ?
             ORDER BY r.created_at DESC",
            [$productId]
        );
    }

    public function getForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, u.username, p.name as product_name FROM reviews r
             JOIN users u ON r.user_id = u.id
             JOIN products p ON r.product_id = p.id
             WHERE p.user_id = ?
             ORDER BY r.created_at DESC",
            [$userId]
        );
    }

    public function create(int $userId, int $productId, int $rating, string $comment): int
    {
        return $this->db->insert('reviews', [
            'user_id' => $userId,
            'product_id' => $productId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }
}
```

- [ ] **Step 9: Run all model tests**

```bash
./vendor/bin/phpunit tests/Models/
```

Expected: All tests PASS.

- [ ] **Step 10: Commit**

```bash
git add src/Models/ tests/Models/
git commit -m "Add Model layer: User, Product, Message, Forum, Review"
```

---

## Task 10: Migrate Controllers to PSR-4

**Files:**
- Create: `src/Controllers/BaseController.php`
- Create: `src/Controllers/AuthController.php`
- Create: `src/Controllers/HomeController.php`
- Create: `src/Controllers/ProductController.php`
- Create: `src/Controllers/ForumController.php`
- Create: `src/Controllers/MessageController.php`
- Create: `src/Controllers/ProfileController.php`
- Create: `src/Controllers/DashboardController.php`
- Create: `src/Controllers/AdminController.php`

This task migrates all 8 legacy controllers to PSR-4 namespaced classes that use Models instead of direct DB queries. The controllers preserve all existing functionality.

- [ ] **Step 1: Implement BaseController**

Rewrite `src/Controllers/BaseController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;

abstract class BaseController
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function render(string $view, array $data = []): void
    {
        // Inject common data
        $data['user'] = $this->getUser();
        $data['flash'] = Session::getFlash();
        View::render($view, $data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function getUser(): ?array
    {
        $userId = Session::userId();
        if (!$userId) {
            return null;
        }
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    protected function isLoggedIn(): bool
    {
        return Session::isLoggedIn();
    }

    protected function isAdmin(): bool
    {
        return Session::isAdmin();
    }

    protected function userId(): ?int
    {
        return Session::userId();
    }
}
```

- [ ] **Step 2: Implement AuthController**

Create `src/Controllers/AuthController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\User;

class AuthController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->flash('error', 'Vul alle velden in.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            // Rate limiting: max 5 attempts per 15 minutes
            $attempts = Session::get('login_attempts', []);
            $attempts = array_filter($attempts, fn($t) => $t > time() - 900);
            if (count($attempts) >= 5) {
                $this->flash('error', 'Te veel inlogpogingen. Probeer het over 15 minuten opnieuw.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            $user = $this->userModel->findByUsername($username);

            if (!$user || !password_verify($password, $user['password'])) {
                $attempts[] = time();
                Session::set('login_attempts', $attempts);
                $this->flash('error', 'Ongeldige gebruikersnaam of wachtwoord.');
                $this->render('auth/login', ['title' => 'Inloggen', 'username' => $username]);
                return;
            }

            // Success — clear attempts, set session
            Session::remove('login_attempts');
            Session::set('user_id', $user['id']);
            Session::set('username', $user['username']);
            Session::set('role', $user['role']);

            $this->flash('success', 'Welkom terug, ' . $user['username'] . '!');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/login', ['title' => 'Inloggen']);
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            $errors = [];

            if (strlen($username) < 3) {
                $errors[] = 'Gebruikersnaam moet minimaal 3 tekens zijn.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Ongeldig e-mailadres.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Wachtwoord moet minimaal 8 tekens zijn.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Wachtwoorden komen niet overeen.';
            }
            if ($this->userModel->existsWithUsername($username)) {
                $errors[] = 'Gebruikersnaam is al in gebruik.';
            }
            if ($this->userModel->existsWithEmail($email)) {
                $errors[] = 'E-mailadres is al in gebruik.';
            }

            if (!empty($errors)) {
                $this->flash('error', implode('<br>', $errors));
                $this->render('auth/register', [
                    'title' => 'Registreren',
                    'username' => $username,
                    'email' => $email,
                ]);
                return;
            }

            $this->userModel->create($username, $email, $password);
            $this->flash('success', 'Account aangemaakt! Je kunt nu inloggen.');
            $this->redirect('/auth/login');
            return;
        }

        $this->render('auth/register', ['title' => 'Registreren']);
    }

    public function logout(): void
    {
        Session::destroy();
        session_start(); // Restart for flash message
        $this->flash('success', 'Je bent uitgelogd.');
        $this->redirect('/auth/login');
    }
}
```

- [ ] **Step 3: Implement HomeController**

Rewrite `src/Controllers/HomeController.php`:
```php
<?php

namespace App\Controllers;

use App\Models\Product;

class HomeController extends BaseController
{
    private Product $productModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $recentProducts = $this->productModel->getRecent(8);
        $categories = $this->productModel->getCategoryCounts();

        $this->render('home/index', [
            'title' => 'Home',
            'recent_products' => $recentProducts,
            'categories' => $categories,
        ]);
    }
}
```

- [ ] **Step 4: Implement ProductController**

Create `src/Controllers/ProductController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Models\Product;

class ProductController extends BaseController
{
    private Product $productModel;

    private const ALLOWED_CATEGORIES = [
        'Servers', 'Netwerk', 'Storage', 'Workstations',
        'Laptops', 'Componenten', 'Randapparatuur', 'Datacenter', 'Overig',
    ];
    private const ALLOWED_STATES = ['Nieuw', 'Als nieuw', 'Gebruikt', 'Voor reparatie'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $filters = [
            'category' => $_GET['category'] ?? null,
            'search' => $_GET['search'] ?? null,
            'state' => $_GET['state'] ?? null,
            'sort' => $_GET['sort'] ?? 'newest',
        ];

        $products = $this->productModel->getApproved($filters);

        $this->render('product/index', [
            'title' => 'Producten',
            'products' => $products,
            'filters' => $filters,
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function view(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product) {
            $this->flash('error', 'Product niet gevonden.');
            $this->redirect('/product');
            return;
        }

        $images = $this->productModel->getImages((int) $id);
        $tags = $this->productModel->getTags((int) $id);
        $reviewModel = new \App\Models\Review();
        $reviews = $reviewModel->getForProduct((int) $id);

        $this->render('product/view', [
            'title' => $product['name'],
            'product' => $product,
            'images' => $images,
            'tags' => $tags,
            'reviews' => $reviews,
        ]);
    }

    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $category = $_POST['category'] ?? '';
            $state = $_POST['state'] ?? '';
            $price = $_POST['price'] ?? '';
            $specs = trim($_POST['specs'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            $errors = [];
            if (empty($name)) $errors[] = 'Naam is verplicht.';
            if (!in_array($category, self::ALLOWED_CATEGORIES)) $errors[] = 'Ongeldige categorie.';
            if (!in_array($state, self::ALLOWED_STATES)) $errors[] = 'Ongeldige staat.';
            if (!is_numeric($price) || $price < 0) $errors[] = 'Ongeldige prijs.';
            if (count($tags) > (int) Config::get('MAX_PRODUCT_TAGS', 5)) {
                $errors[] = 'Maximaal ' . Config::get('MAX_PRODUCT_TAGS', 5) . ' tags.';
            }

            if (!empty($errors)) {
                $this->flash('error', implode('<br>', $errors));
                $this->render('product/add', [
                    'title' => 'Product Toevoegen',
                    'categories' => self::ALLOWED_CATEGORIES,
                    'states' => self::ALLOWED_STATES,
                    'input' => $_POST,
                ]);
                return;
            }

            $productId = $this->productModel->create([
                'user_id' => $this->userId(),
                'name' => $name,
                'category' => $category,
                'state' => $state,
                'price' => (float) $price,
                'specs' => $specs,
                'description' => $description,
                'approved' => Config::get('REQUIRE_APPROVAL', true) ? 0 : 1,
            ]);

            // Handle image uploads
            $this->handleImageUploads($productId);

            // Handle tags
            foreach (array_slice($tags, 0, (int) Config::get('MAX_PRODUCT_TAGS', 5)) as $tag) {
                if (!empty($tag)) {
                    $this->productModel->addTag($productId, $tag);
                }
            }

            $this->flash('success', 'Product toegevoegd! Het wordt beoordeeld door een moderator.');
            $this->redirect('/product/view/' . $productId);
            return;
        }

        $this->render('product/add', [
            'title' => 'Product Toevoegen',
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function edit(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product || $product['user_id'] !== $this->userId()) {
            $this->flash('error', 'Product niet gevonden of geen toegang.');
            $this->redirect('/dashboard');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'category' => $_POST['category'] ?? '',
                'state' => $_POST['state'] ?? '',
                'price' => (float) ($_POST['price'] ?? 0),
                'specs' => trim($_POST['specs'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            $this->productModel->update((int) $id, $data);

            // Re-handle tags
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
            $this->productModel->deleteTags((int) $id);
            foreach (array_slice($tags, 0, (int) Config::get('MAX_PRODUCT_TAGS', 5)) as $tag) {
                if (!empty($tag)) {
                    $this->productModel->addTag((int) $id, $tag);
                }
            }

            // Handle new image uploads
            $this->handleImageUploads((int) $id);

            $this->flash('success', 'Product bijgewerkt.');
            $this->redirect('/product/view/' . $id);
            return;
        }

        $images = $this->productModel->getImages((int) $id);
        $tags = $this->productModel->getTags((int) $id);

        $this->render('product/edit', [
            'title' => 'Product Bewerken',
            'product' => $product,
            'images' => $images,
            'tags' => $tags,
            'categories' => self::ALLOWED_CATEGORIES,
            'states' => self::ALLOWED_STATES,
        ]);
    }

    public function delete(string $id): void
    {
        $product = $this->productModel->findById((int) $id);
        if (!$product || ($product['user_id'] !== $this->userId() && !$this->isAdmin())) {
            $this->flash('error', 'Geen toegang.');
            $this->redirect('/dashboard');
            return;
        }

        // Delete image files
        $images = $this->productModel->getImages((int) $id);
        foreach ($images as $image) {
            $path = __DIR__ . '/../../uploads/products/' . $image['image_url'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->productModel->delete((int) $id);
        $this->flash('success', 'Product verwijderd.');
        $this->redirect('/dashboard');
    }

    private function handleImageUploads(int $productId): void
    {
        if (empty($_FILES['images']['name'][0])) {
            return;
        }

        $uploadDir = dirname(__DIR__, 2) . '/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxImages = (int) Config::get('MAX_PRODUCT_IMAGES', 5);
        $uploaded = 0;

        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($uploaded >= $maxImages) break;
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['images']['size'][$i] > self::MAX_IMAGE_SIZE) continue;

            $tmpFile = $_FILES['images']['tmp_name'][$i];

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) continue;

            // Validate extension
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS)) continue;

            // Generate safe filename
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($tmpFile, $destination)) {
                $this->productModel->addImage($productId, $filename);
                $uploaded++;
            }
        }
    }
}
```

- [ ] **Step 5: Implement remaining controllers**

Create `src/Controllers/ForumController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\Forum;

class ForumController extends BaseController
{
    private Forum $forumModel;

    public function __construct()
    {
        parent::__construct();
        $this->forumModel = new Forum();
    }

    public function index(): void
    {
        $categories = $this->forumModel->getCategories();
        $this->render('forum/index', [
            'title' => 'Forum',
            'categories' => $categories,
        ]);
    }

    public function category(string $id): void
    {
        $category = $this->forumModel->findCategoryById((int) $id);
        if (!$category) {
            $this->flash('error', 'Categorie niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        $topics = $this->forumModel->getTopics((int) $id);
        $this->render('forum/category', [
            'title' => $category['name'],
            'category' => $category,
            'topics' => $topics,
        ]);
    }

    public function topic(string $id): void
    {
        $topic = $this->forumModel->findTopicById((int) $id);
        if (!$topic) {
            $this->flash('error', 'Topic niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        $this->forumModel->incrementViews((int) $id);
        $replies = $this->forumModel->getReplies((int) $id);

        $this->render('forum/topic', [
            'title' => $topic['title'],
            'topic' => $topic,
            'replies' => $replies,
        ]);
    }

    public function new_category(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $this->flash('error', 'Naam is verplicht.');
                $this->render('forum/new_category', ['title' => 'Nieuwe Categorie']);
                return;
            }

            $this->forumModel->createCategory($name, $description);
            $this->flash('success', 'Categorie aangemaakt.');
            $this->redirect('/forum');
            return;
        }

        $this->render('forum/new_category', ['title' => 'Nieuwe Categorie']);
    }

    public function new_topic(string $category_id): void
    {
        $category = $this->forumModel->findCategoryById((int) $category_id);
        if (!$category) {
            $this->flash('error', 'Categorie niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                $this->flash('error', 'Titel en inhoud zijn verplicht.');
                $this->render('forum/new_topic', [
                    'title' => 'Nieuw Topic',
                    'category' => $category,
                ]);
                return;
            }

            // Sanitize HTML content with HTMLPurifier
            $content = self::purify($content);

            $topicId = $this->forumModel->createTopic(
                (int) $category_id,
                $this->userId(),
                $title,
                $content
            );
            $this->flash('success', 'Topic aangemaakt.');
            $this->redirect('/forum/topic/' . $topicId);
            return;
        }

        $this->render('forum/new_topic', [
            'title' => 'Nieuw Topic',
            'category' => $category,
        ]);
    }

    /**
     * Sanitize HTML using HTMLPurifier — allows safe formatting, strips XSS.
     */
    private static function purify(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,b,i,strong,em,a[href],ul,ol,li,code,pre,blockquote');
        $config->set('AutoFormat.AutoParagraph', true);
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

    public function reply(string $topic_id): void
    {
        $content = self::purify(trim($_POST['content'] ?? ''));
        if (empty($content)) {
            $this->flash('error', 'Reactie mag niet leeg zijn.');
            $this->redirect('/forum/topic/' . $topic_id);
            return;
        }

        $this->forumModel->createReply((int) $topic_id, $this->userId(), $content);
        $this->flash('success', 'Reactie geplaatst.');
        $this->redirect('/forum/topic/' . $topic_id);
    }
}
```

Create `src/Controllers/MessageController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\Message;
use App\Models\Product;

class MessageController extends BaseController
{
    private Message $messageModel;

    public function __construct()
    {
        parent::__construct();
        $this->messageModel = new Message();
    }

    public function index(string $user_id = ''): void
    {
        $userId = $this->userId();
        $conversations = $this->messageModel->getConversations($userId);

        $selectedUserId = !empty($user_id) ? (int) $user_id : null;
        $messages = [];
        $otherUser = null;

        if ($selectedUserId) {
            $messages = $this->messageModel->getMessages($userId, $selectedUserId);
            $this->messageModel->markAsRead($userId, $selectedUserId);
            $otherUser = $this->db->fetch("SELECT id, username FROM users WHERE id = ?", [$selectedUserId]);
        } elseif (!empty($conversations)) {
            $selectedUserId = (int) $conversations[0]['other_user_id'];
            $messages = $this->messageModel->getMessages($userId, $selectedUserId);
            $this->messageModel->markAsRead($userId, $selectedUserId);
            $otherUser = $this->db->fetch("SELECT id, username FROM users WHERE id = ?", [$selectedUserId]);
        }

        // Get user's products for context
        $productModel = new Product();
        $userProducts = $productModel->getByUser($userId);

        $this->render('messages/index', [
            'title' => 'Berichten',
            'conversations' => $conversations,
            'messages' => $messages,
            'other_user' => $otherUser,
            'selected_user_id' => $selectedUserId,
            'user_products' => $userProducts,
        ]);
    }

    public function send(): void
    {
        $receiverId = (int) ($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $productId = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : null;

        if ($receiverId === 0 || empty($message)) {
            $this->flash('error', 'Ontvanger en bericht zijn verplicht.');
            $this->redirect('/message');
            return;
        }

        $this->messageModel->send($this->userId(), $receiverId, $message, $productId);
        $this->redirect('/message/conversation/' . $receiverId);
    }
}
```

Create `src/Controllers/ProfileController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use App\Models\Forum;

class ProfileController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }

    public function view(string $id): void
    {
        $user = $this->userModel->findById((int) $id);
        if (!$user) {
            $this->flash('error', 'Gebruiker niet gevonden.');
            $this->redirect('/');
            return;
        }

        $productModel = new Product();
        $reviewModel = new Review();
        $products = $productModel->getByUser((int) $id);
        $reviews = $reviewModel->getForUser((int) $id);

        $this->render('profile/view', [
            'title' => $user['username'],
            'profile_user' => $user,
            'products' => $products,
            'reviews' => $reviews,
        ]);
    }

    public function index(): void
    {
        $userId = $this->userId();
        $user = $this->userModel->findById($userId);

        $productModel = new Product();
        $forumModel = new Forum();
        $products = $productModel->getByUser($userId);
        $forumStats = $forumModel->getUserStats($userId);

        $favCount = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM favorites WHERE user_id = ?", [$userId]
        );

        $this->render('profile/index', [
            'title' => 'Mijn Profiel',
            'profile_user' => $user,
            'products' => $products,
            'forum_stats' => $forumStats,
            'favorite_count' => (int) $favCount['cnt'],
        ]);
    }

    public function products(string $id): void
    {
        $productModel = new Product();
        $products = $productModel->getByUser((int) $id);
        $this->render('profile/_products', ['products' => $products]);
    }

    public function topics(string $id): void
    {
        $forumModel = new Forum();
        $topics = $forumModel->getTopicsByUser((int) $id);
        $this->render('profile/_topics', ['topics' => $topics]);
    }

    public function edit(): void
    {
        $userId = $this->userId();
        $user = $this->userModel->findById($userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

            $errors = [];

            if (strlen($username) < 3) {
                $errors[] = 'Gebruikersnaam moet minimaal 3 tekens zijn.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Ongeldig e-mailadres.';
            }
            if ($this->userModel->existsWithUsername($username, $userId)) {
                $errors[] = 'Gebruikersnaam is al in gebruik.';
            }
            if ($this->userModel->existsWithEmail($email, $userId)) {
                $errors[] = 'E-mailadres is al in gebruik.';
            }

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $errors[] = 'Nieuw wachtwoord moet minimaal 8 tekens zijn.';
                }
                if (!password_verify($currentPassword, $user['password'])) {
                    $errors[] = 'Huidig wachtwoord is onjuist.';
                }
            }

            if (!empty($errors)) {
                $this->flash('error', implode('<br>', $errors));
                $this->render('profile/edit', [
                    'title' => 'Profiel Bewerken',
                    'profile_user' => $user,
                ]);
                return;
            }

            $this->userModel->updateProfile($userId, [
                'username' => $username,
                'email' => $email,
            ]);

            if (!empty($newPassword)) {
                $this->userModel->updatePassword($userId, $newPassword);
            }

            Session::set('username', $username);
            $this->flash('success', 'Profiel bijgewerkt.');
            $this->redirect('/profile');
            return;
        }

        $this->render('profile/edit', [
            'title' => 'Profiel Bewerken',
            'profile_user' => $user,
        ]);
    }

    public function delete(): void
    {
        $this->userModel->delete($this->userId());
        Session::destroy();
        session_start();
        Session::flash('success', 'Account verwijderd.');
        header('Location: /');
        exit;
    }
}
```

Create `src/Controllers/DashboardController.php`:
```php
<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Message;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $userId = $this->userId();
        $productModel = new Product();
        $messageModel = new Message();

        $products = $productModel->getByUser($userId);
        $unreadCount = $messageModel->getUnreadCount($userId);
        $recentMessages = $messageModel->getRecent($userId, 5);

        // Favorites
        $favorites = $this->db->fetchAll(
            "SELECT p.*, u.username,
             (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as image_url
             FROM favorites f
             JOIN products p ON f.product_id = p.id
             JOIN users u ON p.user_id = u.id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC",
            [$userId]
        );

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'products' => $products,
            'unread_count' => $unreadCount,
            'recent_messages' => $recentMessages,
            'favorites' => $favorites,
        ]);
    }
}
```

Create `src/Controllers/AdminController.php`:
```php
<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\User;

class AdminController extends BaseController
{
    private Product $productModel;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
        $this->userModel = new User();
    }

    public function index(): void
    {
        $stats = [
            'total_products' => $this->db->fetch("SELECT COUNT(*) as cnt FROM products")['cnt'],
            'pending_products' => $this->db->fetch("SELECT COUNT(*) as cnt FROM products WHERE approved = 0")['cnt'],
            'total_users' => $this->db->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'],
            'total_reviews' => $this->db->fetch("SELECT COUNT(*) as cnt FROM reviews")['cnt'],
        ];

        $recentProducts = $this->db->fetchAll(
            "SELECT p.*, u.username FROM products p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 10"
        );
        $recentUsers = $this->db->fetchAll(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT 10"
        );

        $this->render('admin/index', [
            'title' => 'Admin Dashboard',
            'stats' => $stats,
            'recent_products' => $recentProducts,
            'recent_users' => $recentUsers,
        ]);
    }

    public function products(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'category' => $_GET['category'] ?? null,
        ];

        $status = $_GET['status'] ?? 'pending';
        if ($status === 'pending') {
            $filters['approved'] = 0;
        } elseif ($status === 'approved') {
            $filters['approved'] = 1;
        }

        $products = $this->productModel->getForAdmin($filters);

        $this->render('admin/products', [
            'title' => 'Producten Beheren',
            'products' => $products,
            'current_status' => $status,
        ]);
    }

    public function approveProduct(string $id): void
    {
        $this->productModel->approve((int) $id);
        $this->flash('success', 'Product goedgekeurd.');
        $this->redirect('/admin/products');
    }

    public function rejectProduct(string $id): void
    {
        $this->productModel->reject((int) $id);
        $this->flash('success', 'Product afgewezen.');
        $this->redirect('/admin/products');
    }

    public function deleteProduct(string $id): void
    {
        $this->productModel->delete((int) $id);
        $this->flash('success', 'Product verwijderd.');
        $this->redirect('/admin/products');
    }

    public function users(): void
    {
        $search = $_GET['search'] ?? null;
        $role = $_GET['role'] ?? null;

        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];

        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        if ($search) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY created_at DESC";
        $users = $this->db->fetchAll($sql, $params);

        $this->render('admin/users', [
            'title' => 'Gebruikers Beheren',
            'users' => $users,
            'current_role' => $role,
        ]);
    }

    public function toggleAdmin(string $id): void
    {
        $this->userModel->toggleAdmin((int) $id);
        $this->flash('success', 'Gebruikersrol aangepast.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(string $id): void
    {
        $this->userModel->delete((int) $id);
        $this->flash('success', 'Gebruiker verwijderd.');
        $this->redirect('/admin/users');
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Controllers/
git commit -m "Migrate all controllers to PSR-4 namespace with Model layer"
```

---

## Task 11: Migrate Views & Layout

**Files:**
- Create: `src/Views/layouts/main.php`
- Move and update: all views from `views/` to `src/Views/`

The views need minimal changes: update `htmlspecialchars()` calls to use `View::e()`, add CSRF fields to all forms, and reference the new layout.

- [ ] **Step 1: Create main layout**

Create `src/Views/layouts/main.php` (merges current `includes/header.php` + `includes/footer.php`):
```php
<?php
use App\Core\View;
use App\Core\Session;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($title ?? 'Cloudmarkplaats') ?> - Cloudmarkplaats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://unpkg.com/hyperscript.org@0.9.12"></script>
    <meta name="csrf-token" content="<?= View::e(Session::get('_csrf_token', '')) ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/">Cloudmarkplaats</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/product">Producten</a></li>
                <li class="nav-item"><a class="nav-link" href="/forum">Forum</a></li>
                <?php if (Session::isAdmin()): ?>
                <li class="nav-item"><a class="nav-link" href="/admin">Admin</a></li>
                <?php endif; ?>
            </ul>
            <form class="d-flex me-3" action="/product" method="GET">
                <input class="form-control me-2" type="search" name="search" placeholder="Zoek hardware..." aria-label="Zoek">
                <button class="btn btn-outline-light" type="submit">Zoek</button>
            </form>
            <ul class="navbar-nav">
                <?php if (Session::isLoggedIn()): ?>
                <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="/message">Berichten</a></li>
                <li class="nav-item"><a class="nav-link" href="/profile"><?= View::e(Session::get('username')) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="/auth/logout">Uitloggen</a></li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="/auth/login">Inloggen</a></li>
                <li class="nav-item"><a class="nav-link" href="/auth/register">Registreren</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if (!empty($flash)): ?>
<div class="container mt-3">
    <div class="alert alert-<?= View::e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<main class="container py-4">
    <?= $content ?>
</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>Cloudmarkplaats</h5>
                <p class="text-muted">Het onafhankelijke handelsplatform voor de IT community. 100% gratis, mogelijk gemaakt door onze sponsors.</p>
            </div>
            <div class="col-md-4">
                <h5>Links</h5>
                <ul class="list-unstyled">
                    <li><a href="/product" class="text-muted">Marktplaats</a></li>
                    <li><a href="/forum" class="text-muted">Forum</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Account</h5>
                <ul class="list-unstyled">
                    <?php if (Session::isLoggedIn()): ?>
                    <li><a href="/dashboard" class="text-muted">Dashboard</a></li>
                    <li><a href="/profile" class="text-muted">Profiel</a></li>
                    <?php else: ?>
                    <li><a href="/auth/login" class="text-muted">Inloggen</a></li>
                    <li><a href="/auth/register" class="text-muted">Registreren</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="text-muted">
        <p class="text-center text-muted mb-0">&copy; <?= date('Y') ?> Cloudmarkplaats.nl</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pass CSRF token to HTMX requests
document.body.addEventListener('htmx:configRequest', function(event) {
    event.detail.headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
});
// Tooltip and popover init
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
</script>
</body>
</html>
```

- [ ] **Step 2: Copy and update all view files**

Copy each view from `views/` to `src/Views/`, making these changes in each file:
1. Replace `<?= htmlspecialchars($var) ?>` with `<?= \App\Core\View::e($var) ?>`
2. Add `<?= \App\Core\View::csrfField() ?>` inside every `<form method="POST">` tag
3. Remove any `require` of `header.php` or `footer.php` (the layout handles this)
4. Ensure variables use the names passed from the new controllers

The views to copy:
- `views/home/index.php` → `src/Views/home/index.php`
- `views/auth/login.php` → `src/Views/auth/login.php`
- `views/auth/register.php` → `src/Views/auth/register.php`
- `views/auth/dashboard.php` → remove (replaced by `dashboard/index.php`)
- `views/dashboard/index.php` → `src/Views/dashboard/index.php`
- `views/product/index.php` → `src/Views/product/index.php`
- `views/product/view.php` → `src/Views/product/view.php`
- `views/product/add.php` → `src/Views/product/add.php`
- `views/product/edit.php` → `src/Views/product/edit.php`
- `views/forum/index.php` → `src/Views/forum/index.php`
- `views/forum/category.php` → `src/Views/forum/category.php`
- `views/forum/topic.php` → `src/Views/forum/topic.php`
- `views/forum/new_category.php` → `src/Views/forum/new_category.php`
- `views/forum/new_topic.php` → `src/Views/forum/new_topic.php`
- `views/messages/index.php` → `src/Views/messages/index.php`
- `views/profile/index.php` → `src/Views/profile/index.php`
- `views/profile/view.php` → `src/Views/profile/view.php`
- `views/profile/edit.php` → `src/Views/profile/edit.php`
- `views/profile/_products.php` → `src/Views/profile/_products.php`
- `views/profile/_topics.php` → `src/Views/profile/_topics.php`
- `views/errors/404.php` → `src/Views/errors/404.php`

Key pattern for CSRF in every POST form:
```php
<form method="POST" action="/some/action">
    <?= \App\Core\View::csrfField() ?>
    <!-- form fields -->
</form>
```

Key pattern for escaping output:
```php
<!-- Before -->
<?= htmlspecialchars($product['name']) ?>

<!-- After -->
<?= \App\Core\View::e($product['name']) ?>
```

- [ ] **Step 3: Create admin views**

Create `src/Views/admin/index.php`:
```php
<?php use App\Core\View; ?>
<h1>Admin Dashboard</h1>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_products'] ?></h3>
                <p class="text-muted">Producten</p>
                <?php if ($stats['pending_products'] > 0): ?>
                <span class="badge bg-warning"><?= (int) $stats['pending_products'] ?> wachtend</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_users'] ?></h3>
                <p class="text-muted">Gebruikers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= (int) $stats['total_reviews'] ?></h3>
                <p class="text-muted">Reviews</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recente Producten</h5>
                <a href="/admin/products" class="btn btn-sm btn-outline-primary">Alle bekijken</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Naam</th><th>Verkoper</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_products as $p): ?>
                    <tr>
                        <td><?= View::e($p['name']) ?></td>
                        <td><?= View::e($p['username']) ?></td>
                        <td><?= $p['approved'] ? '<span class="badge bg-success">Goedgekeurd</span>' : '<span class="badge bg-warning">Wachtend</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recente Gebruikers</h5>
                <a href="/admin/users" class="btn btn-sm btn-outline-primary">Alle bekijken</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Gebruiker</th><th>Email</th><th>Rol</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td><?= View::e($u['username']) ?></td>
                        <td><?= View::e($u['email']) ?></td>
                        <td><?= $u['role'] === 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
```

Create `src/Views/admin/products.php`:
```php
<?php use App\Core\View; ?>
<h1>Producten Beheren</h1>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $current_status === 'pending' ? 'active' : '' ?>" href="/admin/products?status=pending">Wachtend</a></li>
    <li class="nav-item"><a class="nav-link <?= $current_status === 'approved' ? 'active' : '' ?>" href="/admin/products?status=approved">Goedgekeurd</a></li>
    <li class="nav-item"><a class="nav-link <?= $current_status === 'all' ? 'active' : '' ?>" href="/admin/products?status=all">Alles</a></li>
</ul>

<?php if (empty($products)): ?>
<p class="text-muted">Geen producten gevonden.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Naam</th><th>Categorie</th><th>Prijs</th><th>Verkoper</th><th>Status</th><th>Acties</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><a href="/product/view/<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?></a></td>
            <td><?= View::e($p['category']) ?></td>
            <td>&euro;<?= number_format((float) $p['price'], 2, ',', '.') ?></td>
            <td><?= View::e($p['username']) ?></td>
            <td><?= $p['approved'] ? '<span class="badge bg-success">Goedgekeurd</span>' : '<span class="badge bg-warning">Wachtend</span>' ?></td>
            <td>
                <?php if (!$p['approved']): ?>
                <form method="POST" action="/admin/products/approve/<?= (int) $p['id'] ?>" class="d-inline">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-success">Goedkeuren</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="/admin/products/delete/<?= (int) $p['id'] ?>" class="d-inline" onsubmit="return confirm('Weet je het zeker?')">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-danger">Verwijderen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
```

Create `src/Views/admin/users.php`:
```php
<?php use App\Core\View; ?>
<h1>Gebruikers Beheren</h1>

<form class="row g-2 mb-3" method="GET" action="/admin/users">
    <div class="col-auto">
        <input type="text" class="form-control" name="search" placeholder="Zoek gebruiker..." value="<?= View::e($_GET['search'] ?? '') ?>">
    </div>
    <div class="col-auto">
        <select class="form-select" name="role">
            <option value="">Alle rollen</option>
            <option value="user" <?= ($current_role ?? '') === 'user' ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= ($current_role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary" type="submit">Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>ID</th><th>Gebruiker</th><th>Email</th><th>Rol</th><th>Lid sinds</th><th>Acties</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= View::e($u['username']) ?></td>
            <td><?= View::e($u['email']) ?></td>
            <td><?= $u['role'] === 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?></td>
            <td><?= View::e($u['created_at']) ?></td>
            <td>
                <form method="POST" action="/admin/users/toggle-admin/<?= (int) $u['id'] ?>" class="d-inline">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-outline-primary">
                        <?= $u['role'] === 'admin' ? 'Maak User' : 'Maak Admin' ?>
                    </button>
                </form>
                <form method="POST" action="/admin/users/delete/<?= (int) $u['id'] ?>" class="d-inline" onsubmit="return confirm('Weet je het zeker?')">
                    <?= View::csrfField() ?>
                    <button class="btn btn-sm btn-danger">Verwijderen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

- [ ] **Step 4: Commit**

```bash
git add src/Views/
git commit -m "Migrate all views to src/Views with CSRF tokens and auto-escaping"
```

---

## Task 12: New index.php Bootstrap & Cleanup

**Files:**
- Rewrite: `index.php`
- Delete: `config.php`, `Database.php`, `counter.php`, `install.php`, `install.sql`
- Delete: `controllers/` directory
- Delete: `includes/` directory
- Delete: `views/` directory
- Delete: old `src/Views/home/index.php`, `src/Views/layouts/main.php` (replaced in Task 11)

- [ ] **Step 1: Rewrite index.php**

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new \App\Core\App(__DIR__);
$app->run();
```

- [ ] **Step 2: Delete legacy files**

```bash
rm config.php
rm Database.php
rm counter.php
rm install.php
rm install.sql
rm -rf controllers/
rm -rf includes/
rm -rf views/
```

- [ ] **Step 3: Verify the application boots**

```bash
php -S localhost:8000 -t .
```

Open `http://localhost:8000` in a browser. Verify:
- Homepage loads with products and categories
- Navigation shows correct links
- Login/register forms have CSRF tokens
- Flash messages display correctly

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Replace legacy architecture with PSR-4 bootstrap, remove old files"
```

---

## Task 13: Database Migration System

**Files:**
- Create: `migrations/migrate.php`
- Create: `migrations/001_initial_schema.sql`

- [ ] **Step 1: Create migration runner**

Create `migrations/migrate.php`:
```php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

Config::load(dirname(__DIR__));
$db = Database::getInstance();

// Create migrations table if not exists
$db->query("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Get already executed migrations
$executed = $db->fetchAll("SELECT filename FROM migrations ORDER BY filename");
$executedFiles = array_column($executed, 'filename');

// Find migration files
$files = glob(__DIR__ . '/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $executedFiles)) {
        echo "SKIP: {$filename} (already executed)\n";
        continue;
    }

    echo "RUN:  {$filename} ... ";
    $sql = file_get_contents($file);

    try {
        // Execute each SQL statement individually
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->query($statement);
            }
        }

        $db->insert('migrations', ['filename' => $filename]);
        echo "OK\n";
        $count++;
    } catch (\Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nDone. {$count} migration(s) executed.\n";
```

- [ ] **Step 2: Create initial migration from database.sql**

Copy the contents of `database.sql` to `migrations/001_initial_schema.sql`. This is the baseline schema.

- [ ] **Step 3: Test migration**

```bash
php migrations/migrate.php
```

Expected: Either "SKIP" (if tables exist) or "OK" for new tables. Ends with "Done."

- [ ] **Step 4: Remove old setup files**

```bash
rm setup.php setup_forum.php
```

- [ ] **Step 5: Commit**

```bash
git add migrations/ && git add -A
git commit -m "Add database migration system, remove legacy setup scripts"
```

---

## Task 14: Update .htaccess for New Structure

**Files:**
- Modify: `.htaccess`

- [ ] **Step 1: Update .htaccess directory blocking**

The current `.htaccess` blocks access to `/controllers`, `/includes`, `/views` which no longer exist. Update to block `/src` and `/migrations`:

Replace the directory blocking section with:
```apache
# Block access to sensitive directories
RewriteRule ^src/ - [F,L]
RewriteRule ^migrations/ - [F,L]
RewriteRule ^tests/ - [F,L]
RewriteRule ^vendor/ - [F,L]
RewriteRule ^docs/ - [F,L]

# Block access to sensitive files
RewriteRule ^\.env - [F,L]
RewriteRule ^composer\.(json|lock)$ - [F,L]
RewriteRule ^phpunit\.xml$ - [F,L]
```

- [ ] **Step 2: Verify static files still load**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/assets/css/style.css
```

Expected: `200`

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/src/Core/Config.php
```

Expected: `403`

- [ ] **Step 3: Commit**

```bash
git add .htaccess
git commit -m "Update .htaccess for new PSR-4 directory structure"
```

---

## Task 15: Run Full Test Suite & Verify

**Files:** None (verification only)

- [ ] **Step 1: Run all tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (Config, Database, CSRF Middleware, Router, User Model).

- [ ] **Step 2: Manual smoke test**

Start the dev server and verify these flows work:

1. **Homepage** — loads at `/`, shows categories and recent products
2. **Register** — form at `/auth/register` has CSRF token, creates user
3. **Login** — form at `/auth/login` has CSRF token, authenticates user
4. **Dashboard** — shows products, messages, favorites
5. **Add Product** — form has CSRF token, uploads images, creates product
6. **Forum** — categories list, topic view, reply form has CSRF token
7. **Messages** — conversation list, send message
8. **Admin** — dashboard loads, product approve/reject works, user management works
9. **Profile edit** — username/email/password change works
10. **Logout** — destroys session, redirects to login

- [ ] **Step 3: Verify CSRF protection**

Attempt a POST without CSRF token:
```bash
curl -X POST http://localhost:8000/auth/login -d "username=test&password=test"
```

Expected: `403` response with "Invalid CSRF token" message.

- [ ] **Step 4: Verify rate limiting**

Attempt 6 rapid login failures — the 6th should be blocked.

- [ ] **Step 5: Final commit if any fixes needed**

```bash
git add -A
git commit -m "Fix issues found during smoke testing"
```

---

## Summary

| Task | Description | Files Created/Modified |
|------|-------------|----------------------|
| 1 | Environment & Composer | .env.example, .env, composer.json, .gitignore |
| 2 | Config class | src/Core/Config.php, tests |
| 3 | Database class | src/Core/Database.php, tests |
| 4 | Session class | src/Core/Session.php |
| 5 | View renderer | src/Core/View.php |
| 6 | Middleware | 4 middleware files, tests |
| 7 | Router | src/Core/Router.php, tests |
| 8 | App bootstrap | src/Core/App.php, src/routes.php |
| 9 | Models | 5 model files, tests |
| 10 | Controllers | 9 controller files |
| 11 | Views & Layout | ~22 view files |
| 12 | New index.php & cleanup | index.php, delete legacy |
| 13 | Migration system | migrations/ |
| 14 | .htaccess update | .htaccess |
| 15 | Test suite & verify | Verification only |
