<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('returns valid xml with the right content type', function () {
    $res = $this->get('/sitemap.xml');

    $res->assertOk()
        ->assertHeader('Content-Type', 'application/xml');

    // Parse-baar als XML — een kapotte sitemap is erger dan geen.
    $xml = simplexml_load_string($res->getContent());
    expect($xml)->not->toBeFalse();
});

it('includes published listings and homelabs, excludes drafts and removed', function () {
    $published = Listing::factory()->create(['state' => 'published']);
    $draft = Listing::factory()->create(['state' => 'draft']);
    $lab = HomelabPost::factory()->create(['status' => 'published']);
    $removedLab = HomelabPost::factory()->create(['status' => 'removed']);

    $body = (string) $this->get('/sitemap.xml')->getContent();

    expect($body)->toContain("/listings/{$published->ulid}-{$published->slug}")
        ->and($body)->not->toContain($draft->ulid)
        ->and($body)->toContain("/homelabs/{$lab->ulid}-{$lab->slug}")
        ->and($body)->not->toContain($removedLab->ulid);
});

it('lists the key static pages', function () {
    $body = (string) $this->get('/sitemap.xml')->getContent();

    expect($body)->toContain('/waarden')
        ->and($body)->toContain('/faq')
        ->and($body)->toContain('/homelabs');
});
