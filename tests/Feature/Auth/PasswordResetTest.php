<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('sends reset link to known email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'r@b.nl']);
    Livewire::test(ForgotPassword::class)
        ->set('email', 'r@b.nl')->call('submit')
        ->assertHasNoErrors();
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('prefills the email from the reset-link query string', function () {
    $user = User::factory()->create(['email' => 'q@b.nl']);
    $token = Password::createToken($user);

    // The real reset link is /reset-password/{token}?email=… — the email
    // arrives as a query param, not a route/mount argument. The readonly
    // field must be populated from it, or the form can't be submitted.
    $this->get("/reset-password/{$token}?email=q@b.nl")
        ->assertOk()
        ->assertSee('q@b.nl');
});

it('updates password and creates identity row for siwe-only user', function () {
    $user = User::factory()->create(['email' => 's@b.nl', 'password_hash' => null]);
    UserIdentity::factory()->siwe('0xaaaa')->for($user)->create();
    $token = Password::createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 's@b.nl')
        ->set('password', 'new-secret-1234')
        ->set('password_confirmation', 'new-secret-1234')
        ->call('submit')
        ->assertRedirect('/login');

    $user->refresh();
    expect(Hash::check('new-secret-1234', $user->password_hash))->toBeTrue();
    expect($user->identities()->where('provider', 'password')->exists())->toBeTrue();
});
