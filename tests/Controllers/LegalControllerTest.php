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
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_CTL_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legalctl_%'");
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    public function testTosActionRendersLatest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        try {
            (new LegalController())->tos();
        } catch (\Throwable $e) {
            // layout may fail in test env — that's fine, we check buffered output
        }
        $output = ob_get_clean();

        // At minimum the current TEST_CTL_tos (version 99, highest) should appear —
        // but because real migrations also seeded a v1 'tos' nl, the latestVersion
        // returns 99 (test) which is what we want. Content from the test v99 should
        // appear in output.
        $this->assertStringContainsString('TEST_CTL_tos', $output);
    }
}
