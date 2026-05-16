<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AdminAction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the users list for admins', function () {
    User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords(User::all());
});

it('bans a user with a reason via the ban action', function () {
    $target = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('ban', $target, data: ['reason' => 'spammer'])
        ->assertHasNoTableActionErrors();

    $target->refresh();
    expect($target->is_banned)->toBeTrue()
        ->and($target->banned_reason)->toBe('spammer');

    expect(AdminAction::query()
        ->where('action', 'user.ban')
        ->where('target_id', $target->id)
        ->exists()
    )->toBeTrue();
});

it('force-disables 2FA and clears two_factor_* fields', function () {
    $target = User::factory()->create([
        'two_factor_secret' => 'secret-blob',
        'two_factor_recovery_codes' => ['code1', 'code2'],
        'two_factor_confirmed_at' => now(),
    ]);

    Livewire::test(ListUsers::class)
        ->callTableAction('force_disable_2fa', $target)
        ->assertHasNoTableActionErrors();

    $target->refresh();
    expect($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_recovery_codes)->toBeNull()
        ->and($target->two_factor_confirmed_at)->toBeNull();
});
