<?php

declare(strict_types=1);

use App\Livewire\Listings\Detail;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('lets the owner mark their listing sold and tag a buyer', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->set('buyerUsername', 'koper')
        ->call('markSold')
        ->assertHasNoErrors();

    expect($listing->fresh()->state)->toBe('sold')
        ->and(Transaction::query()->where('buyer_user_id', $buyer->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('does not let a non-owner mark it sold', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertForbidden();
});
