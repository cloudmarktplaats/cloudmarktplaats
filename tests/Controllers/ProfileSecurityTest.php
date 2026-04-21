<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Controllers\ProfileController;

class ProfileSecurityTest extends TestCase
{
    private Database $db;
    private int $userId;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'sec_%'");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_sec_%'");

        $this->userId = $this->db->insert('users', [
            'username' => 'test_sec_u1',
            'email' => 'test_sec_u1@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'sec_%'");
        $this->db->query("DELETE FROM wallet_addresses WHERE address LIKE '0xfeedbeef%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_sec_%'");
    }

    public function testCountAuthMethodsWithPasswordAndOauth(): void
    {
        $controller = new ProfileController();
        $this->db->insert('oauth_providers', [
            'user_id' => $this->userId, 'provider' => 'google',
            'provider_uid' => 'sec_uid1', 'email' => 'x@test.com',
        ]);
        $this->assertSame(2, $controller->countAuthMethods($this->userId));
    }

    public function testCountAuthMethodsWithWalletOnly(): void
    {
        $this->db->update('users', ['password' => null], 'id = ?', [$this->userId]);
        $this->db->insert('wallet_addresses', [
            'user_id' => $this->userId,
            'address' => '0xfeedbeefaaaa00000000000000000000000000aa',
            'chain_id' => 1,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $controller = new ProfileController();
        $this->assertSame(1, $controller->countAuthMethods($this->userId));
    }

    public function testCountAuthMethodsZeroWhenOnlyOrphan(): void
    {
        $this->db->update('users', ['password' => null], 'id = ?', [$this->userId]);
        $controller = new ProfileController();
        $this->assertSame(0, $controller->countAuthMethods($this->userId));
    }
}
