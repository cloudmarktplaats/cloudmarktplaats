<?php

declare(strict_types=1);

use App\Filament\Resources\ListingResource\Pages\ListListings;
use App\Models\AdminAction;
use App\Models\Listing;
use App\Models\User;
use App\Services\Admin\AdminActionLogger;
use Livewire\Livewire;

it('writes an admin_actions row carrying actor, target, meta and hashed ip', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $row = AdminActionLogger::log(
        'listing.reject',
        'listing',
        42,
        ['reason' => 'duplicate'],
    );

    expect($row->user_id)->toBe($admin->id)
        ->and($row->action)->toBe('listing.reject')
        ->and($row->target_type)->toBe('listing')
        ->and($row->target_id)->toBe(42)
        ->and($row->meta)->toBe(['reason' => 'duplicate'])
        ->and($row->ip_hash)->toHaveLength(64)
        ->and($row->ip_hash)->toMatch('/^[0-9a-f]+$/');
});

it('stores meta as null when no extra context is supplied', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $row = AdminActionLogger::log('user.unban', 'user', 7);

    expect($row->meta)->toBeNull();
});

it('records an audit row when an admin rejects a listing through Filament', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    Livewire::test(ListListings::class)
        ->callTableAction('reject', $listing, data: ['reason' => 'not allowed'])
        ->assertHasNoTableActionErrors();

    $row = AdminAction::query()
        ->where('action', 'listing.reject')
        ->where('target_id', $listing->id)
        ->firstOrFail();

    expect($row->user_id)->toBe($admin->id)
        ->and($row->target_type)->toBe('listing')
        ->and($row->meta['reason'] ?? null)->toBe('not allowed');
});
