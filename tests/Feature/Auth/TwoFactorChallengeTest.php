<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    RateLimiter::clear('login:127.0.0.1:a@b.nl');

    $secret = (new Google2FA)->generateSecretKey();

    $this->user = User::factory()->create([
        'email' => 'a@b.nl',
        'password_hash' => bcrypt('p'),
    ]);
    $this->user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['recoveryAA1', 'recoveryAA2'],
        'two_factor_confirmed_at' => now(),
    ])->save();
    UserIdentity::factory()->password()->for($this->user)->create();

    $this->plainSecret = $secret;
});

it('redirects to challenge instead of completing login', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'a@b.nl')->set('password', 'p')
        ->call('submit')
        ->assertRedirect('/2fa/challenge');

    expect(auth()->id())->toBeNull();
    expect(session('pending_2fa_user_id'))->toBe($this->user->id);
});

it('completes login with valid TOTP', function (): void {
    session(['pending_2fa_user_id' => $this->user->id]);
    $code = (new Google2FA)->getCurrentOtp($this->plainSecret);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', $code)
        ->call('submit')
        ->assertRedirect('/');

    expect(auth()->id())->toBe($this->user->id);
});

it('completes login with recovery code and removes it', function (): void {
    session(['pending_2fa_user_id' => $this->user->id]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', 'recoveryAA1')
        ->call('submit')
        ->assertRedirect('/');

    expect(auth()->id())->toBe($this->user->id);
    expect($this->user->fresh()->two_factor_recovery_codes)->not->toContain('recoveryAA1');
    expect($this->user->fresh()->two_factor_recovery_codes)->toContain('recoveryAA2');
});

it('rejects an invalid code', function (): void {
    session(['pending_2fa_user_id' => $this->user->id]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('submit')
        ->assertHasErrors(['code']);

    expect(auth()->id())->toBeNull();
});
