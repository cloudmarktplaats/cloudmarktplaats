<?php

declare(strict_types=1);

use App\Models\User;

it('wipes all three 2FA fields for an existing user', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill([
        'two_factor_secret' => 'PLAINSECRET',
        'two_factor_recovery_codes' => ['codeA', 'codeB'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('user:reset-2fa', ['email' => 'staff@example.com'])
        ->assertExitCode(0);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('matches the email case-insensitively', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->artisan('user:reset-2fa', ['email' => 'STAFF@EXAMPLE.COM'])
        ->assertExitCode(0);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('fails with exit code 1 for an unknown email and changes nothing', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->artisan('user:reset-2fa', ['email' => 'nobody@example.com'])
        ->assertExitCode(1);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});
