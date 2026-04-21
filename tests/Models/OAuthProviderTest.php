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
