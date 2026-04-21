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

        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_tos_v88', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_priv_v88', 'published_at' => '2026-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM legal_documents WHERE content LIKE 'TEST_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_legal_%'");
    }

    public function testCurrentVersionsReturnsBothTypes(): void
    {
        $v = $this->service->currentVersions('nl');
        $this->assertSame(88, $v['tos']);
        $this->assertSame(88, $v['privacy']);
    }

    public function testUserNeedsAcceptanceIfNeverAccepted(): void
    {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertTrue($this->service->needsAcceptance($user, 'nl'));
    }

    public function testUserNeedsAcceptanceIfOutdated(): void
    {
        $this->db->update('users', ['tos_version' => 88, 'privacy_version' => 88, 'tos_accepted_at' => '2026-01-02 00:00:00', 'privacy_accepted_at' => '2026-01-02 00:00:00'], 'id = ?', [$this->userId]);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 89, 'language' => 'nl', 'content' => 'TEST_tos_v89', 'published_at' => '2026-02-01 00:00:00']);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertTrue($this->service->needsAcceptance($user, 'nl'));
    }

    public function testUserDoesNotNeedAcceptanceIfUpToDate(): void
    {
        $this->db->update('users', ['tos_version' => 88, 'privacy_version' => 88, 'tos_accepted_at' => '2026-01-02 00:00:00', 'privacy_accepted_at' => '2026-01-02 00:00:00'], 'id = ?', [$this->userId]);
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertFalse($this->service->needsAcceptance($user, 'nl'));
    }

    public function testAcceptUpdatesUser(): void
    {
        $this->service->accept($this->userId, 'nl');
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$this->userId]);
        $this->assertSame(88, (int) $user['tos_version']);
        $this->assertSame(88, (int) $user['privacy_version']);
        $this->assertNotNull($user['tos_accepted_at']);
        $this->assertNotNull($user['privacy_accepted_at']);
    }
}
