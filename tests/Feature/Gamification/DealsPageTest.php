<?php

declare(strict_types=1);

use App\Livewire\Profile\Deals;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

it('lists the buyer\'s pending deals and confirms one', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->create(['state' => 'sold', 'title' => 'Dell R720']);
    $tx = Transaction::factory()->create([
        'listing_id' => $listing->id, 'seller_user_id' => $seller->id,
        'buyer_user_id' => $buyer->id, 'status' => 'pending',
    ]);

    Livewire::actingAs($buyer)
        ->test(Deals::class)
        ->assertSee('Dell R720')
        ->call('confirm', $tx->id)
        ->assertHasNoErrors();

    expect($tx->fresh()->status)->toBe('completed');
});

it('does not let a user confirm a deal that is not theirs', function () {
    $tx = Transaction::factory()->create(['status' => 'pending']);
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(Deals::class)
        ->call('confirm', $tx->id)
        ->assertForbidden();
});

it('404s when the deals feature is off', function () {
    config()->set('cloudmarktplaats.features.deals', false);
    Livewire::actingAs(User::factory()->create())->test(Deals::class)->assertStatus(404);
});

it('shows a confirmed deal to both the buyer and the seller', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['title' => 'HP MicroServer']);
    $tx = app(DealService::class)->markSold($listing, $seller);
    $buyer = User::factory()->create(['email_verified_at' => now()]);
    app(DealService::class)->claim((string) $tx->claim_token, $buyer);

    Livewire::actingAs($buyer)->test(Deals::class)->assertSee('HP MicroServer')->assertSee('Gekocht');
    Livewire::actingAs($seller)->test(Deals::class)->assertSee('HP MicroServer')->assertSee('Verkocht');
});

it('explains itself when there is nothing yet', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Deals::class)
        ->assertSee('Hier komen de deals te staan die jij of je tegenpartij bevestigd heeft.');
});

it('does not let a buyer confirm a deal once the deals feature is turned off mid-session', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->create(['state' => 'sold', 'title' => 'Dell R720']);
    $tx = Transaction::factory()->create([
        'listing_id' => $listing->id, 'seller_user_id' => $seller->id,
        'buyer_user_id' => $buyer->id, 'status' => 'pending',
    ]);

    // The flag check in mount() only guards the initial request; a component
    // that is already mounted skips mount() on a subsequent call. Turning the
    // flag off *after* mounting proves confirm() carries its own guard too.
    $component = Livewire::actingAs($buyer)->test(Deals::class);
    config()->set('cloudmarktplaats.features.deals', false);

    $component->call('confirm', $tx->id)->assertForbidden();

    expect($tx->fresh()->status)->toBe('pending');
});
