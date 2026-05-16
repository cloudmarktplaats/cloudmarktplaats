<?php

declare(strict_types=1);

use App\Livewire\Profile\Security;
use App\Models\User;
use App\Models\UserIdentity;
use Livewire\Livewire;

it('lists user identities', function (): void {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    UserIdentity::factory()->github('1')->for($u)->create();
    $this->actingAs($u);

    Livewire::test(Security::class)
        ->assertSee('password')
        ->assertSee('oauth_github');
});

it('disables unlink button when only one identity', function (): void {
    $u = User::factory()->create();
    $only = UserIdentity::factory()->password()->for($u)->create();
    $this->actingAs($u);

    Livewire::test(Security::class)->call('unlink', $only->id)
        ->assertHasErrors(['identity']);

    expect($u->identities()->count())->toBe(1);
});

it('unlinks a non-last identity', function (): void {
    $u = User::factory()->create();
    UserIdentity::factory()->password()->for($u)->create();
    $github = UserIdentity::factory()->github('42')->for($u)->create();
    $this->actingAs($u);

    Livewire::test(Security::class)->call('unlink', $github->id)
        ->assertHasNoErrors();

    expect($u->identities()->count())->toBe(1);
    expect($u->identities()->where('provider', 'oauth_github')->exists())->toBeFalse();
});
