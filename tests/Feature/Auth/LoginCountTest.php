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
});

it('starts a fresh user at zero logins', function (): void {
    $user = User::factory()->create();

    expect($user->login_count)->toBe(0);
});

it('increments login_count on each password login', function (): void {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('p')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'p')->call('submit');
    expect($user->fresh()->login_count)->toBe(1);

    auth()->logout();
    RateLimiter::clear('login:127.0.0.1:a@b.nl');

    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'p')->call('submit');
    expect($user->fresh()->login_count)->toBe(2);
});

it('records last_login_at and last_login_ip alongside the increment', function (): void {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('p')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'p')->call('submit');

    $fresh = $user->fresh();
    expect($fresh->login_count)->toBe(1);
    expect($fresh->last_login_at)->not->toBeNull();
    expect($fresh->last_login_ip)->toBe('127.0.0.1');
});

it('counts a 2FA login exactly once, only after the challenge passes', function (): void {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('p')]);
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();
    UserIdentity::factory()->password()->for($user)->create();

    // Step 1: password submit only routes to the challenge — no login yet.
    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'p')->call('submit')
        ->assertRedirect('/2fa/challenge');
    expect($user->fresh()->login_count)->toBe(0);

    // Step 2: passing the second factor seats the session and counts the login.
    session(['pending_2fa_user_id' => $user->id]);
    Livewire::test(TwoFactorChallenge::class)
        ->set('code', (new Google2FA)->getCurrentOtp($secret))
        ->call('submit')
        ->assertRedirect('/');

    expect($user->fresh()->login_count)->toBe(1);
});

it('increments login_count when an existing user signs in via oauth', function (): void {
    $existing = User::factory()->create();
    UserIdentity::factory()->github('888')->for($existing)->create();

    fakeSocialiteUser('github', '888', 'irrelevant@example.nl');

    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    expect($existing->fresh()->login_count)->toBe(1);
});
