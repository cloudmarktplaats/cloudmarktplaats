<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Support\ShareLinkBuilder;

it('builds a listing url with utm params on the canonical route', function () {
    $listing = Listing::factory()->create(['slug' => 'cisco-6509']);

    $url = app(ShareLinkBuilder::class)
        ->listingUrl($listing, 'linkedin', 'social', 'seller_share');

    expect($url)
        ->toContain("/listings/{$listing->ulid}-cisco-6509")
        ->toContain('utm_source=linkedin')
        ->toContain('utm_medium=social')
        ->toContain('utm_campaign=seller_share');
});

it('wraps the listing url in the linkedin share-offsite endpoint, url-encoded', function () {
    $listing = Listing::factory()->create();

    $url = app(ShareLinkBuilder::class)->linkedIn($listing);

    expect($url)->toStartWith('https://www.linkedin.com/sharing/share-offsite/?url=')
        // The nested url must be encoded, otherwise LinkedIn truncates at the first &
        ->toContain(urlencode('utm_source=linkedin'))
        ->not->toContain('&utm_medium');
});

it('tags the email link as email/listing_published', function () {
    $listing = Listing::factory()->create();

    expect(app(ShareLinkBuilder::class)->emailUrl($listing))
        ->toContain('utm_source=email')
        ->toContain('utm_medium=email')
        ->toContain('utm_campaign=listing_published');
});

it('tags the copyable link as copy/seller_share', function () {
    $listing = Listing::factory()->create();

    expect(app(ShareLinkBuilder::class)->copyUrl($listing))
        ->toContain('utm_source=copy')
        ->toContain('utm_campaign=seller_share');
});

it('builds share text with the dutch price format and the tagged url', function () {
    $listing = Listing::factory()->create([
        'title' => '2 x Cisco 6509',
        'price_cents' => 45000,
    ]);

    $text = app(ShareLinkBuilder::class)->shareText($listing);

    expect($text)
        ->toContain('2 x Cisco 6509')
        ->toContain('€ 450,00')
        ->toContain('utm_source=copy');
});

it('links to maindeck without prefill (v1 — no confirmed share intent)', function () {
    expect(app(ShareLinkBuilder::class)->mainDeckUrl())->toBe('https://maindeck.eu/');
});
