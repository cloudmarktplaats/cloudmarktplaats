<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\ListingJsonLd;

/** Decodeer de JSON-LD van een advertentie naar een array. */
function listingJsonLd(Listing $listing): array
{
    return json_decode(
        app(ListingJsonLd::class)->toJson($listing),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

it('builds a Product with the listing title and schema context', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Cisco 6509']);

    $data = listingJsonLd($listing);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('Product')
        ->and($data['name'])->toBe('Cisco 6509');
});

it('formats price as a two-decimal string, EUR, in stock', function () {
    $listing = Listing::factory()->published()->create(['price_cents' => 12500]);

    $offer = listingJsonLd($listing)['offers'];

    expect($offer['@type'])->toBe('Offer')
        ->and($offer['price'])->toBe('125.00')
        ->and($offer['priceCurrency'])->toBe('EUR')
        ->and($offer['availability'])->toBe('https://schema.org/InStock');
});

it('points the offer url at the canonical listing route', function () {
    $listing = Listing::factory()->published()->create();

    expect(listingJsonLd($listing)['offers']['url'])
        ->toBe(route('listings.detail', ['ulid' => $listing->ulid, 'slug' => $listing->slug]));
});

it('maps each condition to the right schema.org itemCondition', function (string $condition, string $expected) {
    $listing = Listing::factory()->published()->create(['condition' => $condition]);

    expect(listingJsonLd($listing)['offers']['itemCondition'])->toBe($expected);
})->with([
    ['new', 'https://schema.org/NewCondition'],
    ['used', 'https://schema.org/UsedCondition'],
    ['defective', 'https://schema.org/DamagedCondition'],
    ['for_parts', 'https://schema.org/DamagedCondition'],
]);

it('lists every photo as an original-variant URL', function () {
    $listing = Listing::factory()->published()->create();
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/a/1/card.webp', 'position' => 1]);
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/a/2/card.webp', 'position' => 2]);

    $images = listingJsonLd($listing)['image'];

    expect($images)->toHaveCount(2)
        ->and($images[0])->toContain('/1/original.')
        ->and($images[1])->toContain('/2/original.');
});

it('omits image when the listing has no photos', function () {
    $listing = Listing::factory()->published()->create();

    expect(listingJsonLd($listing))->not->toHaveKey('image');
});

it('omits description when empty and includes it otherwise', function () {
    $without = Listing::factory()->published()->create(['description' => null]);
    $with = Listing::factory()->published()->create(['description' => 'Compleet met rails.']);

    expect(listingJsonLd($without))->not->toHaveKey('description')
        ->and(listingJsonLd($with)['description'])->toBe('Compleet met rails.');
});
