<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;

class OAuthControllerTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();

        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'testctl_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauthctl_%' OR email LIKE 'test_oauthctl_%'");

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE FROM oauth_providers WHERE provider_uid LIKE 'testctl_%'");
        $this->db->query("DELETE FROM users WHERE username LIKE 'test_oauthctl_%' OR email LIKE 'test_oauthctl_%'");
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    public function testHandleProviderResponseCreatesNewUser(): void
    {
        $controller = new \App\Controllers\OAuthController();

        $userId = $controller->handleProviderResponse(
            'google',
            'testctl_newuid',
            'test_oauthctl_new@test.com',
            'Nieuwe Gebruiker'
        );

        $this->assertGreaterThan(0, $userId);

        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertSame('test_oauthctl_new@test.com', $user['email']);
        $this->assertNull($user['password']);
        $link = $this->db->fetch("SELECT * FROM oauth_providers WHERE user_id = ?", [$userId]);
        $this->assertSame('testctl_newuid', $link['provider_uid']);
    }

    public function testHandleProviderResponseLinksExistingEmail(): void
    {
        $existingId = $this->db->insert('users', [
            'username' => 'test_oauthctl_existing',
            'email' => 'test_oauthctl_existing@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);

        $controller = new \App\Controllers\OAuthController();
        $userId = $controller->handleProviderResponse(
            'google',
            'testctl_existuid',
            'test_oauthctl_existing@test.com',
            'Bestaande Gebruiker'
        );

        $this->assertSame($existingId, $userId);
    }

    public function testHandleProviderResponseLogsInLinkedUser(): void
    {
        $userId = $this->db->insert('users', [
            'username' => 'test_oauthctl_linked',
            'email' => 'test_oauthctl_linked@test.com',
            'password' => password_hash('p', PASSWORD_DEFAULT),
        ]);
        $this->db->insert('oauth_providers', [
            'user_id' => $userId,
            'provider' => 'google',
            'provider_uid' => 'testctl_linkeduid',
            'email' => 'test_oauthctl_linked@test.com',
        ]);

        $controller = new \App\Controllers\OAuthController();
        $result = $controller->handleProviderResponse(
            'google',
            'testctl_linkeduid',
            'test_oauthctl_linked@test.com',
            'Gelinkte Gebruiker'
        );

        $this->assertSame($userId, $result);
    }
}
