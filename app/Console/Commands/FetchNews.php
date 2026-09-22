<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Services\News\FeedParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Haalt de actieve nieuwsbronnen op en cachet hun items. De bezoeker praat
 * nooit met de bron; dit commando is de enige die dat doet. Elke bron in een
 * eigen try/catch, zodat 1 dode feed de rest niet meesleept, en de fout landt
 * in `last_error` zodat de reader hem kan tonen. Idempotent: upsert op guid.
 */
class FetchNews extends Command
{
    protected $signature = 'news:fetch';

    protected $description = 'Haal de NL-tech feeds op en cache ze';

    private const PRUNE_DAYS = 60;

    public function handle(FeedParser $parser): int
    {
        if (! config('cloudmarktplaats.features.news_reader', true)) {
            $this->info('Nieuws-reader staat uit; niets opgehaald.');

            return self::SUCCESS;
        }

        foreach (FeedSource::query()->active()->get() as $source) {
            $this->fetchSource($source, $parser);
        }

        $this->prune();

        return self::SUCCESS;
    }

    private function fetchSource(FeedSource $source, FeedParser $parser): void
    {
        try {
            $response = Http::timeout(12)
                ->withUserAgent('CloudmarktplaatsNieuws/1.0 (+https://cloudmarktplaats.nl)')
                ->get($source->feed_url)
                ->throw();

            foreach ($parser->parse($response->body()) as $item) {
                $this->store($source, $item);
            }

            $source->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();
        } catch (Throwable $e) {
            $source->forceFill(['last_error' => $e->getMessage(), 'last_error_at' => now()])->save();
            $this->warn("{$source->name}: {$e->getMessage()}");
        }
    }

    /**
     * @param  array{guid: string, title: string, url: string, summary: ?string, published_at: ?Carbon}  $item
     */
    private function store(FeedSource $source, array $item): void
    {
        FeedItem::query()->updateOrCreate(
            ['feed_source_id' => $source->id, 'guid' => $item['guid']],
            [
                'title' => $item['title'],
                'url' => $item['url'],
                'summary' => $item['summary'],
                'published_at' => $item['published_at'],
                'fetched_at' => now(),
            ],
        );
    }

    private function prune(): void
    {
        FeedItem::query()
            ->where(DB::raw('COALESCE(published_at, fetched_at)'), '<', now()->subDays(self::PRUNE_DAYS))
            ->delete();
    }
}
