<?php

namespace Tests\Services\Auth;

use PHPUnit\Framework\TestCase;
use App\Services\Auth\SiweMessageBuilder;

class SiweMessageBuilderTest extends TestCase
{
    public function testBuildContainsAllRequiredFields(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $msg = $builder->build(
            '0x1111111111111111111111111111111111111111',
            1,
            'abc123def456abc123def456abc123de',
            'https://cloudmarkplaats.test',
            'Log in bij Cloudmarkplaats',
            '2026-04-21T10:00:00Z'
        );

        $this->assertStringContainsString('cloudmarkplaats.test wants you to sign in', $msg);
        $this->assertStringContainsString('0x1111111111111111111111111111111111111111', $msg);
        $this->assertStringContainsString('Log in bij Cloudmarkplaats', $msg);
        $this->assertStringContainsString('URI: https://cloudmarkplaats.test', $msg);
        $this->assertStringContainsString('Version: 1', $msg);
        $this->assertStringContainsString('Chain ID: 1', $msg);
        $this->assertStringContainsString('Nonce: abc123def456abc123def456abc123de', $msg);
        $this->assertStringContainsString('Issued At: 2026-04-21T10:00:00Z', $msg);
    }

    public function testParseExtractsFields(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $msg = $builder->build(
            '0x1111111111111111111111111111111111111111',
            8453,
            'xyz789xyz789xyz789xyz789xyz789xy',
            'https://cloudmarkplaats.test',
            'Log in bij Cloudmarkplaats',
            gmdate('Y-m-d\TH:i:s\Z')
        );

        $parsed = $builder->parse($msg);
        $this->assertSame('0x1111111111111111111111111111111111111111', $parsed['address']);
        $this->assertSame(8453, $parsed['chain_id']);
        $this->assertSame('xyz789xyz789xyz789xyz789xyz789xy', $parsed['nonce']);
        $this->assertSame('cloudmarkplaats.test', $parsed['domain']);
    }

    public function testParseRejectsWrongDomain(): void
    {
        $builder = new SiweMessageBuilder('cloudmarkplaats.test');
        $attackerMessage = "attacker.com wants you to sign in with your Ethereum account:\n" .
            "0x1111111111111111111111111111111111111111\n\nLogin\n\n" .
            "URI: https://attacker.com\nVersion: 1\nChain ID: 1\n" .
            "Nonce: xyz789xyz789xyz789xyz789xyz789xy\nIssued At: " . gmdate('Y-m-d\TH:i:s\Z');

        $this->expectException(\InvalidArgumentException::class);
        $builder->parse($attackerMessage);
    }
}
