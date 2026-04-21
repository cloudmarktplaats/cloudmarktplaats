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
