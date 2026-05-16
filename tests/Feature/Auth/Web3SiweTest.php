<?php

declare(strict_types=1);

use App\Models\AuthNonce;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;

beforeEach(function (): void {
    LegalDocument::factory()->tos()->create(['published_at' => now()]);
    LegalDocument::factory()->privacy()->create(['published_at' => now()]);
});

it('issues a nonce', function (): void {
    $r = $this->getJson('/auth/web3/nonce?address=0x14791697260E4c9A71f18484C9f997B308e59325');
    $r->assertOk()->assertJsonStructure(['nonce', 'message']);
    expect(AuthNonce::count())->toBe(1);
});

it('logs in known SIWE user after valid signature', function (): void {
    /** @var array{address: string, private_key_hex: string} $fix */
    $fix = json_decode((string) file_get_contents(base_path('tests/Fixtures/siwe-keypair.json')), true);

    $u = User::factory()->create();
    UserIdentity::factory()->siwe($fix['address'])->for($u)->create();

    $message = $this->getJson('/auth/web3/nonce?address='.$fix['address'])->json('message');
    $sig = signMessage((string) $message, $fix['private_key_hex']);

    $this->postJson('/auth/web3/verify', [
        'address' => $fix['address'],
        'message' => $message,
        'signature' => $sig,
    ])->assertOk()->assertJson(['ok' => true]);

    expect(auth()->id())->toBe($u->id);
});

it('returns onboarding_required for unknown wallet', function (): void {
    /** @var array{address: string, private_key_hex: string} $fix */
    $fix = json_decode((string) file_get_contents(base_path('tests/Fixtures/siwe-keypair.json')), true);

    $message = $this->getJson('/auth/web3/nonce?address='.$fix['address'])->json('message');
    $sig = signMessage((string) $message, $fix['private_key_hex']);

    $this->postJson('/auth/web3/verify', [
        'address' => $fix['address'],
        'message' => $message,
        'signature' => $sig,
    ])->assertOk()->assertJson([
        'ok' => true,
        'onboarding_required' => true,
        'address' => strtolower($fix['address']),
    ]);

    expect(auth()->check())->toBeFalse();
});

it('rejects reused nonce', function (): void {
    /** @var array{address: string, private_key_hex: string} $fix */
    $fix = json_decode((string) file_get_contents(base_path('tests/Fixtures/siwe-keypair.json')), true);

    $u = User::factory()->create();
    UserIdentity::factory()->siwe($fix['address'])->for($u)->create();

    $message = $this->getJson('/auth/web3/nonce?address='.$fix['address'])->json('message');
    $sig = signMessage((string) $message, $fix['private_key_hex']);

    $this->postJson('/auth/web3/verify', [
        'address' => $fix['address'],
        'message' => $message,
        'signature' => $sig,
    ])->assertOk();
    auth()->logout();

    $this->postJson('/auth/web3/verify', [
        'address' => $fix['address'],
        'message' => $message,
        'signature' => $sig,
    ])->assertStatus(422);
});
