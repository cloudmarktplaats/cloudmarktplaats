<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Postgres compares strings case-sensitively (MySQL does not), so an address
 * stored as "B.vaneijk@outlook.com" never matches a login or reset request for
 * "b.vaneijk@outlook.com". Reported by a real user on 2026-07-15: he could not
 * log in, asked for a reset, and no mail ever arrived — because no user was
 * found, so no token was created and nothing was sent, while the UI reported
 * success either way.
 */
it('stores an email in lowercase regardless of how it was typed', function () {
    $user = User::factory()->create(['email' => 'B.VanEijk@Outlook.com']);

    expect($user->fresh()->email)->toBe('b.vaneijk@outlook.com');
});

it('trims surrounding whitespace off an email', function () {
    $user = User::factory()->create(['email' => '  spaced@example.com  ']);

    expect($user->fresh()->email)->toBe('spaced@example.com');
});

it('lets a user log in whatever the casing of their email', function () {
    User::factory()->create([
        'email' => 'bram@example.com',
        'password_hash' => Hash::make('een-lang-genoeg-wachtwoord'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'Bram@Example.COM')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->call('submit')
        ->assertHasNoErrors();

    expect(auth()->check())->toBeTrue();
});

it('sends a reset link when the email casing differs from the stored one', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'bram@example.com']);

    Livewire::test(ForgotPassword::class)
        ->set('email', 'B.RAM@Example.com')  // different case AND a typo-free variant
        ->call('submit');

    // Nothing should be sent for a genuinely unknown address...
    Notification::assertNothingSent();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'BRAM@example.com')
        ->call('submit');

    // ...but a case variant of a real address must reach the real user.
    Notification::assertSentTo($user, ResetPassword::class);
    expect(DB::table('password_reset_tokens')->where('email', 'bram@example.com')->count())->toBe(1);
});

it('refuses a registration whose email differs only in casing from an existing one', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    // Without normalisation Postgres would happily accept this as a second
    // account, leaving two users who both believe they own the address.
    Livewire::test(Register::class)
        ->set('email', 'TAKEN@example.com')
        ->set('username', 'someone')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors(['email']);

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});
