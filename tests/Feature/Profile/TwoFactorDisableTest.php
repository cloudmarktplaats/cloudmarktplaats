<?php

declare(strict_types=1);

use App\Livewire\Profile\TwoFactorSetup;
use App\Models\User;
use App\Models\UserIdentity;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function enable2faOn(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['rcA', 'rcB', 'rcC', 'rcD', 'rcE', 'rcF', 'rcG', 'rcH'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

it('disables 2FA when correct TOTP and password are provided', function (): void {
    $u = User::factory()->create(['password_hash' => bcrypt('hunter2')]);
    UserIdentity::factory()->password()->for($u)->create();
    $secret = enable2faOn($u);
    $this->actingAs($u);

    $code = (new Google2FA)->getCurrentOtp($secret);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', $code)
        ->set('password', 'hunter2')
        ->call('disable')
        ->assertHasNoErrors();

    $u->refresh();
    expect($u->two_factor_secret)->toBeNull();
    expect($u->two_factor_recovery_codes)->toBeNull();
    expect($u->two_factor_confirmed_at)->toBeNull();
});

it('refuses to disable 2FA with wrong password', function (): void {
    $u = User::factory()->create(['password_hash' => bcrypt('hunter2')]);
    UserIdentity::factory()->password()->for($u)->create();
    $secret = enable2faOn($u);
    $this->actingAs($u);

    $code = (new Google2FA)->getCurrentOtp($secret);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', $code)
        ->set('password', 'wrong')
        ->call('disable')
        ->assertHasErrors(['password']);

    expect($u->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('refuses to disable 2FA with wrong TOTP', function (): void {
    $u = User::factory()->create(['password_hash' => bcrypt('hunter2')]);
    UserIdentity::factory()->password()->for($u)->create();
    enable2faOn($u);
    $this->actingAs($u);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', '000000')
        ->set('password', 'hunter2')
        ->call('disable')
        ->assertHasErrors(['code']);

    expect($u->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('disables 2FA for user without password identity using only TOTP', function (): void {
    // OAuth/SIWE-only user has no password identity → no password required.
    $u = User::factory()->create(['password_hash' => null]);
    UserIdentity::factory()->github('99')->for($u)->create();
    $secret = enable2faOn($u);
    $this->actingAs($u);

    $code = (new Google2FA)->getCurrentOtp($secret);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', $code)
        ->call('disable')
        ->assertHasNoErrors();

    expect($u->fresh()->two_factor_confirmed_at)->toBeNull();
});
