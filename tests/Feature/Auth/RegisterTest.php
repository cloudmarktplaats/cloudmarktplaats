<?php

use App\Livewire\Auth\Register;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    LegalDocument::factory()->tos()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
    LegalDocument::factory()->privacy()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
});

it('creates a user with password identity and legal acceptance', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('email', 'new@example.nl')
        ->set('username', 'newuser')
        ->set('display_name', 'New User')
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertRedirect('/email/verify-notice');

    $user = User::where('email', 'new@example.nl')->first();
    expect($user)->not->toBeNull();
    expect($user->identities()->where('provider', 'password')->exists())->toBeTrue();
    expect($user->legalAcceptances()->count())->toBe(2);
});

it('rejects mismatched passwords', function () {
    Livewire::test(Register::class)
        ->set('email', 'a@b.nl')->set('username', 'a')->set('display_name', 'A')
        ->set('password', 'aaa')->set('password_confirmation', 'bbb')->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('rejects when ToS not accepted', function () {
    Livewire::test(Register::class)
        ->set('email', 'a@b.nl')->set('username', 'abc')->set('display_name', 'A')
        ->set('password', 'aaaaaaaaaa')->set('password_confirmation', 'aaaaaaaaaa')->set('accept_tos', false)
        ->call('submit')
        ->assertHasErrors(['accept_tos']);
});
