<?php

declare(strict_types=1);

use App\Models\FeedItem;
use App\Models\FeedSource;
use Database\Seeders\FeedSourceSeeder;

it('seeds nine active NL tech sources with real feed urls', function () {
    $this->seed(FeedSourceSeeder::class);

    expect(FeedSource::query()->where('is_active', true)->count())->toBe(9)
        ->and(FeedSource::query()->where('slug', 'tweakers')->value('feed_url'))
        ->toBe('https://tweakers.net/feeds/mixed.xml');
});

it('is idempotent: seeding twice keeps nine sources', function () {
    $this->seed(FeedSourceSeeder::class);
    $this->seed(FeedSourceSeeder::class);

    expect(FeedSource::query()->count())->toBe(9);
});

it('relates items to their source', function () {
    $source = FeedSource::factory()->create();
    FeedItem::factory()->for($source, 'source')->count(3)->create();

    expect($source->items()->count())->toBe(3)
        ->and($source->items->first()->source->id)->toBe($source->id);
});
