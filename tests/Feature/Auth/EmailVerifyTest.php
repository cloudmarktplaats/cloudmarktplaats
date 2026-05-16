<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('sends verification email after registration', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    event(new Registered($user));
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('marks email verified when signed link visited', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
    $this->actingAs($user)->get($url)->assertRedirect();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
