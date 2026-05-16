<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

it('redirects anonymous visitors to the Filament login page', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('forbids regular users from accessing the admin panel', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows moderators to access the admin panel', function () {
    $moderator = User::factory()->moderator()->create();

    $this->actingAs($moderator)
        ->get('/admin')
        ->assertOk();
});

it('allows admins to access the admin panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('returns false from canAccessPanel for non-admins', function () {
    $user = User::factory()->create(['role' => 'user']);
    $panel = Filament::getPanel('admin');

    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('returns true from canAccessPanel for moderators and admins', function () {
    $moderator = User::factory()->moderator()->create();
    $admin = User::factory()->admin()->create();
    $panel = Filament::getPanel('admin');

    expect($moderator->canAccessPanel($panel))->toBeTrue()
        ->and($admin->canAccessPanel($panel))->toBeTrue();
});
