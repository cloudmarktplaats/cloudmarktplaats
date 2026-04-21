<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\WalletAddress;

class WalletAddressTest extends TestCase
{
    // 42-char hex addresses (0x + 40 hex chars) used in tests. Prefix 0xfeedbeef
    // reserved for test fixtures so LIKE-based cleanup does not touch real data.
    private const TEST_ADDR_1 = '0xfeedbeef00000000000000000000000000000001';
    private const TEST_ADDR_2 = '0xfeedbeef00000000000000000000000000000002';
    private const TEST_ADDR_3 = '0xfeedbeef00000000000000000000000000000003';
    private const TEST_ADDR_4 = '0xfeedbeef00000000000000000000000000000004';
    private const TEST_ADDR_5 = '0xfeedbeef00000000000000000000000000000005';
    private const TEST_ADDR_6 = '0xfeedbeef00000000000000000000000000000006';
    private const TEST_ADDR_UPPER = '0xFEEDBEEF000000000000000000000000000000AA';

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

        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_wallet_%'");
        $this->userId = $this->db->insert('users', [
            'username' => 'test_wallet_u1',
            'email' => 'test_wallet_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_wallet_%'");
    }

    public function testLinkStoresLowercased(): void
    {
        $id = $this->model->link($this->userId, self::TEST_ADDR_UPPER, 1);
        $this->assertGreaterThan(0, $id);

        $row = $this->db->fetch("SELECT address FROM wallet_addresses WHERE id = ?", [$id]);
        $this->assertSame(strtolower(self::TEST_ADDR_UPPER), $row['address']);
    }

    public function testFindByAddressIsCaseInsensitive(): void
    {
        $this->model->link($this->userId, self::TEST_ADDR_1, 1);
        $row = $this->model->findByAddress(strtoupper(self::TEST_ADDR_1));
        $this->assertNotFalse($row);
    }

    public function testDuplicateAddressThrows(): void
    {
        $this->model->link($this->userId, self::TEST_ADDR_2, 1);
        $this->expectException(\PDOException::class);
        $this->model->link($this->userId, self::TEST_ADDR_2, 1);
    }

    public function testFindByUserReturnsAll(): void
    {
        $this->model->link($this->userId, self::TEST_ADDR_3, 1);
        $this->model->link($this->userId, self::TEST_ADDR_4, 8453);
        $rows = $this->model->findByUser($this->userId);
        $this->assertCount(2, $rows);
    }

    public function testUnlinkRemovesRowOwnedByUser(): void
    {
        $id = $this->model->link($this->userId, self::TEST_ADDR_5, 1);
        $this->assertTrue($this->model->unlink($this->userId, $id));
        $this->assertFalse($this->db->fetch("SELECT * FROM wallet_addresses WHERE id = ?", [$id]));
    }

    public function testUnlinkFailsForOtherUser(): void
    {
        $id = $this->model->link($this->userId, self::TEST_ADDR_6, 1);
        $this->assertFalse($this->model->unlink($this->userId + 99999, $id));
    }
}
