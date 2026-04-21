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
