<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Livewire\Livewire;

it('restricts user management abilities to admins', function () {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $target = User::factory()->create();

    expect($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('create', User::class))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeTrue()
        ->and($moderator->can('update', $target))->toBeFalse()
        ->and($moderator->can('create', User::class))->toBeFalse()
        ->and($moderator->can('delete', $target))->toBeFalse()
        ->and($moderator->can('view', $target))->toBeFalse();
});

it('forbids a moderator from opening the user edit page by direct URL', function () {
    $moderator = User::factory()->moderator()->create();
    $target = User::factory()->create();

    Livewire::actingAs($moderator)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->assertForbidden();
});

it('still lets an admin open the user edit page', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getKey()])
        ->assertOk();
});
