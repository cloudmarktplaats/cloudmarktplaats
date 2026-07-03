<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Services\Gamification\InviteService;
use Livewire\Livewire;

it('prefills the invite code from the query string', function () {
    $this->get('/register?invite=ABC1234567')
        ->assertOk()
        ->assertSee('ABC1234567');
});

it('links the new account to the inviter when a valid code is used', function () {
    $inviter = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 3]);
    $code = app(InviteService::class)->generate($inviter);

    Livewire::test(Register::class)
        ->set('email', 'new@b.nl')->set('username', 'newbie')->set('display_name', 'New')
        ->set('password', 'secret-1234')->set('password_confirmation', 'secret-1234')
        ->set('accept_tos', true)
        ->set('invite_code', $code->code)
        ->call('submit')
        ->assertHasNoErrors();

    $new = User::query()->where('email', 'new@b.nl')->first();
    expect($new->invited_by)->toBe($inviter->id)
        ->and($new->invite_credits)->toBe(3);
});

it('rejects registration with an invalid invite code', function () {
    Livewire::test(Register::class)
        ->set('email', 'x@b.nl')->set('username', 'xuser')->set('display_name', 'X')
        ->set('password', 'secret-1234')->set('password_confirmation', 'secret-1234')
        ->set('accept_tos', true)
        ->set('invite_code', 'BOGUS00000')
        ->call('submit')
        ->assertHasErrors('invite_code');

    expect(User::query()->where('email', 'x@b.nl')->exists())->toBeFalse();
});
