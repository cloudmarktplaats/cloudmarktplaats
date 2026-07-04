<?php

declare(strict_types=1);

use App\Livewire\Profile\Deals;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
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
