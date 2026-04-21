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
