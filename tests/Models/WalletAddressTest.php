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
