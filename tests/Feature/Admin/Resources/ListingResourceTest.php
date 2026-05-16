<?php

declare(strict_types=1);

use App\Events\Listings\ListingPublished;
use App\Events\Listings\ListingRejected;
use App\Filament\Resources\ListingResource\Pages\ListListings;
use App\Models\AdminAction;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the listings list for admins', function () {
    Listing::factory()->count(2)->create();

    Livewire::test(ListListings::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Listing::all());
});

it('rejects a listing through ListingStateService and dispatches event', function () {
    Event::fake([ListingRejected::class]);
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    Livewire::test(ListListings::class)
        ->callTableAction('reject', $listing, data: ['reason' => 'Stolen item, no receipt'])
        ->assertHasNoTableActionErrors();

    $listing->refresh();
    expect($listing->state)->toBe('rejected')
        ->and($listing->moderation_notes)->toBe('Stolen item, no receipt');

    Event::assertDispatched(ListingRejected::class);

    expect(AdminAction::query()
        ->where('action', 'listing.reject')
        ->where('target_id', $listing->id)
        ->whereJsonContains('meta->reason', 'Stolen item, no receipt')
        ->exists()
    )->toBeTrue();
});

it('publishes a pending listing through the state service', function () {
    Event::fake([ListingPublished::class]);
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    Livewire::test(ListListings::class)
        ->callTableAction('publish', $listing)
        ->assertHasNoTableActionErrors();

    expect($listing->fresh()->state)->toBe('published');
    Event::assertDispatched(ListingPublished::class);
});
