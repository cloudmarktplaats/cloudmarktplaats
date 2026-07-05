<?php

declare(strict_types=1);

use App\Livewire\Profile\Security;
use App\Models\User;
use Livewire\Livewire;

it('lets a user change their display name without touching the username', function () {
    $user = User::factory()->create(['username' => 'handle', 'display_name' => 'handle']);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('displayName', 'Nick from Amsterdam')
        ->call('saveDisplayName')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->display_name)->toBe('Nick from Amsterdam')
        ->and($user->username)->toBe('handle');
});

it('rejects an empty display name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('displayName', '')
        ->call('saveDisplayName')
        ->assertHasErrors(['displayName']);
});
