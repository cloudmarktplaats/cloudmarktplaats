<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Services\Auth\OAuthProviderFactory;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Github;

class OAuthProviderFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        $_ENV['GOOGLE_CLIENT_ID'] = 'test-google-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'test-google-secret';
        $_ENV['GITHUB_CLIENT_ID'] = 'test-github-id';
        $_ENV['GITHUB_CLIENT_SECRET'] = 'test-github-secret';
        $_ENV['APP_URL'] = 'https://cloudmarkplaats.test';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'cloudmarkplaats1';
        $_ENV['DB_USER'] = 'cmp';
        $_ENV['DB_PASS'] = 'dev_local_only';

        Config::load(dirname(__DIR__, 3));
    }

    public function testCreateGoogleProvider(): void
    {
        $factory = new OAuthProviderFactory();
        $provider = $factory->make('google');
        $this->assertInstanceOf(Google::class, $provider);
    }

    public function testCreateGithubProvider(): void
    {
        $factory = new OAuthProviderFactory();
        $provider = $factory->make('github');
        $this->assertInstanceOf(Github::class, $provider);
    }

    public function testUnknownProviderThrows(): void
    {
        $factory = new OAuthProviderFactory();
        $this->expectException(\InvalidArgumentException::class);
        $factory->make('facebook');
    }

    public function testMissingConfigThrows(): void
    {
        unset($_ENV['GOOGLE_CLIENT_ID']);
        Config::reset();
        Config::load(dirname(__DIR__, 3));

        $factory = new OAuthProviderFactory();
        $this->expectException(\RuntimeException::class);
        $factory->make('google');
    }
}
