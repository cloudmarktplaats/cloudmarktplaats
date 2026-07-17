<?php

declare(strict_types=1);

use App\Models\Listing;

it('emits Product JSON-LD on a published listing', function () {
    $listing = Listing::factory()->published()->create([
        'title' => 'Cisco 6509',
        'price_cents' => 12500,
    ]);

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"price":"125.00"', false);
});

it('does not emit Product JSON-LD on a non-published listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'draft',
        'title' => 'Geheime Cisco',
    ]);

    // De eigenaar mag de draft previewen; de structured data mag er niet zijn.
    $this->actingAs($listing->user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('"@type":"Product"', false);
});
