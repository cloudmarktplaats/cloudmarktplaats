<?php

declare(strict_types=1);

use App\Livewire\Profile\Invites;
use App\Models\User;
use Livewire\Livewire;

it('shows karma and lets a verified user generate a code', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 2]);

    Livewire::actingAs($user)
        ->test(Invites::class)
        ->call('generate')
        ->assertHasNoErrors();

    expect($user->refresh()->invitesSent()->count())->toBe(1)
        ->and($user->invite_credits)->toBe(1);
});

it('shows an error when out of credits', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 0]);

    Livewire::actingAs($user)->test(Invites::class)->call('generate')->assertHasErrors('generate');
});

it('404s when the invites feature is off', function () {
    config()->set('cloudmarktplaats.features.invites', false);
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)->test(Invites::class)->assertStatus(404);
});
