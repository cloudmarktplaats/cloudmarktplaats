<?php

declare(strict_types=1);

use App\Filament\Resources\AdminActionResource;
use App\Filament\Resources\AdminActionResource\Pages\ListAdminActions;
use App\Models\AdminAction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the audit log list for admins', function () {
    AdminAction::factory()->count(3)->create();

    Livewire::test(ListAdminActions::class)
        ->assertOk()
        ->assertCanSeeTableRecords(AdminAction::all());
});

it('is read-only: create/edit/delete are not allowed', function () {
    $entry = AdminAction::factory()->create();

    expect(AdminActionResource::canCreate())->toBeFalse()
        ->and(AdminActionResource::canEdit($entry))->toBeFalse()
        ->and(AdminActionResource::canDelete($entry))->toBeFalse()
        ->and(AdminActionResource::canDeleteAny())->toBeFalse();
});
