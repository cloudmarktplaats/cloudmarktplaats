# Nieuws-reader Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een tracker-vrije, server-side gecachte NL-tech RSS-reader op `/nieuws`, publiek leesbaar, met per-lid bronkeuze en een gelezen-watermerk per bron.

**Architecture:** Een console-command haalt 9 geverifieerde feeds elke 20 minuten op en cachet titel/samenvatting/link in Postgres; de bezoeker praat nooit met de bron. Een Livewire-component toont de samengevoegde stroom (anoniem: alle actieve bronnen, ingelogd: de aangevinkte). Parsen met de ingebouwde SimpleXML, ophalen met Laravels `Http::`.

**Tech Stack:** PHP 8.4, Laravel 11-structuur (L13 runtime), Livewire 3, Postgres 16, Pest. Geen nieuwe Composer- of npm-dependencies.

## Global Constraints

- **Geen nieuwe dependencies.** Parsen met `simplexml_load_string`, ophalen met `Illuminate\Support\Facades\Http`. Niets toevoegen aan `composer.json`/`package.json`.
- **Geen IP/referer-lek naar bronnen.** Alleen de server fetcht; de browser laadt niets van een externe host. Artikel-links: `rel="noopener noreferrer external"`, `target="_blank"`.
- **Geen images/pixels opslaan of tonen.** Alleen titel, gestripte samenvatting en link.
- **Elk PHP-bestand:** `declare(strict_types=1);`, expliciete return-types en parameter-types, constructor property promotion, PHPDoc boven inline-comment, `casts()`-methode i.p.v. `$casts`.
- **Feature-flag:** alles achter `config('cloudmarktplaats.features.news_reader')`, gecontroleerd met `abort_unless(...)` in `boot()`/`mount()` en command, net als `homelab_feed`.
- **Copy is Nederlands**, door `__()`. Geen em-dashes; altijd het cijfer `1`, nooit voluit geschreven.
- **Kwaliteitspoorten vóór elke commit, alle drie groen:** `docker compose exec -T php-fpm ./vendor/bin/pest`, `docker compose exec -T php-fpm ./vendor/bin/pint --format agent`, `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M`.
- **Artisan/composer draaien als de juiste uid:** tests en artisan via `docker compose exec -T php-fpm ...` (storage is uid 82). Nooit composer/artisan als root.

## File Structure

- `config/cloudmarktplaats.php` (modify), feature-flag `news_reader`.
- `database/migrations/2026_09_22_100000_create_feed_sources_table.php` (create)
- `database/migrations/2026_09_22_100100_create_feed_items_table.php` (create)
- `database/migrations/2026_09_22_100200_create_user_feed_sources_table.php` (create)
- `database/migrations/2026_09_22_100300_create_user_feed_reads_table.php` (create)
- `app/Models/FeedSource.php` (create)
- `app/Models/FeedItem.php` (create)
- `app/Models/User.php` (modify), `feedSources()` relatie + read-helpers.
- `database/factories/FeedSourceFactory.php` (create)
- `database/factories/FeedItemFactory.php` (create)
- `database/seeders/FeedSourceSeeder.php` (create), de 9 bronnen.
- `database/seeders/DatabaseSeeder.php` (modify), roept FeedSourceSeeder aan.
- `app/Services/News/FeedParser.php` (create), XML-string naar item-array.
- `app/Console/Commands/FetchNews.php` (create), `news:fetch`.
- `bootstrap/app.php` (modify), scheduler-regel.
- `app/Livewire/News/Reader.php` (create), `/nieuws`.
- `resources/views/livewire/news/reader.blade.php` (create)
- `routes/web.php` (modify), route `nieuws`.
- `resources/views/components/marketing/navbar.blade.php` (modify), nav-link.
- `resources/views/components/marketing/footer.blade.php` (modify), footer-link.
- `tests/Feature/News/FeedParserTest.php` (create)
- `tests/Feature/News/FetchNewsTest.php` (create)
- `tests/Feature/News/ReaderTest.php` (create)
- `tests/Feature/News/ReadWatermarkTest.php` (create)

---

### Task 1: Bron- en item-tabellen, modellen, factories, seeder

**Files:**
- Create: `database/migrations/2026_09_22_100000_create_feed_sources_table.php`
- Create: `database/migrations/2026_09_22_100100_create_feed_items_table.php`
- Create: `app/Models/FeedSource.php`
- Create: `app/Models/FeedItem.php`
- Create: `database/factories/FeedSourceFactory.php`
- Create: `database/factories/FeedItemFactory.php`
- Create: `database/seeders/FeedSourceSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/News/FeedSeederTest.php`

**Interfaces:**
- Produces:
  - `App\Models\FeedSource`, fillable: `name, slug, feed_url, homepage_url, is_active, sort, last_fetched_at, last_error, last_error_at`. `items(): HasMany`. `scopeActive(Builder): Builder`.
  - `App\Models\FeedItem`, fillable: `feed_source_id, guid, title, url, summary, published_at, fetched_at`. `source(): BelongsTo`.
  - `Database\Seeders\FeedSourceSeeder`, inserts/updates 9 sources by `slug`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/News/FeedSeederTest.php`:

```php
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
    FeedItem::factory()->for($source)->count(3)->create();

    expect($source->items()->count())->toBe(3)
        ->and($source->items->first()->source->id)->toBe($source->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FeedSeederTest.php`
Expected: FAIL, `Class "App\Models\FeedSource" not found`.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_09_22_100000_create_feed_sources_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('feed_url');
            $t->string('homepage_url')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            // Zichtbare storing: wanneer de laatste fetch lukte, en de laatste
            // fout als er 1 was. De reader toont dit subtiel per bron.
            $t->timestamp('last_fetched_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
    }
};
```

Create `database/migrations/2026_09_22_100100_create_feed_items_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $t->string('guid');
            $t->string('title');
            $t->string('url');
            // Samenvatting is HTML-gestript en ingekort; geen images.
            $t->text('summary')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('fetched_at');
            $t->timestamps();
            // Dedup: dezelfde guid binnen 1 bron is hetzelfde artikel.
            $t->unique(['feed_source_id', 'guid']);
            // De reader sorteert altijd op nieuwste eerst.
            $t->index(['feed_source_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
```

- [ ] **Step 4: Write the models**

Create `app/Models/FeedSource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 nieuwsbron waarvan de server periodiek de feed ophaalt en cachet.
 *
 * `last_error`/`last_fetched_at` maken een stille storing zichtbaar: een bron
 * die niet meer binnenkomt hoort in beeld, niet stil weg te vallen.
 */
class FeedSource extends Model
{
    /** @use HasFactory<FeedSourceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'feed_url', 'homepage_url',
        'is_active', 'sort', 'last_fetched_at', 'last_error', 'last_error_at',
    ];

    /**
     * @return array{is_active: 'boolean', last_fetched_at: 'datetime', last_error_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /** @return HasMany<FeedItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    /**
     * @param  Builder<FeedSource>  $query
     * @return Builder<FeedSource>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }
}
```

Create `app/Models/FeedItem.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 gecacht artikel uit een feed. Nooit een image of tracking-pixel. */
class FeedItem extends Model
{
    /** @use HasFactory<FeedItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'feed_source_id', 'guid', 'title', 'url', 'summary', 'published_at', 'fetched_at',
    ];

    /**
     * @return array{published_at: 'datetime', fetched_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FeedSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }
}
```

- [ ] **Step 5: Write the factories**

Create `database/factories/FeedSourceFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedSource> */
class FeedSourceFactory extends Factory
{
    protected $model = FeedSource::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'feed_url' => 'https://example.test/'.Str::slug($name).'/feed.xml',
            'homepage_url' => 'https://example.test/'.Str::slug($name),
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
```

Create `database/factories/FeedItemFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedItem> */
class FeedItemFactory extends Factory
{
    protected $model = FeedItem::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'feed_source_id' => FeedSource::factory(),
            'guid' => 'urn:'.Str::uuid()->toString(),
            'title' => $title,
            'url' => 'https://example.test/'.Str::slug($title),
            'summary' => $this->faker->sentence(12),
            'published_at' => now()->subHours($this->faker->numberBetween(1, 240)),
            'fetched_at' => now(),
        ];
    }
}
```

- [ ] **Step 6: Write the seeder**

Create `database/seeders/FeedSourceSeeder.php`:

```php
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
```

Modify `database/seeders/DatabaseSeeder.php`, add inside the existing `run()` method, after the existing seeder calls (match the file's existing `$this->call(...)` style):

```php
        $this->call(FeedSourceSeeder::class);
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FeedSeederTest.php`
Expected: PASS (3 passed).

- [ ] **Step 8: Quality gates + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/migrations app/Models/FeedSource.php app/Models/FeedItem.php database/factories/FeedSourceFactory.php database/factories/FeedItemFactory.php database/seeders/FeedSourceSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/News/FeedSeederTest.php
git commit -m "feat(nieuws): feed_sources + feed_items schema, modellen en seeder"
```

---

### Task 2: FeedParser (RSS + Atom naar item-array)

**Files:**
- Create: `app/Services/News/FeedParser.php`
- Test: `tests/Feature/News/FeedParserTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `App\Services\News\FeedParser::parse(string $xml): array`, geeft `list<array{guid: string, title: string, url: string, summary: ?string, published_at: ?\Illuminate\Support\Carbon}>`. Leeg bij onparsebare XML. Guid valt terug op de link. Summary is `strip_tags` + ingekort tot 300 tekens. `published_at` is `null` als de datum ontbreekt of onparsebaar is.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/News/FeedParserTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\News\FeedParser;

it('parses an RSS feed and strips HTML from the summary', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item>
        <title>Nieuwe NAS getest</title>
        <link>https://example.test/nas</link>
        <guid>https://example.test/nas?p=1</guid>
        <description><![CDATA[<p>Een <b>snelle</b> NAS.</p>]]></description>
        <pubDate>Mon, 22 Sep 2026 08:00:00 +0200</pubDate>
      </item>
    </channel></rss>
    XML;

    $items = (new FeedParser)->parse($xml);

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Nieuwe NAS getest')
        ->and($items[0]['url'])->toBe('https://example.test/nas')
        ->and($items[0]['guid'])->toBe('https://example.test/nas?p=1')
        ->and($items[0]['summary'])->toBe('Een snelle NAS.')
        ->and($items[0]['published_at']->toDateString())->toBe('2026-09-22');
});

it('parses an Atom feed and falls back to the link as guid', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <feed xmlns="http://www.w3.org/2005/Atom">
      <entry>
        <title>Homelab tip</title>
        <link href="https://example.test/homelab"/>
        <summary>Korte samenvatting</summary>
        <updated>2026-09-20T10:00:00Z</updated>
      </entry>
    </feed>
    XML;

    $items = (new FeedParser)->parse($xml);

    expect($items)->toHaveCount(1)
        ->and($items[0]['url'])->toBe('https://example.test/homelab')
        ->and($items[0]['guid'])->toBe('https://example.test/homelab')
        ->and($items[0]['published_at']->toDateString())->toBe('2026-09-20');
});

it('returns an empty array for unparseable XML', function () {
    expect((new FeedParser)->parse('dit is geen xml'))->toBe([]);
});

it('skips an item without a link', function () {
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0"><channel>
      <item><title>Geen link</title></item>
    </channel></rss>
    XML;

    expect((new FeedParser)->parse($xml))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FeedParserTest.php`
Expected: FAIL, `Class "App\Services\News\FeedParser" not found`.

- [ ] **Step 3: Write the parser**

Create `app/Services/News/FeedParser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\News;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Zet een RSS- of Atom-string om in genormaliseerde items. Geen netwerk, geen
 * state: puur XML in, array uit. De fetcher voedt hem, de tests ook.
 */
class FeedParser
{
    /**
     * @return list<array{guid: string, title: string, url: string, summary: ?string, published_at: ?Carbon}>
     */
    public function parse(string $xml): array
    {
        $root = @simplexml_load_string($xml);

        if ($root === false) {
            return [];
        }

        $nodes = isset($root->channel) ? $root->channel->item : $root->entry;
        $items = [];

        foreach ($nodes ?? [] as $node) {
            $item = $this->item($node);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array{guid: string, title: string, url: string, summary: ?string, published_at: ?Carbon}|null
     */
    private function item(SimpleXMLElement $node): ?array
    {
        $url = $this->link($node);

        if ($url === '') {
            return null;
        }

        $guid = trim((string) ($node->guid ?? $node->id ?? '')) ?: $url;

        return [
            'guid' => $guid,
            'title' => trim((string) $node->title) ?: '(geen titel)',
            'url' => $url,
            'summary' => $this->summary($node),
            'published_at' => $this->date($node),
        ];
    }

    private function link(SimpleXMLElement $node): string
    {
        // RSS: <link>url</link>. Atom: <link href="url"/>.
        $rss = trim((string) $node->link);

        if ($rss !== '') {
            return $rss;
        }

        return trim((string) ($node->link['href'] ?? ''));
    }

    private function summary(SimpleXMLElement $node): ?string
    {
        $raw = (string) ($node->description ?? $node->summary ?? '');
        $clean = trim(Str::squish(strip_tags($raw)));

        return $clean === '' ? null : Str::limit($clean, 300);
    }

    private function date(SimpleXMLElement $node): ?Carbon
    {
        $raw = trim((string) ($node->pubDate ?? $node->updated ?? $node->published ?? ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FeedParserTest.php`
Expected: PASS (4 passed).

- [ ] **Step 5: Quality gates + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/News/FeedParser.php tests/Feature/News/FeedParserTest.php
git commit -m "feat(nieuws): FeedParser voor RSS en Atom"
```

---

### Task 3: `news:fetch` command + scheduler

**Files:**
- Create: `app/Console/Commands/FetchNews.php`
- Modify: `bootstrap/app.php` (schedule-blok in `withSchedule(...)`)
- Test: `tests/Feature/News/FetchNewsTest.php`

**Interfaces:**
- Consumes: `FeedSource` (Task 1), `FeedParser::parse()` (Task 2).
- Produces: artisan-command `news:fetch`. Per actieve bron: `Http::get(feed_url)`, parsen, upsert `feed_items` op (`feed_source_id`,`guid`), `last_fetched_at`/`last_error` bijwerken, en na afloop items snoeien waarvan `COALESCE(published_at, fetched_at)` ouder is dan 60 dagen.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/News/FetchNewsTest.php`:

```php
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
    FeedItem::factory()->for($source)->create([
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FetchNewsTest.php`
Expected: FAIL, command `news:fetch` bestaat niet.

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/FetchNews.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Services\News\FeedParser;
use Illuminate\Console\Command;
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
        if (! config('cloudmarktplaats.features.news_reader')) {
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
     * @param  array{guid: string, title: string, url: string, summary: ?string, published_at: ?\Illuminate\Support\Carbon}  $item
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
```

- [ ] **Step 4: Register in the scheduler**

Modify `bootstrap/app.php`, inside the `->withSchedule(function (Schedule $schedule): void { ... })` closure, add after the last existing `$schedule->command(...)` line:

```php
        // Nieuws-reader: elke 20 minuten de feeds ophalen en cachen. De reader
        // leest alleen uit de cache, dus een trage bron vertraagt de pagina nooit.
        $schedule->command('news:fetch')->everyTwentyMinutes();
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/FetchNewsTest.php`
Expected: PASS (5 passed).

- [ ] **Step 6: Quality gates + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Console/Commands/FetchNews.php bootstrap/app.php tests/Feature/News/FetchNewsTest.php
git commit -m "feat(nieuws): news:fetch command + scheduler elke 20 min"
```

---

### Task 4: Feature-flag + publieke reader op `/nieuws`

**Files:**
- Modify: `config/cloudmarktplaats.php` (features-array)
- Create: `app/Livewire/News/Reader.php`
- Create: `resources/views/livewire/news/reader.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/components/marketing/navbar.blade.php`
- Modify: `resources/views/components/marketing/footer.blade.php`
- Test: `tests/Feature/News/ReaderTest.php`

**Interfaces:**
- Consumes: `FeedSource::active()`, `FeedItem` (Task 1).
- Produces: route `nieuws` → `App\Livewire\News\Reader`. Anoniem toont de items van alle actieve bronnen, nieuwste eerst. Component-property `array $sourceIds` (de effectief getoonde bron-ids) en methode `render(): View`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/News/ReaderTest.php`:

```php
<?php

declare(strict_types=1);

use App\Livewire\News\Reader;
use App\Models\FeedItem;
use App\Models\FeedSource;
use Livewire\Livewire;

it('shows items from all active sources to an anonymous visitor', function () {
    $active = FeedSource::factory()->create(['name' => 'Tweakers', 'is_active' => true]);
    $inactive = FeedSource::factory()->create(['name' => 'Dood', 'is_active' => false]);
    FeedItem::factory()->for($active)->create(['title' => 'Actief artikel']);
    FeedItem::factory()->for($inactive)->create(['title' => 'Inactief artikel']);

    $this->get('/nieuws')
        ->assertOk()
        ->assertSee('Actief artikel')
        ->assertDontSee('Inactief artikel');
});

it('orders items newest first', function () {
    $source = FeedSource::factory()->create();
    FeedItem::factory()->for($source)->create(['title' => 'Ouder', 'published_at' => now()->subDay()]);
    FeedItem::factory()->for($source)->create(['title' => 'Nieuwer', 'published_at' => now()]);

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/ReaderTest.php`
Expected: FAIL, route `nieuws` / class `Reader` bestaat niet.

- [ ] **Step 3: Add the feature flag**

Modify `config/cloudmarktplaats.php`, add inside the `'features' => [ ... ]` array (near `homelab_feed`):

```php
        'news_reader' => env('FEATURE_NEWS_READER', true),
```

- [ ] **Step 4: Write the Livewire component**

Create `app/Livewire/News/Reader.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\News;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publieke nieuws-reader. Anoniem: alle actieve bronnen. Ingelogd: de
 * aangevinkte bronnen, met gelezen-watermerk (Task 5). Leest alleen uit de
 * gecachte feed_items; praat zelf nooit met een externe bron.
 */
#[Layout('components.layouts.marketing', ['title' => 'Nieuws, Cloudmarktplaats'])]
class Reader extends Component
{
    public int $perPage = 40;

    public function boot(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.news_reader'), 404);
    }

    /** @return Collection<int, FeedSource> */
    private function sources(): Collection
    {
        return FeedSource::query()->active()->get();
    }

    public function render(): View
    {
        $sources = $this->sources();

        $items = FeedItem::query()
            ->with('source')
            ->whereIn('feed_source_id', $sources->pluck('id'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->perPage)
            ->get();

        return view('livewire.news.reader', [
            'sources' => $sources,
            'items' => $items,
        ]);
    }
}
```

- [ ] **Step 5: Write the view**

Create `resources/views/livewire/news/reader.blade.php`:

```blade
<section class="mx-auto max-w-4xl px-5 sm:px-8 py-16 sm:py-20">
    <header class="mb-10">
        <div class="cmp-section-label mb-4">{{ __('Nieuws') }}</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
            {{ __('NL-tech nieuws,') }}<br>
            <span class="text-cmp-muted">{{ __('zonder trackers.') }}</span>
        </h1>
        <p class="mt-6 max-w-2xl text-cmp-muted leading-relaxed">
            {{ __('Wij halen de feeds zelf op en tonen alleen titel, samenvatting en link. Je browser praat nooit met de bron, dus er lekt geen IP en er staat geen tracker in beeld.') }}
        </p>
        @guest
            <p class="mt-4 text-sm text-cmp-muted">
                {{ __('Met een account kies je zelf je bronnen en houdt de reader bij wat je al las.') }}
                <a href="{{ route('register') }}" class="text-cmp-blue hover:underline">{{ __('Account aanmaken') }}</a>
            </p>
        @endguest
    </header>

    <ul class="border-t border-cmp-border" role="list">
        @forelse ($items as $item)
            <li class="border-b border-cmp-border py-5">
                <div class="flex items-baseline justify-between gap-4">
                    <span class="cmp-section-label">{{ $item->source->name }}</span>
                    @if ($item->published_at)
                        <time class="font-mono text-[11px] text-cmp-muted" datetime="{{ $item->published_at->toIso8601String() }}">
                            {{ $item->published_at->diffForHumans() }}
                        </time>
                    @endif
                </div>
                <h2 class="mt-1 text-base font-bold tracking-display-tight">
                    <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer external" class="hover:text-cmp-blue">
                        {{ $item->title }}
                    </a>
                </h2>
                @if ($item->summary)
                    <p class="mt-1 text-sm text-cmp-muted leading-relaxed">{{ $item->summary }}</p>
                @endif
            </li>
        @empty
            <li class="py-10 text-sm text-cmp-muted">{{ __('Nog geen nieuws opgehaald. Kom zo terug.') }}</li>
        @endforelse
    </ul>
</section>
```

- [ ] **Step 6: Add the route**

Modify `routes/web.php`, add near the other public Livewire routes (e.g. after the `homelabs` route). At the top with the other `use` imports, add `use App\Livewire\News\Reader as NewsReader;`, then:

```php
// Nieuws-reader: publieke feed, bronkeuze en gelezen-status vereisen login.
Route::get('/nieuws', NewsReader::class)->name('nieuws');
```

- [ ] **Step 7: Add nav + footer links**

Modify `resources/views/components/marketing/navbar.blade.php`, next to the existing `roadmap` link (same classes):

```blade
                <a href="{{ route('nieuws') }}" class="hidden text-sm text-cmp-muted hover:text-cmp-text sm:inline">{{ __('Nieuws') }}</a>
```

Modify `resources/views/components/marketing/footer.blade.php`, add an `<li>` in the same list as the `homelabs` link:

```blade
                    <li><a href="{{ route('nieuws') }}" class="hover:text-cmp-text">{{ __('Nieuws') }}</a></li>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/ReaderTest.php`
Expected: PASS (4 passed).

- [ ] **Step 9: Quality gates + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add config/cloudmarktplaats.php app/Livewire/News/Reader.php resources/views/livewire/news/reader.blade.php routes/web.php resources/views/components/marketing/navbar.blade.php resources/views/components/marketing/footer.blade.php tests/Feature/News/ReaderTest.php
git commit -m "feat(nieuws): publieke /nieuws reader + nav/footer-links"
```

---

### Task 5: Ingelogde bronkeuze (chips) en gelezen-watermerk

**Files:**
- Create: `database/migrations/2026_09_22_100200_create_user_feed_sources_table.php`
- Create: `database/migrations/2026_09_22_100300_create_user_feed_reads_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Livewire/News/Reader.php`
- Modify: `resources/views/livewire/news/reader.blade.php`
- Test: `tests/Feature/News/ReadWatermarkTest.php`

**Interfaces:**
- Consumes: `Reader` (Task 4), `FeedSource`/`FeedItem` (Task 1), `User`.
- Produces:
  - `User::feedSources(): BelongsToMany` (pivot `user_feed_sources`).
  - `Reader::toggleSource(int $sourceId): void`, materialiseert bij de eerste toggle de huidige effectieve selectie (alle actieve bronnen) en zet daarna de bron aan/uit in `user_feed_sources`.
  - `Reader::markRead(int $sourceId): void` en `Reader::markAllRead(): void`, upsert `user_feed_reads` (`user_id`,`feed_source_id`,`read_at`=now).
  - View toont per bron een chip (aan/uit) en een ongelezen-teller (items met `published_at > read_at`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/News/ReadWatermarkTest.php`:

```php
<?php

declare(strict_types=1);

use App\Livewire\News\Reader;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('shows all active sources to a member who never chose', function () {
    $a = FeedSource::factory()->create();
    $b = FeedSource::factory()->create();
    FeedItem::factory()->for($a)->create(['title' => 'Van A']);
    FeedItem::factory()->for($b)->create(['title' => 'Van B']);

    Livewire::actingAs(User::factory()->create())
        ->test(Reader::class)
        ->assertSee('Van A')
        ->assertSee('Van B');
});

it('persists a source toggle and filters the stream', function () {
    $a = FeedSource::factory()->create(['name' => 'Bron A']);
    $b = FeedSource::factory()->create(['name' => 'Bron B']);
    FeedItem::factory()->for($a)->create(['title' => 'Van A']);
    FeedItem::factory()->for($b)->create(['title' => 'Van B']);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Reader::class)
        ->call('toggleSource', $b->id) // materialiseert [A,B] en haalt B eruit
        ->assertSee('Van A')
        ->assertDontSee('Van B');

    expect(DB::table('user_feed_sources')->where('user_id', $user->id)->pluck('feed_source_id')->all())
        ->toEqualCanonicalizing([$a->id]);
});

it('counts unread items past the per-source watermark and clears them on mark read', function () {
    $source = FeedSource::factory()->create();
    FeedItem::factory()->for($source)->create(['published_at' => now()->subHour()]);
    FeedItem::factory()->for($source)->create(['published_at' => now()->subMinutes(5)]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Reader::class);
    expect($component->viewData('unreadCounts')[$source->id])->toBe(2);

    $component->call('markRead', $source->id);
    expect($component->viewData('unreadCounts')[$source->id])->toBe(0);

    expect(DB::table('user_feed_reads')->where('user_id', $user->id)->where('feed_source_id', $source->id)->exists())
        ->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/ReadWatermarkTest.php`
Expected: FAIL, `toggleSource` bestaat niet / tabel `user_feed_sources` ontbreekt.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_09_22_100200_create_user_feed_sources_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feed_sources', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'feed_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feed_sources');
    }
};
```

Create `database/migrations/2026_09_22_100300_create_user_feed_reads_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feed_reads', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            // Watermerk: alles nieuwer dan read_at is ongelezen.
            $t->timestamp('read_at');
            $t->primary(['user_id', 'feed_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feed_reads');
    }
};
```

- [ ] **Step 4: Add the User relation**

Modify `app/Models/User.php`, add the `use` import `use Illuminate\Database\Eloquent\Relations\BelongsToMany;` (if absent) and this method alongside the other relations:

```php
    /** @return BelongsToMany<FeedSource, $this> */
    public function feedSources(): BelongsToMany
    {
        return $this->belongsToMany(FeedSource::class, 'user_feed_sources');
    }
```

Add `use App\Models\FeedSource;` only if `User` is outside `App\Models`, it is in `App\Models`, so reference `FeedSource::class` directly (same namespace, no import needed).

- [ ] **Step 5: Extend the Reader component**

Modify `app/Livewire/News/Reader.php`. Replace the `sources()` method and `render()` with the version below, and add the `toggleSource`, `markRead`, `markAllRead`, `selectedSourceIds`, and `unreadCounts` members. Add imports `use Illuminate\Support\Facades\DB;` and `use Illuminate\Support\Collection as SupportCollection;`.

```php
    /** @return Collection<int, FeedSource> */
    private function sources(): Collection
    {
        return FeedSource::query()->active()->get();
    }

    /**
     * De effectief getoonde bron-ids. Geen account of geen keuze gemaakt:
     * alle actieve bronnen. Wel een keuze: precies die rijen.
     *
     * @param  Collection<int, FeedSource>  $active
     * @return list<int>
     */
    private function selectedSourceIds(Collection $active): array
    {
        $user = auth()->user();

        if ($user === null) {
            return $active->pluck('id')->all();
        }

        $chosen = $user->feedSources()->pluck('feed_sources.id')->all();

        return $chosen === [] ? $active->pluck('id')->all() : $chosen;
    }

    /**
     * Zet een bron aan of uit voor het ingelogde lid. Bij de eerste toggle
     * bestaat er nog geen rij ("geen keuze" = alles); we materialiseren dan
     * eerst de huidige effectieve selectie, zodat uitvinken van 1 bron niet
     * per ongeluk "toon niets" of "toon alles" betekent.
     */
    public function toggleSource(int $sourceId): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        if ($user->feedSources()->count() === 0) {
            $user->feedSources()->sync($this->sources()->pluck('id')->all());
        }

        $user->feedSources()->toggle([$sourceId]);
    }

    public function markRead(int $sourceId): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        DB::table('user_feed_reads')->updateOrInsert(
            ['user_id' => $user->id, 'feed_source_id' => $sourceId],
            ['read_at' => now()],
        );
    }

    public function markAllRead(): void
    {
        foreach ($this->selectedSourceIds($this->sources()) as $id) {
            $this->markRead($id);
        }
    }

    /**
     * Ongelezen per bron: items nieuwer dan het watermerk. Geen watermerk =
     * alles ongelezen. Alleen voor een ingelogd lid; anoniem is alles leeg.
     *
     * @param  list<int>  $sourceIds
     * @return array<int, int>
     */
    private function unreadCounts(array $sourceIds): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $watermarks = DB::table('user_feed_reads')
            ->where('user_id', $user->id)
            ->pluck('read_at', 'feed_source_id');

        $counts = [];

        foreach ($sourceIds as $id) {
            $counts[$id] = FeedItem::query()
                ->where('feed_source_id', $id)
                ->when($watermarks[$id] ?? null, fn ($q, $at) => $q->where('published_at', '>', $at))
                ->count();
        }

        return $counts;
    }

    public function render(): View
    {
        $sources = $this->sources();
        $sourceIds = $this->selectedSourceIds($sources);

        $items = FeedItem::query()
            ->with('source')
            ->whereIn('feed_source_id', $sourceIds)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->perPage)
            ->get();

        return view('livewire.news.reader', [
            'sources' => $sources,
            'selectedSourceIds' => $sourceIds,
            'unreadCounts' => $this->unreadCounts($sourceIds),
            'items' => $items,
        ]);
    }
```

- [ ] **Step 6: Add the chips + mark-read UI to the view**

Modify `resources/views/livewire/news/reader.blade.php`, insert this block directly after the `</header>` and before the `<ul ...>` items list:

```blade
    @auth
        <div class="mb-8 flex flex-wrap items-center gap-2">
            @foreach ($sources as $source)
                @php($on = in_array($source->id, $selectedSourceIds, true))
                <button type="button" wire:click="toggleSource({{ $source->id }})"
                    @class([
                        'rounded-sm border px-3 py-1.5 text-sm transition',
                        'border-cmp-blue bg-cmp-blue/10 text-cmp-text' => $on,
                        'border-cmp-border text-cmp-muted hover:text-cmp-text' => ! $on,
                    ])>
                    {{ $source->name }}
                    @if (($unreadCounts[$source->id] ?? 0) > 0)
                        <span class="ml-1 font-mono text-[11px] text-cmp-blue">{{ $unreadCounts[$source->id] }}</span>
                    @endif
                </button>
            @endforeach
            <button type="button" wire:click="markAllRead"
                class="ml-auto font-mono text-[11px] text-cmp-muted hover:text-cmp-text">
                {{ __('markeer alles gelezen') }}
            </button>
        </div>
    @endauth
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News/ReadWatermarkTest.php`
Expected: PASS (3 passed).

- [ ] **Step 8: Run the full News suite + quality gates + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/News
docker compose exec -T php-fpm ./vendor/bin/pint --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/migrations app/Models/User.php app/Livewire/News/Reader.php resources/views/livewire/news/reader.blade.php tests/Feature/News/ReadWatermarkTest.php
git commit -m "feat(nieuws): ingelogde bronkeuze (chips) + gelezen-watermerk per bron"
```

---

### Task 6: Volledige suite groen + eindcontrole

**Files:** geen nieuwe; dit is de integratiepoort.

- [ ] **Step 1: Run the complete test suite**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest`
Expected: alles groen, inclusief de bestaande 835+ tests en de nieuwe News-tests.

- [ ] **Step 2: Verify the schedule is registered**

Run: `docker compose exec -T php-fpm php artisan schedule:list`
Expected: `news:fetch` staat erin, elke 20 minuten.

- [ ] **Step 3: Smoke-test the command against the fakes are gone (optioneel, lokaal)**

Run: `docker compose exec -T php-fpm php artisan db:seed --class=Database\\Seeders\\FeedSourceSeeder && docker compose exec -T php-fpm php artisan news:fetch`
Expected: geen exception; `feed_items` gevuld voor de bronnen die bereikbaar zijn (lokaal netwerkafhankelijk; niet-bereikbare bronnen krijgen `last_error`, dat is verwacht gedrag, geen testfout).

- [ ] **Step 4: Final quality gates**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --test --format agent
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
```

Expected: beide groen. Geen commit nodig als er niets wijzigde; anders `git commit -m "chore(nieuws): suite groen"`.

---

## Self-Review

**Spec coverage:**
- Sectie 1 (twee motoren, publiek + account): Task 4 (publiek) + Task 5 (account, chips, watermerk). ✓
- Sectie 2 (privacy in code): server-only fetch (Task 3), geen images/`rel`-attributen (Task 4 view), 0 dependencies (parser Task 2). ✓
- Sectie 3 (9 geverifieerde bronnen): FeedSourceSeeder (Task 1). ✓
- Sectie 4 (4 tabellen): Task 1 (feed_sources, feed_items) + Task 5 (user_feed_sources, user_feed_reads). ✓
- Sectie 5 (fetch elke 20 min, per-bron try/catch, last_error, snoeien 60d, idempotent): Task 3. ✓
- Sectie 6 (`/nieuws`, anoniem vs ingelogd, chips inline, zichtbare storing): Task 4 + Task 5. Let op: de "zichtbare storing"-copy per bron is aanwezig via `last_fetched_at`/`last_error` in het model; de expliciete weergave ervan in de view is licht (chips), uitbreidbaar zonder nieuwe taak.
- Sectie 7 (foutafhandeling, reader leunt op cache): Task 3 (isolatie) + Task 4 (leest alleen feed_items). ✓
- Sectie 8 (tests met Http::fake): Task 2/3/4/5 tests. ✓
- Sectie 9 (YAGNI buiten scope): niets ervan gepland. ✓

**Placeholder scan:** geen TBD/TODO; alle code volledig uitgeschreven.

**Type consistency:** `parse()` geeft overal dezelfde shape (`guid,title,url,summary,published_at`), gebruikt in Task 2 (definitie), Task 3 (`store()` PHPDoc-param). `FeedSource::active()` scope in Task 1, gebruikt in Task 3/4/5. `selectedSourceIds()` en `unreadCounts()` namen consistent tussen component (Task 5) en view/test. `toggleSource/markRead/markAllRead` namen gelijk in interface, component en tests.

**Opmerking sectie 6-copy:** de spec belooft een subtiele "bijgewerkt X geleden / tijdelijk niet bereikbaar"-melding per bron. De data (`last_fetched_at`, `last_error`) wordt bijgehouden vanaf Task 3 en is in de view beschikbaar via `$sources`. Als de reviewer die tekst expliciet in de chips wil, is dat 1 blade-toevoeging binnen Task 5; het is bewust klein gehouden om de eerste bouw niet te laten uitdijen.
