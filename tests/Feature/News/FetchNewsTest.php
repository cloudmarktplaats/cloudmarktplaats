<?php

declare(strict_types=1);

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Http;

function rssBody(string $guid, string $title): string
{
    return <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item>
        <title>$title</title>
        <link>https://example.test/$guid</link>
        <guid>$guid</guid>
        <description>Body</description>
        <pubDate>Mon, 22 Sep 2026 08:00:00 +0200</pubDate>
      </item>
    </channel></rss>
    XML;
}

it('fetches active sources and stores their items', function () {
    $source = FeedSource::factory()->create(['feed_url' => 'https://feed.test/a.xml']);
    Http::fake(['feed.test/*' => Http::response(rssBody('a1', 'Artikel A'))]);

    $this->artisan('news:fetch')->assertSuccessful();

    expect(FeedItem::query()->where('feed_source_id', $source->id)->count())->toBe(1)
        ->and($source->fresh()->last_fetched_at)->not->toBeNull()
        ->and($source->fresh()->last_error)->toBeNull();
});

it('does not duplicate items on a second fetch', function () {
    $source = FeedSource::factory()->create(['feed_url' => 'https://feed.test/a.xml']);
    Http::fake(['feed.test/*' => Http::response(rssBody('a1', 'Artikel A'))]);

    $this->artisan('news:fetch')->assertSuccessful();
    $this->artisan('news:fetch')->assertSuccessful();

    expect(FeedItem::query()->where('feed_source_id', $source->id)->count())->toBe(1);
});

it('records last_error for a failing source but keeps others working', function () {
    $ok = FeedSource::factory()->create(['feed_url' => 'https://ok.test/a.xml']);
    $bad = FeedSource::factory()->create(['feed_url' => 'https://bad.test/a.xml']);
    Http::fake([
        'ok.test/*' => Http::response(rssBody('a1', 'Goed')),
        'bad.test/*' => Http::response('', 503),
    ]);

    $this->artisan('news:fetch')->assertSuccessful();

    expect(FeedItem::query()->where('feed_source_id', $ok->id)->count())->toBe(1)
        ->and($bad->fresh()->last_error)->not->toBeNull()
        ->and(FeedItem::query()->where('feed_source_id', $bad->id)->count())->toBe(0);
});

it('prunes items older than sixty days', function () {
    $source = FeedSource::factory()->create(['feed_url' => 'https://feed.test/a.xml']);
    FeedItem::factory()->for($source, 'source')->create([
        'published_at' => now()->subDays(90),
        'fetched_at' => now()->subDays(90),
    ]);
    Http::fake(['feed.test/*' => Http::response(rssBody('a1', 'Vers'))]);

    $this->artisan('news:fetch')->assertSuccessful();

    expect(FeedItem::query()->where('feed_source_id', $source->id)->count())->toBe(1)
        ->and(FeedItem::query()->where('guid', 'a1')->exists())->toBeTrue();
});

it('skips fetching when the feature flag is off', function () {
    config(['cloudmarktplaats.features.news_reader' => false]);
    FeedSource::factory()->create(['feed_url' => 'https://feed.test/a.xml']);
    Http::fake();

    $this->artisan('news:fetch')->assertSuccessful();

    Http::assertNothingSent();
});
