<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\Web3Controller;

class Web3ControllerTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->db->query("DELETE FROM auth_nonces");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'wallet_%'");

        $_ENV['APP_URL'] = 'https://cloudmarkplaats.test';
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM auth_nonces");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'wallet_%'");
    }

    public function testUsernameForAddressIsDeterministic(): void
    {
        $controller = new Web3Controller();
        $name1 = $controller->deriveWalletUsername('0xFEEDBEEFDEAD000000000000000000000000BABE');
        $name2 = $controller->deriveWalletUsername('0xFEEDBEEFDEAD000000000000000000000000BABE');
        $this->assertSame($name1, $name2);
        $this->assertStringStartsWith('wallet_', $name1);
    }

    public function testLinkOrCreateCreatesNewUserForNewWallet(): void
    {
        $controller = new Web3Controller();
        $userId = $controller->linkOrCreate('0xfeedbeef00000000000000000000000000000001', 1);
        $this->assertGreaterThan(0, $userId);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertStringStartsWith('wallet_', $user['username']);
        $this->assertNull($user['email']);
        $this->assertNull($user['password']);
    }

    public function testLinkOrCreateReusesExistingWallet(): void
    {
        $controller = new Web3Controller();
        $addr = '0xfeedbeef00000000000000000000000000000002';
        $id1 = $controller->linkOrCreate($addr, 1);
        $id2 = $controller->linkOrCreate($addr, 1);
        $this->assertSame($id1, $id2);
    }
}
