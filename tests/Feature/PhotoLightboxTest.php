<?php

declare(strict_types=1);

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Support\Facades\Blade;

it('renders a button thumbnail per photo with the card image', function () {
    $photos = [
        ['card' => 'https://cdn.test/a/card.webp', 'original' => 'https://cdn.test/a/original.jpg', 'alt' => 'Cisco 6509'],
        ['card' => 'https://cdn.test/b/card.webp', 'original' => 'https://cdn.test/b/original.jpg', 'alt' => 'Cisco 6509'],
    ];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    // Elke thumbnail-knop roept show($i, $event) aan; de overlay heeft daarnaast
    // altijd een sluit- en twee navigatieknoppen (close/prev/next), dus tellen op
    // '<button' alleen zou ook die drie meetellen. show(-aanroepen zijn uniek per
    // thumbnail.
    expect(substr_count($html, '@click="show('))->toBe(2)
        ->and($html)->toContain('https://cdn.test/a/card.webp')
        ->and($html)->toContain('https://cdn.test/b/card.webp')
        ->and($html)->toContain('photoLightbox(');
});

it('carries the original URLs in the Alpine payload for the lightbox', function () {
    $photos = [
        ['card' => 'https://cdn.test/a/card.webp', 'original' => 'https://cdn.test/a/original.jpg', 'alt' => 'Cisco 6509'],
    ];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    // @js() wrapt in JSON.parse('...') en dubbel-escaped daarbij de JSON-string
    // (quotes -> ", slashes -> \\\/), dus de kale URL staat niet letterlijk
    // in de HTML. Payload eruit halen en twee keer JSON-decoderen om te
    // verifiëren dat de original-URL er daadwerkelijk in zit.
    preg_match("/JSON\.parse\('(.*?)'\)/", $html, $matches);
    $payload = json_decode(json_decode('"'.$matches[1].'"'), true);

    expect($payload[0]['original'])->toBe('https://cdn.test/a/original.jpg');
});

it('renders nothing when there are no photos', function () {
    $html = trim(Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => []]));

    expect($html)->toBe('');
});

it('is keyboard-reachable and announces the dialog for accessibility', function () {
    $photos = [['card' => 'c', 'original' => 'o', 'alt' => 'Cisco 6509']];

    $html = Blade::render('<x-photo-lightbox :photos="$photos" :columns="3" />', ['photos' => $photos]);

    expect($html)->toContain('role="dialog"')
        ->and($html)->toContain('aria-modal="true"')
        ->and($html)->toContain('<button');
});

it('shows the lightbox with original URLs and the title as alt on a listing detail page', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Cisco 6509']);
    ListingPhoto::factory()->for($listing)->create(['path' => 'listings/x/1/card.webp', 'mime' => 'image/jpeg', 'position' => 1]);

    $html = (string) $this->get("/listings/{$listing->ulid}-{$listing->slug}")->assertOk()->getContent();

    expect($html)->toContain('photoLightbox(')
        ->and($html)->toContain('/1/original.jpg')       // original in de payload
        ->and($html)->toContain('Cisco 6509');           // alt = titel
});

it('uses an anonymous alt for homelab photos — never anything identifying', function () {
    $post = HomelabPost::factory()->create();
    HomelabPhoto::factory()->for($post, 'post')->create(['path' => 'homelabs/y/1/card.webp', 'mime' => 'image/jpeg', 'position' => 1]);

    $html = (string) $this->get('/homelabs/'.$post->ulid.'-'.$post->slug)->assertOk()->getContent();

    expect($html)->toContain('photoLightbox(')
        ->and($html)->toContain('Homelab-foto');
});
