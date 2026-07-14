<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;

it('shows the share panel to the owner of a published listing', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Deel je advertentie')
        ->assertSee('linkedin.com/sharing/share-offsite', false);
});

it('hides the share panel from a visitor who is not the owner', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $this->actingAs(User::factory()->create())
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('Deel je advertentie');
});

it('hides the share panel on a listing that is not published yet', function () {
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('Deel je advertentie');
});
