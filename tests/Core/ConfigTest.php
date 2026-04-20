<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
    }

    public function testLoadReadsEnvFile(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertNotEmpty(Config::get('APP_NAME'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertNull(Config::get('NONEXISTENT_KEY'));
        $this->assertEquals('fallback', Config::get('NONEXISTENT_KEY', 'fallback'));
    }

    public function testGetReturnsBoolForTrueValues(): void
    {
        Config::load(dirname(__DIR__, 2));
        $debug = Config::get('APP_DEBUG');
        $this->assertIsBool($debug);
    }

    public function testIsDebugReturnsBoolean(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->assertIsBool(Config::isDebug());
    }

    public function testDatabaseConfigReturnsArray(): void
    {
        Config::load(dirname(__DIR__, 2));
        $db = Config::database();
        $this->assertArrayHasKey('host', $db);
        $this->assertArrayHasKey('name', $db);
        $this->assertArrayHasKey('user', $db);
        $this->assertArrayHasKey('pass', $db);
    }
}
