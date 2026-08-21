<?php

declare(strict_types=1);

use App\Livewire\Listings\Detail;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

/** @return array{0: User, 1: Listing} */
function sellerWithListing(): array
{
    $seller = User::factory()->create();

    return [$seller, Listing::factory()->published()->for($seller)->create()];
}

it('marks the listing sold with one button and shows the claim link afterwards', function () {
    [$seller, $listing] = sellerWithListing();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertHasNoErrors()
        ->assertSee('Stuur de koper deze link');

    $tx = Transaction::query()->sole();

    expect($listing->fresh()->state)->toBe('sold')
        ->and($tx->buyer_user_id)->toBeNull()
        ->and($tx->claim_token)->not->toBeNull();
});

it('keeps showing the panel on a sold listing while a claim is open', function () {
    [$seller, $listing] = sellerWithListing();
    app(DealService::class)->markSold($listing, $seller);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertSee('Nog niet bevestigd');
});

it('hands out a fresh link when the seller asks for one', function () {
    [$seller, $listing] = sellerWithListing();
    $tx = app(DealService::class)->markSold($listing, $seller);
    $old = (string) $tx->claim_token;

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('newLink', $tx->id)
        ->assertHasNoErrors();

    expect($tx->fresh()->claim_token)->not->toBe($old);
});

it('does not let a non-owner mark it sold or refresh a link', function () {
    [$seller, $listing] = sellerWithListing();
    $tx = app(DealService::class)->markSold($listing, $seller);
    $stranger = User::factory()->create();

    // Mount op een *gepubliceerde* advertentie van dezelfde verkoper: een
    // vreemde die een 'sold' advertentie opvraagt krijgt al in mount() een 404
    // van de view-ability, en dan komt newLink nooit aan de beurt.
    $other = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $other->ulid, 'slug' => (string) $other->slug])
        ->call('markSold')
        ->assertForbidden();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $other->ulid, 'slug' => (string) $other->slug])
        ->call('newLink', $tx->id)
        ->assertForbidden();
});

it('does not let the owner mark it sold when the deals feature is off', function () {
    config(['cloudmarktplaats.features.deals' => false]);
    [$seller, $listing] = sellerWithListing();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertForbidden();

    expect($listing->fresh()->state)->toBe('published')
        ->and(Transaction::query()->count())->toBe(0);
});

it('does not let the owner refresh a claim link when the deals feature is off', function () {
    [$seller, $listing] = sellerWithListing();
    $tx = app(DealService::class)->markSold($listing, $seller);
    $oldToken = (string) $tx->claim_token;

    config(['cloudmarktplaats.features.deals' => false]);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('newLink', $tx->id)
        ->assertForbidden();

    expect($tx->fresh()->claim_token)->toBe($oldToken);
});
