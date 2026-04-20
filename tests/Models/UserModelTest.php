<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\User;

class UserModelTest extends TestCase
{
    private User $user;
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->user = new User();

        $this->db->query("DELETE FROM users WHERE username LIKE 'test_%'");
    }

    public function testFindByIdReturnsUser(): void
    {
        $id = $this->db->insert('users', [
            'username' => 'test_findbyid',
            'email' => 'test_findbyid@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);

        $user = $this->user->findById($id);
        $this->assertNotNull($user);
        $this->assertEquals('test_findbyid', $user['username']);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testFindByUsernameReturnsUser(): void
    {
        $id = $this->db->insert('users', [
            'username' => 'test_findbyname',
            'email' => 'test_findbyname@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
        ]);

        $user = $this->user->findByUsername('test_findbyname');
        $this->assertNotNull($user);
        $this->assertEquals($id, $user['id']);

        $this->db->delete('users', 'id = ?', [$id]);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $user = $this->user->findById(999999);
        $this->assertFalse($user);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->user->create('test_create', 'test_create@test.com', 'securepass123');
        $this->assertGreaterThan(0, $id);

        $user = $this->user->findById($id);
        $this->assertEquals('test_create', $user['username']);
        $this->assertTrue(password_verify('securepass123', $user['password']));

        $this->db->delete('users', 'id = ?', [$id]);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_%'");
    }
}
