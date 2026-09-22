<?php

declare(strict_types=1);

use App\Livewire\News\Reader;
use App\Models\FeedItem;
use App\Models\FeedSource;
use Livewire\Livewire;

it('shows items from all active sources to an anonymous visitor', function () {
    $active = FeedSource::factory()->create(['name' => 'Tweakers', 'is_active' => true]);
    $inactive = FeedSource::factory()->create(['name' => 'Dood', 'is_active' => false]);
    FeedItem::factory()->for($active, 'source')->create(['title' => 'Actief artikel']);
    FeedItem::factory()->for($inactive, 'source')->create(['title' => 'Inactief artikel']);

    $this->get('/nieuws')
        ->assertOk()
        ->assertSee('Actief artikel')
        ->assertDontSee('Inactief artikel');
});

it('orders items newest first', function () {
    $source = FeedSource::factory()->create();
    FeedItem::factory()->for($source, 'source')->create(['title' => 'Ouder', 'published_at' => now()->subDay()]);
    FeedItem::factory()->for($source, 'source')->create(['title' => 'Nieuwer', 'published_at' => now()]);

    Livewire::test(Reader::class)
        ->assertSeeInOrder(['Nieuwer', 'Ouder']);
});

it('is reachable and linked from nav and footer', function () {
    $this->get('/')->assertOk()->assertSee(route('nieuws'), false);
});

it('returns 404 when the feature flag is off', function () {
    config(['cloudmarktplaats.features.news_reader' => false]);

    $this->get('/nieuws')->assertNotFound();
});
