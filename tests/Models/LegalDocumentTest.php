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
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_v88', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 89, 'language' => 'nl', 'content' => 'TEST_v89', 'published_at' => '2026-02-01 00:00:00']);

        $v = $this->model->latestVersion('tos', 'nl');
        $this->assertSame(89, $v);
    }

    public function testLatestVersionIgnoresFuturePublished(): void
    {
        $future = date('Y-m-d H:i:s', time() + 86400);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_current', 'published_at' => '2026-01-01 00:00:00']);
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 90, 'language' => 'nl', 'content' => 'TEST_future', 'published_at' => $future]);

        $this->assertSame(88, $this->model->latestVersion('tos', 'nl'));
    }

    public function testLatestVersionReturnsZeroIfNone(): void
    {
        // Use a type that has no rows at all in the DB
        $this->assertSame(0, $this->model->latestVersion('tos', 'xx'));
    }

    public function testFindReturnsSpecificVersion(): void
    {
        $this->db->insert('legal_documents', ['type' => 'privacy', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_privacy_v88', 'published_at' => '2026-01-01 00:00:00']);
        $doc = $this->model->find('privacy', 88, 'nl');
        $this->assertNotFalse($doc);
        $this->assertSame('TEST_privacy_v88', $doc['content']);
    }

    public function testFindFallsBackToNlWhenLanguageMissing(): void
    {
        $this->db->insert('legal_documents', ['type' => 'tos', 'version' => 88, 'language' => 'nl', 'content' => 'TEST_nl_only', 'published_at' => '2026-01-01 00:00:00']);
        $doc = $this->model->findWithFallback('tos', 88, 'de');
        $this->assertNotFalse($doc);
        $this->assertSame('TEST_nl_only', $doc['content']);
        $this->assertSame('nl', $doc['language']);
    }
}
