<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;
use App\Models\AuthNonce;
use App\Services\Auth\Web3NonceGenerator;

class Web3NonceGeneratorTest extends TestCase
{
    private Web3NonceGenerator $gen;
    private AuthNonce $nonces;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 3));
        Database::resetInstance();
        $this->nonces = new AuthNonce();
        $this->gen = new Web3NonceGenerator($this->nonces);
        Database::getInstance()->query("DELETE FROM auth_nonces");
    }

    public function testIssueReturns32CharAlphanumeric(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertSame(32, strlen($nonce));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $nonce);
    }

    public function testIssuedNonceIsValid(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertTrue($this->gen->verifyAndConsume($nonce, '0xabc'));
    }

    public function testReplayRejected(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertTrue($this->gen->verifyAndConsume($nonce, '0xabc'));
        $this->assertFalse($this->gen->verifyAndConsume($nonce, '0xabc'));
    }

    public function testWrongAddressRejected(): void
    {
        $nonce = $this->gen->issue('0xabc');
        $this->assertFalse($this->gen->verifyAndConsume($nonce, '0xdef'));
    }
}
