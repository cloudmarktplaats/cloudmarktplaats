<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1:a@b.nl');
});

it('logs in with correct password', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('right-pass')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)
        ->set('email', 'a@b.nl')->set('password', 'right-pass')
        ->call('submit')
        ->assertRedirect('/');
    expect(auth()->id())->toBe($user->id);
});

it('rejects wrong password with generic error', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('right-pass')]);
    UserIdentity::factory()->password()->for($user)->create();

    Livewire::test(Login::class)
        ->set('email', 'a@b.nl')->set('password', 'wrong')
        ->call('submit')
        ->assertHasErrors(['email']);
    expect(auth()->id())->toBeNull();
});

it('throttles after 5 failed attempts', function () {
    $user = User::factory()->create(['email' => 'a@b.nl', 'password_hash' => bcrypt('p')]);
    UserIdentity::factory()->password()->for($user)->create();
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'x')->call('submit');
    }
    Livewire::test(Login::class)->set('email', 'a@b.nl')->set('password', 'x')->call('submit')
        ->assertHasErrors(['email']);
});
