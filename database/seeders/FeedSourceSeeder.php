<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeedSource;
use Illuminate\Database\Seeder;

/**
 * De 9 NL-tech-bronnen bij de start. Feed-urls geverifieerd op 22-09-2026:
 * opgehaald, HTTP 200, geldige RSS met items. Techzine (Cloudflare-403),
 * Hardware.info (410) en AG Connect (geen feed) zijn bewust weggelaten.
 */
class FeedSourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sources() as $sort => $source) {
            FeedSource::query()->updateOrCreate(
                ['slug' => $source['slug']],
                [...$source, 'sort' => $sort, 'is_active' => true],
            );
        }
    }

    /** @return list<array{slug: string, name: string, feed_url: string, homepage_url: string}> */
    private function sources(): array
    {
        return [
            ['slug' => 'tweakers', 'name' => 'Tweakers', 'feed_url' => 'https://tweakers.net/feeds/mixed.xml', 'homepage_url' => 'https://tweakers.net'],
            ['slug' => 'security-nl', 'name' => 'Security.NL', 'feed_url' => 'https://www.security.nl/rss/headlines.xml', 'homepage_url' => 'https://www.security.nl'],
            ['slug' => 'bits-of-freedom', 'name' => 'Bits of Freedom', 'feed_url' => 'https://www.bitsoffreedom.nl/feed/', 'homepage_url' => 'https://www.bitsoffreedom.nl'],
            ['slug' => 'computable', 'name' => 'Computable', 'feed_url' => 'https://www.computable.nl/rss/', 'homepage_url' => 'https://www.computable.nl'],
            ['slug' => 'emerce', 'name' => 'Emerce', 'feed_url' => 'https://www.emerce.nl/rss/', 'homepage_url' => 'https://www.emerce.nl'],
            ['slug' => 'bright', 'name' => 'Bright', 'feed_url' => 'https://www.bright.nl/rss', 'homepage_url' => 'https://www.bright.nl'],
            ['slug' => 'iculture', 'name' => 'iCulture', 'feed_url' => 'https://www.iculture.nl/feed/', 'homepage_url' => 'https://www.iculture.nl'],
            ['slug' => 'androidworld', 'name' => 'Androidworld', 'feed_url' => 'https://www.androidworld.nl/feed/', 'homepage_url' => 'https://www.androidworld.nl'],
            ['slug' => 'nu-tech', 'name' => 'NU.nl Tech', 'feed_url' => 'https://www.nu.nl/rss/Tech', 'homepage_url' => 'https://www.nu.nl/tech'],
        ];
    }
}
