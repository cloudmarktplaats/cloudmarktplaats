<?php

declare(strict_types=1);

use App\Livewire\Profile\TwoFactorSetup;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('generates a secret and recovery codes; confirms with valid TOTP', function (): void {
    $u = User::factory()->create();
    $this->actingAs($u);

    $component = Livewire::test(TwoFactorSetup::class)->call('start');
    $u->refresh();
    expect($u->two_factor_secret)->not->toBeNull();
    expect($u->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA)->getCurrentOtp($u->two_factor_secret);
    $component->set('code', $code)->call('confirm');
    $u->refresh();
    expect($u->two_factor_confirmed_at)->not->toBeNull();
    expect($u->two_factor_recovery_codes)->toHaveCount(8);
});

it('rejects wrong totp code at confirmation', function (): void {
    $u = User::factory()->create();
    $this->actingAs($u);

    Livewire::test(TwoFactorSetup::class)->call('start')->set('code', '000000')->call('confirm')
        ->assertHasErrors(['code']);

    $u->refresh();
    expect($u->two_factor_confirmed_at)->toBeNull();
});

it('shows recovery codes after successful confirmation', function (): void {
    $u = User::factory()->create();
    $this->actingAs($u);

    $component = Livewire::test(TwoFactorSetup::class)->call('start');
    $code = (new Google2FA)->getCurrentOtp($u->fresh()->two_factor_secret);

    $component->set('code', $code)->call('confirm');

    /** @var array<int,string> $recovery */
    $recovery = $component->get('recovery');
    expect($recovery)->toHaveCount(8);
    expect($u->fresh()->two_factor_recovery_codes)->toEqual($recovery);
});
