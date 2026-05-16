<?php

declare(strict_types=1);

use App\Services\Auth\SiweMessageBuilder;

it('builds an EIP-4361 message with required fields', function (): void {
    $msg = (new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl'))
        ->build('0xAbC0000000000000000000000000000000000001', 'abcdef1234567890', '2026-05-16T10:00:00Z');

    expect($msg)->toContain('cloudmarktplaats.nl wants you to sign in with your Ethereum account:');
    expect($msg)->toContain('0xAbC0000000000000000000000000000000000001');
    expect($msg)->toContain('URI: https://cloudmarktplaats.nl');
    expect($msg)->toContain('Version: 1');
    expect($msg)->toContain('Chain ID: 1');
    expect($msg)->toContain('Nonce: abcdef1234567890');
    expect($msg)->toContain('Issued At: 2026-05-16T10:00:00Z');
});

it('parses a built message back to fields', function (): void {
    $b = new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl');
    $msg = $b->build('0xAbC0000000000000000000000000000000000001', 'nonce123', '2026-05-16T10:00:00Z');
    $parsed = $b->parse($msg);

    expect($parsed['address'])->toBe('0xAbC0000000000000000000000000000000000001');
    expect($parsed['uri'])->toBe('https://cloudmarktplaats.nl');
    expect($parsed['chain_id'])->toBe(1);
    expect($parsed['nonce'])->toBe('nonce123');
    expect($parsed['issued_at'])->toBe('2026-05-16T10:00:00Z');
});
