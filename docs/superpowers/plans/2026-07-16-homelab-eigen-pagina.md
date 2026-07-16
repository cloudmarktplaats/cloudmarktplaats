# Elk homelab een eigen pagina — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Elk homelab krijgt een eigen, deelbare pagina met titel, verhaal, meerdere foto's en een feedback-vraag — zodat een homelab iets is om naar te linken.

**Architecture:** De foto's verhuizen van twee kolommen op `homelab_posts` naar een nieuwe `homelab_photos`-tabel, een exacte spiegel van `listing_photos` inclusief de `mime`-kolom die `original.{ext}` en dus een deelbare og:image bouwbaar maakt. De detailpagina spiegelt de advertentie-detailpagina (`Listings\Detail`): handmatige ulid-lookup met 301-canonicalisatie, OG-tags via `layoutData()`. Verwerking blijft synchroon binnen de transactie, met een nieuw maximum van vier foto's zodat de request onder de timeout blijft.

**Tech Stack:** Laravel 11, Livewire 3, Pest, Intervention Image, Docker Compose, Postgres.

**Spec:** `docs/superpowers/specs/2026-07-15-homelab-eigen-pagina-design.md`

## Global Constraints

- Alles draait in Docker; de host heeft geen PHP. Tests: `docker compose exec -T php-fpm ./vendor/bin/pest`.
- Kwaliteitspoorten groen: `docker compose exec -T php-fpm ./vendor/bin/pint --test` en `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G` (level 8; zonder die limit crasht hij).
- NL is de brontaal én de vertaalsleutel. Elke nieuwe zichtbare string krijgt een regel in `lang/en.json` met de Nederlandse zin als key. Verifieer EN via `artisan tinker`, niet curl (locale is sessie-gebonden).
- **Het anonimiteitscontract is onaantastbaar:** `user_id` wordt nooit publiek gerenderd. De pagina toont nergens gebruikersnaam of e-mail van de bouwer. Dit is een FAQ-belofte.
- **Geen downvotes.** Waarderen blijft upvote-only.
- **Maximaal vier foto's per homelab**, uit `config('cloudmarktplaats.photos.homelab_max_count')`. Verwerking blijft synchroon binnen de transactie — de atomaire garantie (nooit een post zonder foto) blijft. Advertenties houden hun eigen `max_count` van 10.
- Per-foto grens 8 MB uit `config('cloudmarktplaats.photos.max_bytes')` — gedeeld met advertenties.
- **Titel mag leeg.** De twee bestaande posts hebben geen titel en moeten ongewijzigd blijven werken.
- Bestaande badges/posts worden nooit geraakt of verwijderd.

---

## File Structure

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| migratie (nieuw) | 3 kolommen op `homelab_posts` + `homelab_photos`-tabel + backfill 2 posts | 1 |
| `config/cloudmarktplaats.php` | `homelab_max_count` | 1 |
| `app/Models/HomelabPhoto.php` (nieuw) | Spiegel van `ListingPhoto`: `urlFor()`, `extForMime()` | 2 |
| `app/Models/HomelabPost.php` | `photos()`-relatie, `photoUrl()` als doorgeefluik, `slug`-accessor, fillable | 2 |
| `tests/Feature/Homelab/HomelabPhotoUrlTest.php` | Contract verandert: `original` wordt bouwbaar | 2 |
| `app/Jobs/Homelab/StoreHomelabPhotoJob.php` | Per foto, schrijft `homelab_photos`-rij met `position` | 3 |
| `app/Livewire/Homelab/Feed.php` | Accepteert titel, feedback-vraag, meerdere foto's | 4 |
| `resources/views/livewire/homelab/feed.blade.php` | Formulier + upload-zichtbaarheid | 5 |
| `app/Livewire/Homelab/Detail.php` (nieuw) | De pagina: lookup, canonicalisatie, OG-tags | 6 |
| `resources/views/livewire/homelab/detail.blade.php` (nieuw) | Galerij, feedback-kader, waarderen, melden | 6 |
| `routes/web.php` | De detail-route | 6 |
| `lang/en.json` | Vertalingen | 5, 6 |

**Volgorde:** schema → model → job → formulier-backend → formulier-frontend → pagina → koppeling. Elke taak eindigt op groen.

---

### Task 1: Schema — kolommen, tabel, en de twee bestaande posts

**Achtergrond:** homelab-foto's zitten nu als `photo_disk`/`photo_path` direct op `homelab_posts` (één foto per post, geen mime/afmetingen). Ze verhuizen naar `homelab_photos`, een kopie van `listing_photos`. De twee bestaande posts op productie moeten hun foto meekrijgen.

**Files:**
- Create: `database/migrations/2026_07_16_100000_add_homelab_pages.php`
- Modify: `config/cloudmarktplaats.php:45-47`
- Test: `tests/Feature/Homelab/HomelabSchemaTest.php` (nieuw)

**Interfaces:**
- Produces: tabel `homelab_photos` (kolommen als `listing_photos`), plus `homelab_posts.title` (nullable string 120), `feedback_prompt` (nullable string 280), `comments_open` (boolean default true). Config-key `cloudmarktplaats.photos.homelab_max_count` (int, 4).

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Homelab/HomelabSchemaTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use Illuminate\Support\Facades\Schema;

it('adds the page columns to homelab_posts', function () {
    expect(Schema::hasColumns('homelab_posts', ['title', 'feedback_prompt', 'comments_open']))->toBeTrue();
});

it('creates a homelab_photos table mirroring listing_photos', function () {
    expect(Schema::hasColumns('homelab_photos', [
        'homelab_post_id', 'disk', 'path', 'width', 'height', 'mime', 'byte_size', 'position',
    ]))->toBeTrue();
});

it('leaves title nullable so titleless posts keep working', function () {
    $post = HomelabPost::factory()->create(['title' => null]);
    expect($post->fresh()->title)->toBeNull();
});

it('exposes a homelab photo count separate from listings', function () {
    expect(config('cloudmarktplaats.photos.homelab_max_count'))->toBe(4)
        ->and(config('cloudmarktplaats.photos.max_count'))->toBe(10);
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabSchemaTest.php`
Expected: FAIL — kolommen/tabel/config bestaan niet.

- [ ] **Step 3: Voeg de config-key toe**

In `config/cloudmarktplaats.php`, vervang regel 45-46:

```php
        'max_bytes' => 8 * 1024 * 1024,
        'max_count' => 10,
```

door:

```php
        'max_bytes' => 8 * 1024 * 1024,
        'max_count' => 10,
        // Homelabs verwerken hun foto's synchroon binnen de transactie (de
        // post bestaat pas als de foto's er zijn). Tien decodes in één request
        // tikt tegen de gateway-timeout; vier blijft ruim daaronder en een rack
        // heeft niet meer nodig. Advertenties gaan via de queue en houden 10.
        'homelab_max_count' => 4,
```

- [ ] **Step 4: Schrijf de migratie**

Maak `database/migrations/2026_07_16_100000_add_homelab_pages.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Elk homelab een eigen pagina: titel, feedback-vraag, en foto's in een eigen
 * tabel (spiegel van listing_photos) in plaats van twee kolommen op de post.
 *
 * De mime-kolom is de kern: zonder de bron-mime is original.{ext} — en dus een
 * deelbare og:image — niet te bouwen. Dat is precies wat HomelabPost::photoUrl()
 * nu met een exception afdwingt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homelab_posts', function (Blueprint $t): void {
            $t->string('title', 120)->nullable()->after('ulid');
            $t->string('feedback_prompt', 280)->nullable()->after('body');
            $t->boolean('comments_open')->default(true)->after('feedback_prompt');
        });

        Schema::create('homelab_photos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
            $t->string('disk', 16)->default('local');
            $t->string('path');
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->string('mime', 64);
            $t->unsignedInteger('byte_size');
            $t->unsignedTinyInteger('position');
            $t->timestamps();
            $t->unique(['homelab_post_id', 'position']);
        });

        // Bestaande posts hun foto meegeven op position 0. De bron-mime is niet
        // te achterhalen (photo_path wijst naar de card-webp), dus zetten we de
        // mime van het bestand waar path werkelijk naar wijst: image/webp. Niet
        // gokken naar jpg/png — dat bouwt exact de 404 die deze feature oplost.
        // Gevolg: og:image valt voor deze twee terug op og-default.png, precies
        // zoals de webp-regel voorschrijft.
        $posts = DB::table('homelab_posts')
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', 'pending')
            ->get(['id', 'photo_disk', 'photo_path']);

        foreach ($posts as $post) {
            [$width, $height] = $this->dimensionsFor((string) $post->photo_disk, (string) $post->photo_path);

            DB::table('homelab_photos')->insert([
                'homelab_post_id' => $post->id,
                'disk' => (string) $post->photo_disk,
                'path' => (string) $post->photo_path,
                'width' => $width,
                'height' => $height,
                'mime' => 'image/webp',
                'byte_size' => $this->byteSizeFor((string) $post->photo_disk, (string) $post->photo_path),
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array{0:int,1:int} */
    private function dimensionsFor(string $disk, string $path): array
    {
        try {
            $bytes = Storage::disk($disk)->get($path);
            $info = $bytes !== null ? getimagesizefromstring($bytes) : false;
            if ($info !== false) {
                return [(int) $info[0], (int) $info[1]];
            }
        } catch (\Throwable) {
            // Bestand weg of onleesbaar — val terug op de card-afmeting (600x600).
        }

        return [600, 600];
    }

    private function byteSizeFor(string $disk, string $path): int
    {
        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_photos');
        Schema::table('homelab_posts', function (Blueprint $t): void {
            $t->dropColumn(['title', 'feedback_prompt', 'comments_open']);
        });
    }
};
```

- [ ] **Step 5: Draai de migratie in de testdb en draai de test**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabSchemaTest.php`
Expected: alle vier PASS. (Pest draait `RefreshDatabase`, dus de migratie draait automatisch mee.)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_16_100000_add_homelab_pages.php config/cloudmarktplaats.php tests/Feature/Homelab/HomelabSchemaTest.php
git commit -m "feat(homelab): schema voor eigen pagina — kolommen, foto-tabel, config

homelab_photos spiegelt listing_photos inclusief de mime-kolom die
original.{ext} bouwbaar maakt. title/feedback_prompt/comments_open op de
post. De twee bestaande posts krijgen een foto-rij op position 0 met mime
image/webp (de bron is niet te achterhalen; gokken bouwt een 404).
homelab_max_count=4: synchrone verwerking blijft onder de timeout."
```

---

### Task 2: Modellen — `HomelabPhoto`, en `HomelabPost` als eigenaar

**Achtergrond:** `HomelabPost::photoUrl()` gooit vandaag bewust een exception voor `original` omdat de bron-mime nergens staat. Met `homelab_photos.mime` kan dat wél. `photoUrl()` wordt een doorgeefluik naar de eerste foto, zodat feed, recent-blok en Filament ongewijzigd blijven. Het testbestand dat het oude contract vastlegt verandert mee.

**Files:**
- Create: `app/Models/HomelabPhoto.php`
- Modify: `app/Models/HomelabPost.php`
- Modify: `database/factories/HomelabPostFactory.php`
- Create: `database/factories/HomelabPhotoFactory.php`
- Modify: `tests/Feature/Homelab/HomelabPhotoUrlTest.php`

**Interfaces:**
- Consumes: tabel `homelab_photos` (Taak 1).
- Produces: `HomelabPhoto::urlFor(string $variant = 'card'): string`, `HomelabPhoto::extForMime(?string): string`. `HomelabPost::photos(): HasMany<HomelabPhoto>`, `HomelabPost::photoUrl(string $variant = 'card'): string` (doorgeefluik), `HomelabPost::getSlugAttribute(): string`, `HomelabPost::getOgImageUrl(): ?string`.

- [ ] **Step 1: Schrijf de falende test — herschrijf `HomelabPhotoUrlTest`**

Vervang de **volledige** inhoud van `tests/Feature/Homelab/HomelabPhotoUrlTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;

/**
 * Foto-URLs voor homelabs lopen nu via HomelabPhoto, een spiegel van
 * ListingPhoto. Met de mime-kolom is `original` wél bouwbaar — dat was de hele
 * reden voor deze feature. Het oude contract (photoUrl('original') gooit) is
 * daarmee vervallen; wat blijft is: card/thumb zijn webp, original volgt de
 * bron-mime, en een verzonnen variant is onbouwbaar.
 */
beforeEach(function () {
    Storage::fake('public');
});

it('builds a webp url for card and thumb', function () {
    $photo = HomelabPhoto::factory()->create([
        'disk' => 'local',
        'path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/1/card.webp',
        'mime' => 'image/jpeg',
    ]);

    expect($photo->urlFor('card'))->toContain('/1/card.webp')
        ->and($photo->urlFor('thumb'))->toContain('/1/thumb.webp');
});

it('builds an original url from the stored source mime', function () {
    $photo = HomelabPhoto::factory()->create([
        'disk' => 'local',
        'path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/1/card.webp',
        'mime' => 'image/jpeg',
    ]);

    // De card is webp, maar de original houdt zijn eigen extensie — dat is
    // precies waarvoor de mime-kolom bestaat.
    expect($photo->urlFor('original'))->toContain('/1/original.jpg');
});

it('post photoUrl delegates to the first photo', function () {
    $post = HomelabPost::factory()->create();
    HomelabPhoto::factory()->for($post)->create([
        'path' => 'homelabs/'.$post->ulid.'/1/card.webp',
        'position' => 0,
        'mime' => 'image/png',
    ]);

    expect($post->photoUrl('card'))->toContain('/1/card.webp')
        ->and($post->photoUrl('original'))->toContain('/1/original.png');
});

it('post photoUrl throws when there is no photo', function () {
    $post = HomelabPost::factory()->create();

    // Kan niet via het formulier, wel via een half mislukte migratie. Een dode
    // URL is erger dan een duidelijke fout.
    expect(fn () => $post->photoUrl('card'))
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabPhotoUrlTest.php`
Expected: FAIL — `HomelabPhoto` en de factory bestaan niet.

- [ ] **Step 3: Maak het `HomelabPhoto`-model**

Maak `app/Models/HomelabPhoto.php` (spiegel van `ListingPhoto`):

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Storage\StorageManager;
use Database\Factories\HomelabPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén foto van een homelab-post. Spiegel van ListingPhoto: `path` wijst naar de
 * card-variant (webp), `mime` bewaart de bron zodat original.{ext} te bouwen is.
 */
class HomelabPhoto extends Model
{
    /** @use HasFactory<HomelabPhotoFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'homelab_post_id',
        'disk',
        'path',
        'width',
        'height',
        'mime',
        'byte_size',
        'position',
    ];

    /** @return BelongsTo<HomelabPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(HomelabPost::class, 'homelab_post_id');
    }

    /**
     * URL voor een variant. `path` wijst naar de card (webp); siblings worden
     * eruit afgeleid. Alleen `original` kent zijn eigen extensie, via `mime`.
     */
    public function urlFor(string $variant = 'card'): string
    {
        $ext = $variant === 'original' ? self::extForMime($this->mime) : 'webp';

        $variantPath = dirname($this->path).'/'.$variant.'.'.$ext;

        return app(StorageManager::class)->driver($this->disk)->url($variantPath);
    }

    /** Bron-extensie voor een mime. Naast de lezer gehouden, niet naast de schrijver. */
    public static function extForMime(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
```

- [ ] **Step 4: Maak de `HomelabPhotoFactory`**

Maak `database/factories/HomelabPhotoFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPhoto> */
class HomelabPhotoFactory extends Factory
{
    protected $model = HomelabPhoto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'homelab_post_id' => HomelabPost::factory(),
            'disk' => 'local',
            'path' => 'homelabs/fake/1/card.webp',
            'width' => 1200,
            'height' => 900,
            'mime' => 'image/jpeg',
            'byte_size' => 123456,
            'position' => 0,
        ];
    }
}
```

- [ ] **Step 5: Werk `HomelabPost` bij**

In `app/Models/HomelabPost.php`:

(a) Voeg `title`, `feedback_prompt`, `comments_open` toe aan `$fillable` (na de bestaande keys):

```php
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'feedback_prompt',
        'comments_open',
        'photo_disk',
        'photo_path',
        'status',
    ];
```

(b) Voeg een cast toe voor `comments_open` (nieuwe of bestaande `casts()`-methode — HomelabPost heeft die nog niet, dus toevoegen):

```php
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'comments_open' => 'boolean',
        ];
    }
```

(c) Voeg de `photos()`-relatie toe (naast de bestaande `upvotes()`):

```php
    /** @return HasMany<HomelabPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(HomelabPhoto::class)->orderBy('position');
    }
```

(d) Vervang de hele `photoUrl()`-methode (regel 68-105, inclusief de `ADDRESSABLE_VARIANTS`-const en het lange docblock) door een doorgeefluik:

```php
    /**
     * URL voor een variant van de eerste foto.
     *
     * Was een zelfstandige methode die `original` weigerde omdat de bron-mime
     * nergens stond. Nu leven foto's in homelab_photos mét mime, dus dit
     * delegeert naar HomelabPhoto::urlFor(). Feed, recent-blok en Filament
     * roepen dit onveranderd aan.
     *
     * @throws RuntimeException als de post geen foto heeft — kan niet via het
     *   formulier, wel via een half mislukte migratie. Een dode URL is erger.
     */
    public function photoUrl(string $variant = 'card'): string
    {
        $photo = $this->photos()->first();

        if ($photo === null) {
            throw new RuntimeException("Homelab post {$this->ulid} heeft geen foto.");
        }

        return $photo->urlFor($variant);
    }
```

(e) Voeg een `slug`-accessor toe. Titels mogen leeg zijn, dus de slug valt terug op de body, en anders op "homelab". De 6-teken-suffix maakt hem uniek, net als bij listings:

```php
    /**
     * URL-slug. Titel is de bron; is die leeg, dan de eerste woorden van de
     * body; is ook dat leeg, dan "homelab". Altijd een niet-lege slug, want de
     * route /homelabs/{ulid}-{slug} eist een slug-segment.
     */
    public function getSlugAttribute(): string
    {
        $base = Str::slug((string) ($this->title ?: Str::words($this->body, 6, '')));
        $base = $base !== '' ? $base : 'homelab';
        $suffix = strtolower(substr((string) $this->ulid, -6));

        return $base.'-'.$suffix;
    }
```

(f) Voeg een `getOgImageUrl()`-methode toe. Alleen jpg/png van de eerste foto; webp valt terug op de layout-default (LinkedIn rendert geen webp):

```php
    /**
     * og:image voor de deelbare pagina: original van de eerste foto, mits
     * jpg/png. Anders null → de layout valt terug op og-default.png. Dezelfde
     * regel als de advertentiepagina, en de reden dat de twee bestaande posts
     * (mime webp) het merkbeeld tonen in plaats van een kapotte link.
     */
    public function getOgImageUrl(): ?string
    {
        $photo = $this->photos()->first();

        if ($photo === null || ! in_array($photo->mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return $photo->urlFor('original');
    }
```

(g) Zorg dat de imports bovenin kloppen: voeg `use Illuminate\Support\Str;` en `use RuntimeException;` toe als ze ontbreken. `HasMany` wordt al geïmporteerd (bestaande `upvotes()`-relatie).

- [ ] **Step 6: Werk de `HomelabPostFactory` bij**

In `database/factories/HomelabPostFactory.php`, voeg de nieuwe velden toe aan `definition()`:

```php
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->optional()->sentence(4),
            'body' => fake()->sentences(2, true),
            'feedback_prompt' => null,
            'comments_open' => true,
            'photo_disk' => 'local',
            'photo_path' => 'homelabs/fake/card.webp',
            'status' => 'published',
        ];
    }
```

- [ ] **Step 7: Draai de gewijzigde tests**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabPhotoUrlTest.php`
Expected: alle vier PASS.

- [ ] **Step 8: Draai de volle homelab-suite (regressie op feed/recent/Filament)**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab`
Expected: groen. `photoUrl('card')` gedraagt zich hetzelfde voor consumenten, maar de feed-factory maakt posts zonder `homelab_photos`-rij — als een bestaande feed-test `photoUrl()` aanroept, faalt die nu op "geen foto". Meld zo'n test in je rapport: hij legt bloot dat de feed z'n foto voortaan uit de relatie haalt, wat Taak 3/5 regelen. Repareer 'm niet stil.

- [ ] **Step 9: Commit**

```bash
git add app/Models/HomelabPhoto.php app/Models/HomelabPost.php database/factories/HomelabPhotoFactory.php database/factories/HomelabPostFactory.php tests/Feature/Homelab/HomelabPhotoUrlTest.php
git commit -m "feat(homelab): HomelabPhoto-model + post als eigenaar

photoUrl() wordt een doorgeefluik naar de eerste foto; original is nu
bouwbaar via de mime-kolom. Het oude contract (original gooit) verviel —
dat was een gemis, geen feature. Slug-accessor valt terug op body dan
'homelab' zodat titelloze posts een geldige URL houden."
```

---

### Task 3: `StoreHomelabPhotoJob` schrijft een foto-rij per positie

**Achtergrond:** de job werkt nu `homelab_posts.photo_path` bij (één foto). Hij moet een `homelab_photos`-rij schrijven met een `position`, zodat meerdere foto's naast elkaar bestaan. Pad wordt `homelabs/{post_ulid}/{position}/{variant}.{ext}` — de positie in het pad houdt foto's uit elkaar. Card en thumb worden webp; original houdt de bron-mime.

**Files:**
- Modify: `app/Jobs/Homelab/StoreHomelabPhotoJob.php`
- Test: `tests/Feature/Homelab/StoreHomelabPhotoJobTest.php` (nieuw)

**Interfaces:**
- Consumes: `HomelabPhoto` (Taak 2).
- Produces: `StoreHomelabPhotoJob::__construct(int $postId, string $bytes, string $declaredMime, int $position)` — een `position`-argument erbij. `handle()` schrijft een `homelab_photos`-rij i.p.v. de post bij te werken, en werkt de post niet meer bij.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Homelab/StoreHomelabPhotoJobTest.php`:

```php
<?php

declare(strict_types=1);

use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('writes a homelab_photos row with position and source mime', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 0))->handle();

    $photo = $post->photos()->first();
    expect($photo)->not->toBeNull()
        ->and($photo->position)->toBe(0)
        ->and($photo->mime)->toBe('image/jpeg')
        ->and($photo->path)->toContain('homelabs/'.$post->ulid.'/0/card.webp')
        ->and(Storage::disk('local')->exists($photo->path))->toBeTrue()
        // De original houdt zijn bron-extensie, zodat og:image te bouwen is.
        ->and(Storage::disk('local')->exists('homelabs/'.$post->ulid.'/0/original.jpg'))->toBeTrue();
});

it('places a second photo at its own position', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 0))->handle();
    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 1))->handle();

    expect($post->photos()->count())->toBe(2)
        ->and($post->photos()->pluck('position')->all())->toBe([0, 1]);
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/StoreHomelabPhotoJobTest.php`
Expected: FAIL — de constructor kent geen `position`, en er wordt geen `homelab_photos`-rij geschreven.

- [ ] **Step 3: Herschrijf de job**

Vervang de constructor en `handle()` in `app/Jobs/Homelab/StoreHomelabPhotoJob.php`. De constructor krijgt `position`; `handle()` schrijft een rij en werkt de post niet meer bij. Bij fout wordt alleen deze foto opgeruimd — **niet** meer de post verwijderd (de aanroeper in Taak 4 beheert de transactie over alle foto's samen).

Vervang de constructor (regel 41-45):

```php
    public function __construct(
        public int $postId,
        public string $bytes,
        public string $declaredMime,
        public int $position,
    ) {}
```

Vervang de hele `handle()`-methode (regel 47-95):

```php
    public function handle(): void
    {
        $post = HomelabPost::query()->findOrFail($this->postId);

        $disk = (string) config('cloudmarktplaats.storage.driver', 'local');
        $storage = app(StorageManager::class)->driver($disk);

        $written = [];
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $actual = (string) $finfo->buffer($this->bytes);

            if (! in_array($actual, self::ALLOWED_MIMES, true) || $actual !== $this->declaredMime) {
                throw new InvalidUploadException(
                    "Unsupported or mismatched MIME type (declared {$this->declaredMime}, actual {$actual})"
                );
            }

            $info = getimagesizefromstring($this->bytes);
            if ($info === false) {
                throw new InvalidUploadException('Not a readable image');
            }
            [$w, $h] = $info;
            if ($w < self::MIN_DIM || $h < self::MIN_DIM || $w > self::MAX_DIM || $h > self::MAX_DIM) {
                throw new InvalidUploadException("Image dimensions out of bounds ({$w}x{$h})");
            }

            $image = Image::read($this->bytes);
            $stripped = clone $image;

            // De positie in het pad houdt de foto's van één post uit elkaar.
            $baseDir = 'homelabs/'.$post->ulid.'/'.$this->position;
            $originalPath = $baseDir.'/original.'.$this->extFor($actual);
            $cardPath = $baseDir.'/card.webp';
            $thumbPath = $baseDir.'/thumb.webp';

            $written[] = $this->writeOriginal($storage, $stripped, $originalPath, $actual);
            $written[] = $this->writeCard($storage, $stripped, $cardPath);
            $written[] = $this->writeThumb($storage, $stripped, $thumbPath);

            HomelabPhoto::query()->create([
                'homelab_post_id' => $post->id,
                'disk' => $disk,
                'path' => $cardPath,
                'width' => $w,
                'height' => $h,
                'mime' => $actual,
                'byte_size' => strlen($this->bytes),
                'position' => $this->position,
            ]);
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $storage->delete($path);
                } catch (Throwable) {
                    // Best-effort opruimen.
                }
            }
            throw $e;
        }
    }
```

Voeg de `writeThumb()`-helper toe (naast `writeCard()`), want de nieuwe `urlFor('thumb')` moet een bestand vinden — de oude job schreef nooit een thumb:

```php
    private function writeThumb(StorageInterface $storage, object $image, string $path): string
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->cover(300, 300);
        $storage->put($path, (string) $copy->toWebp(quality: 78));

        return $path;
    }
```

Voeg bovenin de import toe: `use App\Models\HomelabPhoto;`.

- [ ] **Step 4: Draai en zie slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/StoreHomelabPhotoJobTest.php`
Expected: beide PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/Homelab/StoreHomelabPhotoJob.php tests/Feature/Homelab/StoreHomelabPhotoJobTest.php
git commit -m "feat(homelab): foto-job schrijft een rij per positie

Constructor krijgt \$position; handle() schrijft een homelab_photos-rij
i.p.v. de post bij te werken, en ruimt bij fout alleen deze foto op — de
aanroeper beheert de transactie over alle foto's. Schrijft nu ook een
thumb (die de oude job nooit maakte terwijl photoUrl hem al adverteerde)."
```

---

### Task 4: `Feed::submit` — titel, feedback-vraag, meerdere foto's

**Achtergrond:** `submit()` accepteert nu één `photo` en een `body`. Het wordt een array van foto's (max 4), plus optionele `title` en `feedback_prompt`. De rate-limiters en de synchrone-binnen-transactie-aanpak blijven; de job wordt per foto aangeroepen met een oplopende `position`. Faalt één foto, dan rolt de hele transactie terug (geen halve post).

**Files:**
- Modify: `app/Livewire/Homelab/Feed.php`
- Test: `tests/Feature/Homelab/HomelabFeedTest.php` (toevoegen)

**Interfaces:**
- Consumes: `StoreHomelabPhotoJob` met `position` (Taak 3), config `homelab_max_count` (Taak 1).
- Produces: publieke Livewire-props `array $photos`, `?string $title`, `?string $feedbackPrompt` op `Feed`. `body` blijft.

- [ ] **Step 1: Schrijf de falende test**

Voeg toe aan `tests/Feature/Homelab/HomelabFeedTest.php`:

```php
it('accepts a title, a feedback prompt and multiple photos', function () {
    $user = App\Models\User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $a = Illuminate\Http\UploadedFile::fake()->createWithContent('a.jpg', $bytes);
    $b = Illuminate\Http\UploadedFile::fake()->createWithContent('b.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(App\Livewire\Homelab\Feed::class)
        ->set('title', 'Proxmox-cluster op drie EliteDesks')
        ->set('feedbackPrompt', 'Idle-verbruik is 38W. Kan dat lager?')
        ->set('body', 'Drie nodes, Ceph, TrueNAS in een VM. Draait al maanden stabiel.')
        ->set('photos', [$a, $b])
        ->call('submit')
        ->assertHasNoErrors();

    $post = App\Models\HomelabPost::query()->firstOrFail();
    expect($post->title)->toBe('Proxmox-cluster op drie EliteDesks')
        ->and($post->feedback_prompt)->toBe('Idle-verbruik is 38W. Kan dat lager?')
        ->and($post->photos()->count())->toBe(2);
});

it('rejects more than the homelab photo maximum', function () {
    $user = App\Models\User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $five = collect(range(1, 5))
        ->map(fn (int $i) => Illuminate\Http\UploadedFile::fake()->createWithContent("p{$i}.jpg", $bytes))
        ->all();

    Livewire::actingAs($user)
        ->test(App\Livewire\Homelab\Feed::class)
        ->set('body', 'Vijf foto’s, één te veel.')
        ->set('photos', $five)
        ->call('submit')
        ->assertHasErrors(['photos']);

    expect(App\Models\HomelabPost::query()->count())->toBe(0);
});

it('still posts without a title', function () {
    $user = App\Models\User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $photo = Illuminate\Http\UploadedFile::fake()->createWithContent('lab.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(App\Livewire\Homelab\Feed::class)
        ->set('body', 'Geen titel, wel een rack.')
        ->set('photos', [$photo])
        ->call('submit')
        ->assertHasNoErrors();

    expect(App\Models\HomelabPost::query()->firstOrFail()->title)->toBeNull();
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabFeedTest.php`
Expected: de drie nieuwe FALEN — `photos`, `title`, `feedbackPrompt` bestaan nog niet als props.

- [ ] **Step 3: Herschrijf de props en `submit()`**

In `app/Livewire/Homelab/Feed.php`:

(a) Vervang de `$photo`-prop door de nieuwe props (behoud `$body`):

```php
    /** @var array<int, \Illuminate\Http\UploadedFile> */
    public array $photos = [];

    public ?string $title = null;

    public string $body = '';

    public ?string $feedbackPrompt = null;
```

(b) Vervang de validatie- en opslag-kern van `submit()`. De rate-limiters (attempts + 1/dag) blijven ongewijzigd; vervang alleen het validatie-blok en de transactie:

```php
        $maxKb = (int) (config('cloudmarktplaats.photos.max_bytes') / 1024);
        $maxCount = (int) config('cloudmarktplaats.photos.homelab_max_count');

        $this->validate([
            'photos' => ['required', 'array', 'max:'.$maxCount],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'feedbackPrompt' => ['nullable', 'string', 'max:280'],
        ], [
            'photos.required' => __('We hebben geen foto\'s ontvangen. Koos je ze wél? Dan is het uploaden misgegaan — probeer het opnieuw, eventueel met minder of kleinere foto\'s.'),
            'photos.max' => __('Maximaal :max foto\'s per homelab.'),
            'photos.*.uploaded' => __('Deze foto is niet aangekomen. Meestal is hij te groot: maximaal :max MB per foto.', ['max' => (int) (config('cloudmarktplaats.photos.max_bytes') / 1024 / 1024)]),
            'photos.*.max' => __('Deze foto is te groot. Maximaal :max MB per foto.', ['max' => (int) (config('cloudmarktplaats.photos.max_bytes') / 1024 / 1024)]),
            'photos.*.mimes' => __('Alleen JPG, PNG of WebP.'),
        ]);
```

Vervang de `DB::transaction(...)`-body (de plek waar nu één `HomelabPost::create()` + één synchrone job staat) door:

```php
        try {
            $post = DB::transaction(function () use ($userId): HomelabPost {
                $post = HomelabPost::query()->create([
                    'user_id' => $userId,
                    'title' => $this->title,
                    'body' => $this->body,
                    'feedback_prompt' => $this->feedbackPrompt,
                    'comments_open' => true,
                    'photo_disk' => (string) config('cloudmarktplaats.storage.driver', 'local'),
                    'photo_path' => 'pending',
                ]);

                // Synchroon binnen de transactie: de post wordt pas zichtbaar als
                // álle foto's geslaagd zijn. Faalt er één, dan rolt de hele
                // transactie terug — geen homelab met een gat in de galerij.
                foreach (array_values($this->photos) as $position => $photo) {
                    (new StoreHomelabPhotoJob(
                        $post->id,
                        (string) file_get_contents((string) $photo->getRealPath()),
                        (string) $photo->getMimeType(),
                        $position,
                    ))->handle();
                }

                return $post;
            });
        } catch (\Throwable $e) {
            report($e);
            $this->addError('photos', __('Het uploaden is misgegaan. Vaak zijn de foto\'s samen te groot, of viel de verbinding weg. Probeer het opnieuw met minder of kleinere foto\'s.'));

            return;
        }
```

Let op: `photo_path => 'pending'` op de post blijft staan — die kolom is dood na deze feature (de foto's leven in `homelab_photos`), maar hij is `NOT NULL` in het oude schema, dus hij moet gevuld worden tot de opruim-migratie later. Verander dat hier niet.

(c) Werk het `$this->reset(...)` aan het eind van `submit()` bij zodat het de nieuwe props leegt: vervang `'photo'` door `'photos', 'title', 'feedbackPrompt'` in de reset-aanroep, naast het bestaande `'body'`.

- [ ] **Step 4: Draai de homelab-suite**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab`
Expected: groen, inclusief de drie nieuwe. Een bestaande test die `->set('photo', ...)` gebruikt faalt nu (de prop heet `photos` en is een array) — pas die aan naar `->set('photos', [$upload])`; dat is geen stille reparatie maar het volgen van de hernoemde interface. Meld welke tests je zo aanpaste.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Homelab/Feed.php tests/Feature/Homelab/HomelabFeedTest.php
git commit -m "feat(homelab): posten met titel, feedback-vraag en tot 4 foto's

Foto's worden per stuk synchroon verwerkt binnen de transactie, met een
oplopende positie. Faalt er één, dan rolt alles terug — nooit een homelab
met een gat. Titel en feedback-vraag mogen leeg."
```

---

### Task 5: Het formulier — velden + upload-zichtbaarheid

**Achtergrond:** het feed-formulier heeft nu één bestand-input en een body-veld, zonder enige upload-statusafhandeling — precies de stille faalwijze die we bij de advertentiewizard hebben opgelost. Dit formulier krijgt dezelfde behandeling plus de nieuwe velden.

**Files:**
- Modify: `resources/views/livewire/homelab/feed.blade.php`
- Modify: `lang/en.json`
- Test: handmatig (blade), plus de bestaande Feed-tests uit Taak 4 dekken de backend.

**Interfaces:**
- Consumes: props `photos`, `title`, `body`, `feedbackPrompt` (Taak 4); config `photos.max_bytes` / `homelab_max_count`.

- [ ] **Step 1: Herschrijf het formulier**

Vervang in `resources/views/livewire/homelab/feed.blade.php` het formulier-blok (het `<form wire:submit="submit">` met de bestaande `wire:model="photo"`-input en het body-veld) door onderstaande. Dit spiegelt de behandeling uit `wizard.blade.php`: client-side controle bij het kiezen, voortgang, en een leesbare uploadfout.

```blade
@php
    $maxBytes = config('cloudmarktplaats.photos.max_bytes');
    $maxCount = config('cloudmarktplaats.photos.homelab_max_count');
    $maxMb = (int) ($maxBytes / 1024 / 1024);
@endphp
<form wire:submit="submit" class="space-y-3"
      x-data="{
          bezig: false,
          voortgang: 0,
          probleem: '',
          maxPerFoto: {{ $maxBytes }},
          maxAantal: {{ $maxCount }},
          keuze($event) {
              this.probleem = '';
              const fotos = [...$event.target.files];
              const teGroot = fotos.filter(f => f.size > this.maxPerFoto);
              if (fotos.length > this.maxAantal) {
                  this.probleem = @js(__('Je koos :n foto\'s. Er passen er maximaal :max in één homelab.', ['max' => $maxCount])).replace(':n', fotos.length);
              } else if (teGroot.length) {
                  this.probleem = @js(__('Te groot: :namen. Maximaal :max MB per foto — verklein ze en probeer het opnieuw.', ['max' => $maxMb])).replace(':namen', teGroot.map(f => f.name).join(', '));
              }
          },
      }"
      x-on:livewire-upload-start="bezig = true; voortgang = 0"
      x-on:livewire-upload-progress="voortgang = $event.detail.progress"
      x-on:livewire-upload-finish="bezig = false; voortgang = 100"
      x-on:livewire-upload-cancel="bezig = false"
      x-on:livewire-upload-error="bezig = false; probleem = @js(__('Het uploaden is misgegaan. Vaak zijn de foto\'s samen te groot, of viel de verbinding weg. Probeer het opnieuw met minder of kleinere foto\'s.'))">

    <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ __('Titel (optioneel)') }}</span>
        <input type="text" wire:model="title" maxlength="120"
               class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
    </label>
    @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ __('Vertel over je lab') }}</span>
        <textarea wire:model="body" rows="4" maxlength="500"
                  class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal"></textarea>
    </label>
    @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ __('Waar wil je feedback op? (optioneel)') }}</span>
        <input type="text" wire:model="feedbackPrompt" maxlength="280"
               placeholder="{{ __('Bijv. idle-verbruik, kabelwerk, of je backup-strategie') }}"
               class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
    </label>
    @error('feedbackPrompt') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    <label class="block text-sm">
        <span class="mb-1 block font-medium">{{ __('Foto\'s (1–:max, max :mb MB elk)', ['max' => $maxCount, 'mb' => $maxMb]) }}</span>
        <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp"
               x-on:change="keuze($event)"
               class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
    </label>

    <div x-show="bezig" x-cloak class="space-y-1" role="status" aria-live="polite">
        <div class="flex justify-between text-xs text-cmp-muted">
            <span>{{ __('Foto\'s uploaden…') }}</span>
            <span class="font-mono" x-text="voortgang + '%'"></span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-cmp-bg2">
            <div class="h-full rounded-full bg-cmp-signal transition-all" x-bind:style="'width: ' + Math.max(2, voortgang) + '%'"></div>
        </div>
    </div>

    <p x-show="probleem" x-cloak x-text="probleem" class="text-sm text-red-600" role="alert"></p>
    @error('photos') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('photos.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    <p class="text-xs text-cmp-muted">{{ __('EXIF (waaronder GPS) wordt automatisch verwijderd na uploaden.') }}</p>

    <button x-bind:disabled="bezig"
            class="cmp-btn cmp-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
        <span x-show="! bezig">{{ __('Plaatsen') }}</span>
        <span x-show="bezig" x-cloak>{{ __('Bezig met uploaden…') }}</span>
    </button>
</form>
```

Behoud de bestaande omringende structuur (de kop boven het formulier en de feed eronder). Alleen het `<form>`-blok wordt vervangen.

- [ ] **Step 2: Voeg de Engelse vertalingen toe**

Voeg aan `lang/en.json` toe (sommige bestaan al van de wizard-fix — dubbele keys zijn ongeldig in JSON, dus voeg alleen de nog niet bestaande toe). Nieuw voor dit formulier:

```json
    "Titel (optioneel)": "Title (optional)",
    "Vertel over je lab": "Tell us about your lab",
    "Waar wil je feedback op? (optioneel)": "What would you like feedback on? (optional)",
    "Bijv. idle-verbruik, kabelwerk, of je backup-strategie": "E.g. idle power draw, cable management, or your backup strategy",
    "Je koos :n foto's. Er passen er maximaal :max in één homelab.": "You picked :n photos. A homelab holds :max at most.",
    "Maximaal :max foto's per homelab.": "At most :max photos per homelab.",
    "Plaatsen": "Post"
}
```

Controleer eerst welke van de upload-strings (`Foto's uploaden…`, `Bezig met uploaden…`, `Het uploaden is misgegaan…`, `Te groot: :namen…`, `Foto's (1–:max, max :mb MB elk)`, `Deze foto is te groot…`, `Alleen JPG, PNG of WebP.`) al bestaan uit de wizard-fix, en voeg die niet opnieuw toe. Het bestand moet geldige JSON blijven.

- [ ] **Step 3: Bouw de assets en controleer de vertalingen**

```bash
npm run build
docker compose exec -T php-fpm php -r 'json_decode(file_get_contents("lang/en.json"), true, 512, JSON_THROW_ON_ERROR); echo "geldige JSON\n";'
docker compose exec -T php-fpm php artisan tinker --execute="app()->setLocale('en'); echo __('Waar wil je feedback op? (optioneel)').' | '.__('Plaatsen');"
```

Expected: `geldige JSON`, en `What would you like feedback on? (optional) | Post`.

- [ ] **Step 4: Draai de homelab-suite (regressie)**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab`
Expected: groen — de backend-tests uit Taak 4 dekken het gedrag; deze taak raakt alleen de weergave.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/homelab/feed.blade.php lang/en.json public/build
git commit -m "feat(homelab): formulier met titel, feedback-vraag en zichtbare upload

Titel, verhaal, feedback-vraag en meerdere foto's. Het formulier krijgt
dezelfde upload-behandeling als de wizard: te grote of te veel foto's
meteen gemeld, voortgang tijdens uploaden, knop uit. Geen stille faalwijze."
```

---

### Task 6: De pagina — route, component, blade

**Achtergrond:** dit is waar de feature zichtbaar wordt. De pagina spiegelt `Listings\Detail`: handmatige ulid-lookup, 301 als de slug niet klopt, OG-tags via `layoutData()`. Anonimiteit: nergens de bouwer.

**Files:**
- Create: `app/Livewire/Homelab/Detail.php`
- Create: `resources/views/livewire/homelab/detail.blade.php`
- Modify: `routes/web.php`
- Modify: `lang/en.json`
- Test: `tests/Feature/Homelab/HomelabDetailTest.php` (nieuw)

**Interfaces:**
- Consumes: `HomelabPost::getSlugAttribute()`, `getOgImageUrl()`, `photos()`, `photoUrl()` (Taak 2).
- Produces: route `homelab.detail` op `/homelabs/{ulid}-{slug}`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Homelab/HomelabDetailTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use App\Models\User;

function homelabWithPhoto(array $attributes = []): HomelabPost
{
    $post = HomelabPost::factory()->create($attributes);
    HomelabPhoto::factory()->for($post)->create([
        'path' => 'homelabs/'.$post->ulid.'/0/card.webp',
        'position' => 0,
        'mime' => 'image/jpeg',
    ]);

    return $post;
}

it('renders a homelab page at its own url', function () {
    $post = homelabWithPhoto(['title' => 'Proxmox-cluster', 'body' => 'Drie nodes.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('Proxmox-cluster')
        ->assertSee('Drie nodes.');
});

it('never shows who built it', function () {
    $owner = User::factory()->create(['username' => 'marco', 'email' => 'marco@example.com']);
    $post = homelabWithPhoto(['user_id' => $owner->id, 'title' => 'Rack', 'body' => 'Mijn lab.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertDontSee('marco')
        ->assertDontSee('marco@example.com');
});

it('shows the feedback prompt only when set', function () {
    $with = homelabWithPhoto(['body' => 'A', 'feedback_prompt' => 'Kan het zuiniger?']);
    $this->get("/homelabs/{$with->ulid}-{$with->slug}")->assertSee('Kan het zuiniger?');

    $without = homelabWithPhoto(['body' => 'B', 'feedback_prompt' => null]);
    $this->get("/homelabs/{$without->ulid}-{$without->slug}")->assertDontSee('feedback');
});

it('301-redirects a wrong slug to the canonical url', function () {
    $post = homelabWithPhoto(['title' => 'Cluster', 'body' => 'x']);

    $this->get("/homelabs/{$post->ulid}-verkeerd")
        ->assertRedirect("/homelabs/{$post->ulid}-{$post->slug}");
});

it('renders a titleless post using a body fragment as heading', function () {
    $post = homelabWithPhoto(['title' => null, 'body' => 'Een klein maar fijn rack in de meterkast.']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('Een klein maar fijn rack');
});

it('404s a removed post', function () {
    $post = homelabWithPhoto(['status' => 'removed', 'body' => 'weg']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")->assertNotFound();
});

it('sets og:image to the jpg original', function () {
    $post = homelabWithPhoto(['title' => 'Rack', 'body' => 'x']);

    $this->get("/homelabs/{$post->ulid}-{$post->slug}")
        ->assertOk()
        ->assertSee('original.jpg', escape: false);
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabDetailTest.php`
Expected: FAIL — de route en component bestaan niet.

- [ ] **Step 3: Maak de Detail-component**

Maak `app/Livewire/Homelab/Detail.php` (spiegel van `Listings\Detail`):

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Models\HomelabPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Publieke homelab-pagina. Spiegel van Listings\Detail: handmatige ulid-lookup
 * met 301-canonicalisatie op de slug, OG-tags via layoutData().
 *
 * Anonimiteit: de bouwer wordt nooit gerenderd. De view krijgt uitsluitend de
 * post en zijn foto's, geen user.
 */
class Detail extends Component
{
    public HomelabPost $post;

    public function mount(string $ulid, string $slug): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);

        $post = HomelabPost::query()
            ->where('ulid', $ulid)
            ->where('status', 'published')
            ->first();

        if ($post === null) {
            abort(404);
        }

        if ($slug !== $post->slug) {
            abort(new RedirectResponse("/homelabs/{$post->ulid}-{$post->slug}", 301));
        }

        $this->post = $post;
    }

    /** Titel voor kop en og:title; zonder titel een fragment van de body. */
    public function heading(): string
    {
        return $this->post->title ?: Str::limit($this->post->body, 60);
    }

    public function render(): View
    {
        return view('livewire.homelab.detail')->layoutData([
            'title' => $this->heading().' — Cloudmarktplaats',
            'description' => Str::limit(strip_tags($this->post->body), 150),
            'ogImage' => $this->post->getOgImageUrl(),
            'canonical' => url("/homelabs/{$this->post->ulid}-{$this->post->slug}"),
        ]);
    }
}
```

- [ ] **Step 4: Maak de blade**

Maak `resources/views/livewire/homelab/detail.blade.php`. Gebruikt de marketing-layout via de component (Livewire full-page). Toont galerij, kop, body, feedback-kader, waarderingen en de meld-knop. **Nergens de bouwer.**

```blade
<div>
    <article class="mx-auto max-w-3xl px-5 py-10 sm:px-8">

        <div class="mb-2 flex items-baseline justify-between gap-4">
            <h1 class="text-2xl font-bold tracking-display-tight sm:text-3xl">{{ $this->heading() }}</h1>
            <span class="shrink-0 text-sm text-cmp-muted">{{ $post->created_at->diffForHumans() }}</span>
        </div>

        @if ($post->photos->isNotEmpty())
            <div class="mb-6 grid grid-cols-1 gap-1 bg-cmp-bg2 sm:grid-cols-2">
                @foreach ($post->photos as $photo)
                    <img src="{{ $photo->urlFor('card') }}" alt="{{ __('Homelab-foto') }}" loading="lazy"
                         class="aspect-[4/3] w-full object-cover">
                @endforeach
            </div>
        @endif

        <div class="prose prose-sm max-w-none whitespace-pre-line text-cmp-text">{{ $post->body }}</div>

        @if ($post->feedback_prompt)
            <div class="mt-6 rounded-sm border-2 border-cmp-ink bg-cmp-surface p-4">
                <div class="cmp-section-label mb-1">{{ __('De bouwer vraagt feedback op') }}</div>
                <p class="text-cmp-text">{{ $post->feedback_prompt }}</p>
            </div>
        @endif

        <div class="mt-6 flex items-center justify-between border-t border-cmp-border pt-4">
            <div class="font-mono text-sm text-cmp-muted">
                ▲ {{ number_format($post->upvotes()->count(), 0, ',', '.') }} {{ __('waarderingen') }}
            </div>
            <a href="{{ route('reports.homelab.store', $post->ulid) }}"
               class="text-xs text-cmp-faint hover:text-cmp-signal"
               onclick="event.preventDefault();"
               title="{{ __('Melden') }}">⚑ {{ __('Melden') }}</a>
        </div>
    </article>
</div>
```

Noot voor de implementer: de meld-route is een `POST` (`reports.homelab.store`). Een `<a href>` met `preventDefault` is hier een placeholder-knop — het volledige meld-formulier bestaat al elders in de feed en valt buiten deze taak. Laat de knop naar de feed-meldflow verwijzen zoals die daar werkt, of toon 'm als niet-actieve markering. Kies de variant die de bestaande feed-blade ook gebruikt; kopieer dat patroon in plaats van een nieuwe POST-knop te verzinnen.

- [ ] **Step 5: Registreer de route**

In `routes/web.php`, direct onder de bestaande `/homelabs`-route (regel 57):

```php
Route::get('/homelabs/{ulid}-{slug}', \App\Livewire\Homelab\Detail::class)
    ->where('ulid', '[0-9a-z]{26}')
    ->where('slug', '[a-z0-9-]+')
    ->name('homelab.detail');
```

Let op de ulid-constraint: homelab-ulids worden **lowercase** opgeslagen (`HomelabPost::booted()` doet `strtolower`), anders dan listing-ulids (uppercase Crockford). Vandaar `[0-9a-z]{26}`, niet de listing-regex.

- [ ] **Step 6: Voeg de Engelse vertalingen toe**

In `lang/en.json`:

```json
    "De bouwer vraagt feedback op": "The builder wants feedback on",
    "waarderingen": "appreciations",
    "Melden": "Report"
```

(Controleer of `waarderingen` / `Melden` al bestaan; zo ja, niet opnieuw toevoegen.)

- [ ] **Step 7: Draai de detail-suite**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabDetailTest.php`
Expected: alle acht PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Homelab/Detail.php resources/views/livewire/homelab/detail.blade.php routes/web.php lang/en.json
git commit -m "feat(homelab): eigen pagina met galerij, feedback-kader en og:image

Spiegel van Listings\\Detail: ulid-lookup, 301 op verkeerde slug, OG-tags
via layoutData(). Toont nergens de bouwer — het anonimiteitscontract.
Titelloze posts krijgen een body-fragment als kop."
```

---

### Task 7: De feed linkt naar de pagina

**Achtergrond:** de feed-kaart is nu niet klikbaar. Elke kaart moet doorlinken naar zijn eigen pagina.

**Files:**
- Modify: `resources/views/livewire/homelab/feed.blade.php`
- Test: `tests/Feature/Homelab/HomelabFeedTest.php` (toevoegen)

**Interfaces:**
- Consumes: route `homelab.detail` (Taak 6).

- [ ] **Step 1: Schrijf de falende test**

Voeg toe aan `tests/Feature/Homelab/HomelabFeedTest.php`:

```php
it('links each feed card to its own page', function () {
    $post = App\Models\HomelabPost::factory()->create(['title' => 'Rack', 'body' => 'x']);
    App\Models\HomelabPhoto::factory()->for($post)->create([
        'path' => 'homelabs/'.$post->ulid.'/0/card.webp',
        'position' => 0,
    ]);

    Livewire::test(App\Livewire\Homelab\Feed::class)
        ->assertSee("/homelabs/{$post->ulid}-{$post->slug}", escape: false);
});
```

- [ ] **Step 2: Draai en zie falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabFeedTest.php --filter="links each feed card"`
Expected: FAIL — er is geen link.

- [ ] **Step 3: Maak de kaart klikbaar**

In `resources/views/livewire/homelab/feed.blade.php`, wikkel de foto in de kaart (`<img src="{{ $post->photoUrl('card') }}" ...>`) in een link naar de detailpagina:

```blade
<a href="{{ route('homelab.detail', ['ulid' => $post->ulid, 'slug' => $post->slug]) }}"
   wire:navigate class="block aspect-[4/3] overflow-hidden bg-cmp-bg2">
    <img src="{{ $post->photoUrl('card') }}" alt="{{ __('Homelab-foto') }}" loading="lazy"
         class="h-full w-full object-cover transition-transform duration-200 hover:scale-105">
</a>
```

- [ ] **Step 4: Draai en zie slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Homelab/HomelabFeedTest.php`
Expected: groen.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/homelab/feed.blade.php tests/Feature/Homelab/HomelabFeedTest.php
git commit -m "feat(homelab): feed-kaart linkt naar de eigen pagina"
```

---

### Task 8: Kwaliteitspoorten en uitrol

**Achtergrond:** deployen is een file-sync naar LXC 214, géén git pull. Er is een migratie (nieuwe tabel + kolommen + datamigratie van de twee bestaande posts) en er zijn nieuwe assets — beide moeten mee. De migratie is de spannende stap: hij verplaatst de foto's van de enige twee echte homelabs.

**Files:** geen wijzigingen; dit is de uitrol van Taak 1-7.

- [ ] **Step 1: Volle suite en poorten**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: alles groen. Pint klaagt? `docker compose exec -T php-fpm ./vendor/bin/pint` en commit apart.

- [ ] **Step 2: Sync code en assets**

```bash
cd /mnt/nvme1tb/projects/cloudmarktplaats
tar czf - \
  app/Models/HomelabPhoto.php \
  app/Models/HomelabPost.php \
  app/Jobs/Homelab/StoreHomelabPhotoJob.php \
  app/Livewire/Homelab/Feed.php \
  app/Livewire/Homelab/Detail.php \
  database/migrations/2026_07_16_100000_add_homelab_pages.php \
  config/cloudmarktplaats.php \
  resources/views/livewire/homelab/feed.blade.php \
  resources/views/livewire/homelab/detail.blade.php \
  routes/web.php \
  lang/en.json \
  public/build \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && tar xzf - && chown -R 1000:1000 app/Models/HomelabPhoto.php app/Models/HomelabPost.php app/Jobs/Homelab/StoreHomelabPhotoJob.php app/Livewire/Homelab config/cloudmarktplaats.php database/migrations/2026_07_16_100000_add_homelab_pages.php resources/views/livewire/homelab routes/web.php lang/en.json public/build && echo synced'"
```

Expected: `synced`.

- [ ] **Step 3: Migreer — met een controle vooraf en achteraf**

Kijk eerst wat er staat, draai dan de migratie, controleer dan of de twee posts hun foto-rij kregen:

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T postgres psql -U app -d cloudmarktplaats -t -c \"SELECT count(*) FROM homelab_posts WHERE photo_path IS NOT NULL AND photo_path <> chr(112)||chr(101)||chr(110)||chr(100)||chr(105)||chr(110)||chr(103);\"'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan migrate --force'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T postgres psql -U app -d cloudmarktplaats -t -c \"SELECT count(*) FROM homelab_photos;\"'"
```

Expected: het eerste getal (posts met een echte foto) is gelijk aan het laatste (rijen in `homelab_photos`). Zijn ze ongelijk, dan miste de datamigratie een post — meld dat vóór je verder gaat.

- [ ] **Step 4: Cache en herstart**

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan config:cache && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart php-fpm'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart nginx'"
```

`config:cache` want er is een nieuwe config-key; view:clear want de blades wijzigden; nginx ná php-fpm.

- [ ] **Step 5: Verifieer publiek**

```bash
sleep 3
echo -n "  feed: "; curl -s -o /dev/null -w "%{http_code}\n" https://cloudmarktplaats.nl/homelabs
echo "  een homelab-pagina:"; curl -s https://cloudmarktplaats.nl/homelabs | grep -oE '/homelabs/[0-9a-z]{26}-[a-z0-9-]+' | head -1
echo -n "  healthz: "; curl -s -o /dev/null -w "%{http_code}\n" https://cloudmarktplaats.nl/healthz
```

Pak de gevonden homelab-URL en open die met eigen ogen. Controleer: de foto('s) laden, er staat nergens een gebruikersnaam, en delen op LinkedIn toont een afbeelding (voor de twee bestaande posts is dat het merkbeeld — hun mime is webp — voor nieuwe posts hun eigen foto).

- [ ] **Step 6: Registreer als bezoeker een homelab met twee foto's**

Doe dit met eigen ogen op prod: log in, plaats een homelab met een titel, een feedback-vraag en twee foto's, en controleer dat de pagina ze allebei toont in volgorde en dat de deelkaart klopt. Verwijder de testpost daarna via je eigen account of Filament — de feed is publiek en klein.

---

## Rollback

- **De pagina/route:** `git revert` van Taak 6-7 en opnieuw syncen; de feed werkt weer zonder detaillinks.
- **De migratie:** `php artisan migrate:rollback` draait de `down()` — `homelab_photos` valt weg en de drie kolommen verdwijnen. De foto's staan nog op schijf en `homelab_posts.photo_path` is nooit aangeraakt, dus de oude feed werkt weer. Draai rollback alleen samen met een revert van Taak 2-5, want die code leest uit `homelab_photos`.
- **De twee bestaande posts:** hun bestanden op schijf en hun `photo_path`-kolom blijven in elk scenario ongemoeid.
