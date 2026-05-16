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

it('writes a user.update audit row when an admin edits a user via the row Edit action', function () {
    $target = User::factory()->create([
        'role' => 'user',
        'display_name' => 'Original Name',
    ]);

    Livewire::test(ListUsers::class)
        ->callTableAction('edit', $target, data: [
            'email' => $target->email,
            'username' => $target->username,
            'display_name' => 'Renamed Person',
            'role' => 'moderator',
            'is_banned' => false,
        ])
        ->assertHasNoTableActionErrors();

    $target->refresh();
    expect($target->role)->toBe('moderator')
        ->and($target->display_name)->toBe('Renamed Person');

    $row = AdminAction::query()
        ->where('action', 'user.update')
        ->where('target_type', 'user')
        ->where('target_id', $target->id)
        ->firstOrFail();

    expect($row->user_id)->toBe($this->admin->id)
        ->and($row->meta)->toHaveKey('changes')
        ->and($row->meta['changes'])->toHaveKey('role')
        ->and($row->meta['changes']['role'])->toBe('moderator')
        ->and($row->meta['changes'])->toHaveKey('display_name')
        ->and($row->meta['changes'])->not->toHaveKey('updated_at');
});

it('writes a user.bulk_delete audit row when an admin bulk-deletes users', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', [$a, $b])
        ->assertHasNoTableBulkActionErrors();

    expect(User::query()->whereIn('id', [$a->id, $b->id])->count())->toBe(0)
        ->and(User::withTrashed()->whereIn('id', [$a->id, $b->id])->count())->toBe(2);

    $row = AdminAction::query()
        ->where('action', 'user.bulk_delete')
        ->where('target_type', 'user')
        ->firstOrFail();

    expect($row->user_id)->toBe($this->admin->id)
        ->and($row->meta)->toHaveKey('ids')
        ->and($row->meta['ids'])->toEqualCanonicalizing([$a->id, $b->id]);
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

    // Force-disabling 2FA is a sensitive support action; the AdminAction
    // audit row is what makes it accountable. If the AdminActionLogger
    // call ever gets dropped this test fails loudly.
    expect(AdminAction::query()
        ->where('action', 'user.force_disable_2fa')
        ->where('target_type', 'user')
        ->where('target_id', $target->id)
        ->exists()
    )->toBeTrue();
});
