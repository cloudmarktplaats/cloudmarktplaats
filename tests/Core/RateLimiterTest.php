<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\RateLimiter;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;
    private string $key;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter();
        $this->key = 'test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->limiter->reset($this->key);
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->limiter->attempt($this->key, 5, 60));
        }
    }

    public function testBlocksRequestsOverLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt($this->key, 3, 60);
        }
        $this->assertFalse($this->limiter->attempt($this->key, 3, 60));
    }

    public function testResetClearsCounter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt($this->key, 3, 60);
        }
        $this->limiter->reset($this->key);
        $this->assertTrue($this->limiter->attempt($this->key, 3, 60));
    }

    public function testRemainingReflectsAttempts(): void
    {
        $this->limiter->attempt($this->key, 5, 60);
        $this->limiter->attempt($this->key, 5, 60);
        $this->assertSame(3, $this->limiter->remaining($this->key, 5));
    }
}
