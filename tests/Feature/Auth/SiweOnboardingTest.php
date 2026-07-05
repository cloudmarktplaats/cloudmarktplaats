<?php

declare(strict_types=1);

use App\Livewire\Auth\SiweOnboarding;
use App\Models\LegalDocument;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    LegalDocument::factory()->tos()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
    LegalDocument::factory()->privacy()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
});

it('creates user + siwe identity from onboarding form', function (): void {
    $address = '0xdead000000000000000000000000000000000001';

    Livewire::test(SiweOnboarding::class, ['address' => $address])
        ->set('email', 'wallet@b.nl')
        ->set('username', 'walletuser')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertRedirect('/');

    $u = User::where('email', 'wallet@b.nl')->first();
    expect($u)->not->toBeNull();
    expect($u->password_hash)->toBeNull();
    expect(
        $u->identities()
            ->where('provider', 'siwe')
            ->where('provider_uid', $address)
            ->exists()
    )->toBeTrue();
    expect($u->legalAcceptances()->count())->toBe(2);
    expect($u->invite_credits)->toBe(3);
    expect($u->display_name)->toBe('walletuser');
});

it('rejects onboarding when ToS is not accepted', function (): void {
    Livewire::test(SiweOnboarding::class, ['address' => '0xdead000000000000000000000000000000000002'])
        ->set('email', 'no-tos@b.nl')
        ->set('username', 'notosuser')
        ->set('accept_tos', false)
        ->call('submit')
        ->assertHasErrors(['accept_tos']);

    expect(User::where('email', 'no-tos@b.nl')->exists())->toBeFalse();
});
