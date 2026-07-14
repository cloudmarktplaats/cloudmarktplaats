<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Policies\ListingPolicy;

beforeEach(function () {
    $this->policy = new ListingPolicy;
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->admin = User::factory()->admin()->create();
});

// ---- viewAny -------------------------------------------------------------

it('lets anyone (including guests) view the listing index', function () {
    expect($this->policy->viewAny(null))->toBeTrue()
        ->and($this->policy->viewAny($this->stranger))->toBeTrue();
});

// ---- view ----------------------------------------------------------------

it('lets anyone view a published listing', function () {
    $listing = Listing::factory()->for($this->owner)->published()->create();

    expect($this->policy->view(null, $listing))->toBeTrue()
        ->and($this->policy->view($this->stranger, $listing))->toBeTrue();
});

it('hides a non-published listing from the public but shows it to owner and staff', function () {
    $draft = Listing::factory()->for($this->owner)->create(['state' => 'draft']);

    expect($this->policy->view(null, $draft))->toBeFalse()
        ->and($this->policy->view($this->stranger, $draft))->toBeFalse()
        ->and($this->policy->view($this->owner, $draft))->toBeTrue()
        ->and($this->policy->view($this->moderator, $draft))->toBeTrue()
        ->and($this->policy->view($this->admin, $draft))->toBeTrue();
});

// ---- create --------------------------------------------------------------

it('lets any authenticated user create a listing but denies guests', function () {
    expect($this->policy->create($this->stranger))->toBeTrue()
        ->and($this->policy->create(null))->toBeFalse();
});

// ---- update --------------------------------------------------------------

it('lets the owner or staff update a listing, but not a stranger', function () {
    $listing = Listing::factory()->for($this->owner)->published()->create();

    expect($this->policy->update($this->owner, $listing))->toBeTrue()
        ->and($this->policy->update($this->moderator, $listing))->toBeTrue()
        ->and($this->policy->update($this->admin, $listing))->toBeTrue()
        ->and($this->policy->update($this->stranger, $listing))->toBeFalse();
});

// ---- delete --------------------------------------------------------------

it('lets only staff delete a listing — not the owner nor a stranger', function () {
    $listing = Listing::factory()->for($this->owner)->published()->create();

    expect($this->policy->delete($this->moderator, $listing))->toBeTrue()
        ->and($this->policy->delete($this->admin, $listing))->toBeTrue()
        ->and($this->policy->delete($this->owner, $listing))->toBeFalse()
        ->and($this->policy->delete($this->stranger, $listing))->toBeFalse();
});

// ---- markSold ------------------------------------------------------------

it('lets only the owner mark a listing sold — not staff nor a stranger', function () {
    $listing = Listing::factory()->for($this->owner)->published()->create();

    expect($this->policy->markSold($this->owner, $listing))->toBeTrue()
        ->and($this->policy->markSold($this->moderator, $listing))->toBeFalse()
        ->and($this->policy->markSold($this->admin, $listing))->toBeFalse()
        ->and($this->policy->markSold($this->stranger, $listing))->toBeFalse();
});

// ---- share -----------------------------------------------------------------

it('allows the owner to share their own published listing', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    expect($listing->user->can('share', $listing))->toBeTrue();
});

it('denies sharing a listing that is not published yet', function () {
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    expect($listing->user->can('share', $listing))->toBeFalse();
});

it('denies sharing someone else\'s listing, staff included', function () {
    $listing = Listing::factory()->create(['state' => 'published']);
    $stranger = User::factory()->create();
    $moderator = User::factory()->create(['role' => 'moderator']);

    // Deliberately no staff bypass: moderators moderate, they don't share
    // someone else's listing.
    expect($stranger->can('share', $listing))->toBeFalse()
        ->and($moderator->can('share', $listing))->toBeFalse();
});
