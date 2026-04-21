<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Services\Auth\Web3SignatureVerifier;

class Web3SignatureVerifierTest extends TestCase
{
    private Web3SignatureVerifier $verifier;
    private array $fixture;

    protected function setUp(): void
    {
        $this->verifier = new Web3SignatureVerifier();
        $path = dirname(__DIR__, 2) . '/fixtures/siwe/valid-mainnet.json';
        $this->fixture = json_decode(file_get_contents($path), true);
    }

    public function testRecoverReturnsCorrectAddress(): void
    {
        $recovered = $this->verifier->recover($this->fixture['message'], $this->fixture['signature']);
        $this->assertSame(strtolower($this->fixture['expected_address']), strtolower($recovered));
    }

    public function testVerifyReturnsTrueForValid(): void
    {
        $this->assertTrue($this->verifier->verify(
            $this->fixture['message'],
            $this->fixture['signature'],
            $this->fixture['expected_address']
        ));
    }

    public function testVerifyReturnsFalseForTamperedMessage(): void
    {
        $tampered = str_replace('Log in bij Cloudmarkplaats', 'Transfer funds to 0xBAD', $this->fixture['message']);
        $this->assertFalse($this->verifier->verify(
            $tampered,
            $this->fixture['signature'],
            $this->fixture['expected_address']
        ));
    }

    public function testVerifyReturnsFalseForDifferentAddress(): void
    {
        $this->assertFalse($this->verifier->verify(
            $this->fixture['message'],
            $this->fixture['signature'],
            '0x0000000000000000000000000000000000000000'
        ));
    }

    public function testVerifyReturnsFalseForMalformedSignature(): void
    {
        $this->assertFalse($this->verifier->verify(
            $this->fixture['message'],
            '0xdeadbeef',
            $this->fixture['expected_address']
        ));
    }
}
