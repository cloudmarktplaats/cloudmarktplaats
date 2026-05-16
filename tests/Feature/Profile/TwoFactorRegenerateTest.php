<?php

declare(strict_types=1);

use App\Livewire\Profile\TwoFactorSetup;
use App\Models\User;
use App\Models\UserIdentity;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

it('regenerates recovery codes when TOTP is correct', function (): void {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    $secret = (new Google2FA)->generateSecretKey();
    $oldCodes = ['rc1', 'rc2', 'rc3', 'rc4', 'rc5', 'rc6', 'rc7', 'rc8'];
    $u->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $oldCodes,
        'two_factor_confirmed_at' => now(),
    ])->save();
    $this->actingAs($u);

    $code = (new Google2FA)->getCurrentOtp($secret);

    $component = Livewire::test(TwoFactorSetup::class)
        ->set('code', $code)
        ->call('regenerate')
        ->assertHasNoErrors();

    /** @var array<int,string> $newCodes */
    $newCodes = $component->get('recovery');
    expect($newCodes)->toHaveCount(8);
    expect(array_intersect($newCodes, $oldCodes))->toBe([]);
    expect($u->fresh()->two_factor_recovery_codes)->toEqual($newCodes);
});

it('refuses to regenerate with wrong TOTP', function (): void {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    $secret = (new Google2FA)->generateSecretKey();
    $oldCodes = ['rc1', 'rc2', 'rc3', 'rc4', 'rc5', 'rc6', 'rc7', 'rc8'];
    $u->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $oldCodes,
        'two_factor_confirmed_at' => now(),
    ])->save();
    $this->actingAs($u);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', '000000')
        ->call('regenerate')
        ->assertHasErrors(['code']);

    expect($u->fresh()->two_factor_recovery_codes)->toEqual($oldCodes);
});

it('refuses to regenerate when 2FA is not enabled', function (): void {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    $this->actingAs($u);

    Livewire::test(TwoFactorSetup::class)
        ->set('code', '123456')
        ->call('regenerate')
        ->assertHasErrors(['code']);
});
