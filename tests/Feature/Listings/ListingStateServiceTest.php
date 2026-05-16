<?php

declare(strict_types=1);

use App\Events\Listings\ListingArchived;
use App\Events\Listings\ListingPublished;
use App\Events\Listings\ListingRejected;
use App\Events\Listings\ListingSold;
use App\Models\Listing;
use App\Services\Listings\InvalidStateTransition;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\Event;

it('allows draft → pending_review and persists state', function () {
    $listing = Listing::factory()->create(['state' => 'draft']);
    $svc = app(ListingStateService::class);

    $svc->transition($listing, 'pending_review');

    expect($listing->fresh()->state)->toBe('pending_review');
});

it('fires ListingPublished when transitioning pending_review → published', function () {
    Event::fake([ListingPublished::class]);

    $listing = Listing::factory()->create(['state' => 'pending_review']);
    app(ListingStateService::class)->transition($listing, 'published');

    expect($listing->fresh()->state)->toBe('published')
        ->and($listing->fresh()->published_at)->not->toBeNull();
    Event::assertDispatched(ListingPublished::class);
});

it('fires ListingSold on published → sold and sets sold_at', function () {
    Event::fake([ListingSold::class]);

    $listing = Listing::factory()->published()->create();
    app(ListingStateService::class)->transition($listing, 'sold');

    expect($listing->fresh()->state)->toBe('sold')
        ->and($listing->fresh()->sold_at)->not->toBeNull();
    Event::assertDispatched(ListingSold::class);
});

it('fires ListingRejected with note', function () {
    Event::fake([ListingRejected::class]);

    $listing = Listing::factory()->create(['state' => 'pending_review']);
    app(ListingStateService::class)->transition($listing, 'rejected', 'Niet conform regels');

    expect($listing->fresh()->state)->toBe('rejected')
        ->and($listing->fresh()->moderation_notes)->toBe('Niet conform regels');
    Event::assertDispatched(ListingRejected::class, fn (ListingRejected $e) => $e->note === 'Niet conform regels');
});

it('fires ListingArchived on published → archived', function () {
    Event::fake([ListingArchived::class]);

    $listing = Listing::factory()->published()->create();
    app(ListingStateService::class)->transition($listing, 'archived');

    expect($listing->fresh()->state)->toBe('archived');
    Event::assertDispatched(ListingArchived::class);
});

it('rejects invalid transitions with InvalidStateTransition', function () {
    $listing = Listing::factory()->create(['state' => 'draft']);

    expect(fn () => app(ListingStateService::class)->transition($listing, 'sold'))
        ->toThrow(InvalidStateTransition::class);
});

it('allows rejected → draft (resubmit flow)', function () {
    $listing = Listing::factory()->create(['state' => 'rejected']);
    app(ListingStateService::class)->transition($listing, 'draft');

    expect($listing->fresh()->state)->toBe('draft');
});

it('forbids transitioning out of archived (terminal)', function () {
    $listing = Listing::factory()->create(['state' => 'archived']);

    expect(fn () => app(ListingStateService::class)->transition($listing, 'draft'))
        ->toThrow(InvalidStateTransition::class);
});
