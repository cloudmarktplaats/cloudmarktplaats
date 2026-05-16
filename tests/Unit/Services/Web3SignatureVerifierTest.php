<?php

declare(strict_types=1);

use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3SignatureVerifier;

it('verifies a valid signature against signer address', function (): void {
    /** @var array{address: string, private_key_hex: string} $fix */
    $fix = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/siwe-keypair.json'), true);

    $msg = (new SiweMessageBuilder('cloudmarktplaats.nl', 'https://cloudmarktplaats.nl'))
        ->build($fix['address'], 'nonce42', '2026-05-16T10:00:00Z');
    $sig = signMessage($msg, $fix['private_key_hex']);

    expect((new Web3SignatureVerifier)->verify($fix['address'], $msg, $sig))->toBeTrue();
});

it('rejects signature from different key', function (): void {
    /** @var array{address: string, private_key_hex: string} $fix */
    $fix = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/siwe-keypair.json'), true);

    $msg = 'hello';
    $sig = signMessage($msg, '0x'.str_repeat('1', 64));

    expect((new Web3SignatureVerifier)->verify($fix['address'], $msg, $sig))->toBeFalse();
});
