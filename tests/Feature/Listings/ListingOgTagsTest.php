<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Listing;

it('renders listing-specific og tags on a published listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'published',
        'title' => 'Cisco 6509',
        'description' => 'Twee chassis, compleet met supervisors.',
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Cisco 6509 — Cloudmarktplaats', false)
        ->assertSee('Twee chassis, compleet met supervisors.', false);
});

it('falls back to category, condition and price when the description is empty', function () {
    $category = Category::factory()->create(['name' => 'Netwerk']);
    $listing = Listing::factory()->create([
        'state' => 'published',
        'description' => null,
        'condition' => 'used',
        'price_cents' => 45000,
        'category_id' => $category->id,
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Netwerk', false)
        ->assertSee('€ 450,00', false);
});

it('keeps the layout defaults on a non-published listing so it cannot leak', function () {
    $listing = Listing::factory()->create([
        'state' => 'pending_review',
        'title' => 'Geheime Cisco',
    ]);

    // The owner may preview it; the OG tags must still show the generic defaults.
    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Cloudmarktplaats', false)
        ->assertDontSee('<meta property="og:title" content="Geheime Cisco', false);
});
