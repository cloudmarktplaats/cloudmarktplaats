# "Uit de homelabs" Feed Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pseudonymous homelab showcase feed — logged-in users post 1 photo + ≤500 chars; the public feed reveals nothing about who posted.

**Architecture:** New `homelab_posts` table + `HomelabPost` model; a photo-ingest job cloned from the listing pipeline (EXIF strip, variants); a full-page Livewire `Homelab\Feed` component at `/homelabs` (feed + post form + own-post delete); a `HomelabRecent` widget on the homepage; report flow reusing the polymorphic `reports` table; a Filament resource where only admins see the poster. Everything behind the `FEATURE_HOMELAB_FEED` flag.

**Tech Stack:** Laravel 11, Livewire 3 (`WithFileUploads`, `#[Layout]`), Intervention Image, Filament 3, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-homelab-feed-design.md`.
- **Anonymity contract:** `user_id` never renders publicly — no username, display_name, avatar, or exact timestamp in public HTML. Relative time only (`->diffForHumans()`).
- Rate limit: 1 post per account per 24h. Body max 500 chars. Photo required, `jpg,jpeg,png,webp`, max 8192 KB.
- Feature flag key: `config('cloudmarktplaats.features.homelab_feed')`, env `FEATURE_HOMELAB_FEED`, default `true`.
- Styling per `docs/DESIGN.md`: `rounded-sm`, cmp-tokens, mono for data, `cmp-label-chip` for the HOMELAB chip. No likes/comments/edit (YAGNI).
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 must stay green: run `docker compose exec -T php-fpm ./vendor/bin/pint --dirty` and `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse` before each commit.
- Tests run in Docker: `docker compose exec -T php-fpm php artisan test --filter=<Name>`.

---

### Task 1: Migration, model, factory, feature flag

**Files:**
- Create: `database/migrations/2026_07_03_000100_create_homelab_posts_table.php`
- Create: `app/Models/HomelabPost.php`
- Create: `database/factories/HomelabPostFactory.php`
- Modify: `config/cloudmarktplaats.php` (features array, na `'umami_analytics'`)
- Modify: `.env.example` (na `FEATURE_UMAMI=false`)
- Test: `tests/Feature/Homelab/HomelabPostModelTest.php`

**Interfaces:**
- Produces: `App\Models\HomelabPost` met `fillable [user_id, body, photo_disk, photo_path, status]`, auto-`ulid` bij create, `scopePublished(Builder $q): Builder`, `photoUrl(string $variant = 'card'): string`, relatie `user(): BelongsTo`.
- Produces: `HomelabPost::factory()` → published post met fake pad; state `removed()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\User;

it('mints a ulid and defaults to published', function () {
    $post = HomelabPost::factory()->create();

    expect($post->ulid)->toBeString()->toHaveLength(26)
        ->and($post->status)->toBe('published');
});

it('scopes to published only', function () {
    HomelabPost::factory()->create();
    HomelabPost::factory()->removed()->create();

    expect(HomelabPost::query()->published()->count())->toBe(1);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $post = HomelabPost::factory()->for($user)->create();

    expect($post->user->is($user))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabPostModelTest`
Expected: FAIL — class `HomelabPost` not found.

- [ ] **Step 3: Write migration, model, factory, flag**

Migration:

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
        Schema::create('homelab_posts', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            // Interne verantwoordingslijn — wordt nooit publiek gerenderd.
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('photo_disk')->default('local');
            $t->string('photo_path');
            $t->enum('status', ['published', 'removed'])->default('published');
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_posts');
    }
};
```

Model `app/Models/HomelabPost.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Storage\StorageManager;
use Database\Factories\HomelabPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pseudonieme homelab-showcase-post.
 *
 * Anonimiteitscontract: user_id bestaat voor rate-limits, eigen-post-
 * verwijderen en moderatie, maar wordt NOOIT publiek gerenderd. Publiek
 * zichtbaar zijn uitsluitend foto, body en relatieve tijd.
 */
class HomelabPost extends Model
{
    /** @use HasFactory<HomelabPostFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'body',
        'photo_disk',
        'photo_path',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (HomelabPost $post) {
            $post->ulid ??= strtolower((string) Str::ulid());
        });
    }

    /**
     * @param  Builder<HomelabPost>  $query
     * @return Builder<HomelabPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoUrl(string $variant = 'card'): string
    {
        $sourceExt = pathinfo($this->photo_path, PATHINFO_EXTENSION);
        $ext = $variant === 'original' ? $sourceExt : 'webp';
        $variantPath = dirname($this->photo_path).'/'.$variant.'.'.$ext;

        return app(StorageManager::class)->driver($this->photo_disk)->url($variantPath);
    }
}
```

Factory `database/factories/HomelabPostFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPost> */
class HomelabPostFactory extends Factory
{
    protected $model = HomelabPost::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentences(2, true),
            'photo_disk' => 'local',
            'photo_path' => 'homelabs/fake/card.webp',
            'status' => 'published',
        ];
    }

    public function removed(): static
    {
        return $this->state(fn () => ['status' => 'removed']);
    }
}
```

Config: in `config/cloudmarktplaats.php` na de `umami_analytics`-regel:

```php
        'homelab_feed' => env('FEATURE_HOMELAB_FEED', true),
```

`.env.example`: na `FEATURE_UMAMI=false`:

```
FEATURE_HOMELAB_FEED=true
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabPostModelTest`
Expected: 3 passed. Run migrate eerst als nodig: tests draaien met RefreshDatabase (volg `tests/Feature/Listings`-conventie — check `tests/Pest.php`, RefreshDatabase zit daar al globaal).

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add database/migrations/2026_07_03_000100_create_homelab_posts_table.php app/Models/HomelabPost.php database/factories/HomelabPostFactory.php config/cloudmarktplaats.php .env.example tests/Feature/Homelab/HomelabPostModelTest.php
git commit -m "Add HomelabPost model + homelab_feed feature flag"
```

---

### Task 2: Photo-ingest job (EXIF strip + varianten)

**Files:**
- Create: `app/Jobs/Homelab/StoreHomelabPhotoJob.php`
- Test: `tests/Feature/Homelab/StoreHomelabPhotoJobTest.php`

**Interfaces:**
- Consumes: `HomelabPost` (Task 1), `App\Services\Storage\StorageManager`, `App\Exceptions\InvalidUploadException` (bestaat).
- Produces: `new StoreHomelabPhotoJob(int $postId, string $bytes, string $declaredMime)`; schrijft `homelabs/{ulid}/original.{ext}` + `homelabs/{ulid}/card.webp` en zet `photo_path` op het card-pad. Bij falen: blobs opgeruimd + post verwijderd + exception re-thrown.

De job is een bewuste kloon van `app/Jobs/Listings/StoreListingPhotoJob.php` (zelfde MIME/dimensie/EXIF-regels), versimpeld tot één foto en twee varianten. Lees dat bestand eerst — de commentaren daar leggen het waarom uit.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use App\Services\Storage\StorageManager;

it('stores original + card variants and strips EXIF', function () {
    $post = HomelabPost::factory()->create(['photo_path' => 'pending']);
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg'))->handle();

    $post->refresh();
    expect($post->photo_path)->toBe("homelabs/{$post->ulid}/card.webp");

    $storage = app(StorageManager::class)->driver($post->photo_disk);
    expect($storage->exists("homelabs/{$post->ulid}/original.jpg"))->toBeTrue()
        ->and($storage->exists("homelabs/{$post->ulid}/card.webp"))->toBeTrue();

    // EXIF weg: het origineel mag geen GPS-tags meer bevatten.
    $original = $storage->get("homelabs/{$post->ulid}/original.jpg");
    expect(str_contains($original, 'GPS'))->toBeFalse();
});

it('rejects a mismatched mime and leaves nothing behind', function () {
    $post = HomelabPost::factory()->create(['photo_path' => 'pending']);

    expect(fn () => (new StoreHomelabPhotoJob($post->id, 'not-an-image', 'image/jpeg'))->handle())
        ->toThrow(InvalidUploadException::class);
});
```

Let op: kopieer de asserts uit `tests/Feature/Listings/WizardTest.php` als de EXIF-assert daar strenger is (bijv. via `exif_read_data`) — volg dan die aanpak; de test hierboven is het minimum.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=StoreHomelabPhotoJobTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Homelab;

use App\Exceptions\InvalidUploadException;
use App\Models\HomelabPost;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
use finfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Foto-ingest voor homelab-posts. Kloon van StoreListingPhotoJob,
 * versimpeld tot één foto met twee varianten:
 *   - original: max 2000px lange zijde, bron-mime, EXIF gestript
 *   - card:     cover 600x600 webp (feed-grid)
 * Pad: homelabs/{post_ulid}/{variant}.{ext}. De DB-rij wijst naar card.
 */
class StoreHomelabPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MIN_DIM = 200;

    private const MAX_DIM = 8000;

    private const ORIGINAL_MAX_LONG_EDGE = 2000;

    public function __construct(
        public int $postId,
        public string $bytes,
        public string $declaredMime,
    ) {}

    public function handle(): void
    {
        $post = HomelabPost::query()->findOrFail($this->postId);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actual = (string) $finfo->buffer($this->bytes);

        if (! in_array($actual, self::ALLOWED_MIMES, true) || $actual !== $this->declaredMime) {
            throw new InvalidUploadException(
                "Unsupported or mismatched MIME type (declared {$this->declaredMime}, actual {$actual})"
            );
        }

        $image = Image::read($this->bytes);
        $w = $image->width();
        $h = $image->height();
        if ($w < self::MIN_DIM || $h < self::MIN_DIM || $w > self::MAX_DIM || $h > self::MAX_DIM) {
            throw new InvalidUploadException("Image dimensions out of bounds ({$w}x{$h})");
        }

        // Privacy: EXIF/IPTC/XMP weg vóór her-encoderen.
        $stripped = clone $image;

        $post->photo_disk = (string) config('cloudmarktplaats.storage.driver', 'local');
        $storage = app(StorageManager::class)->driver($post->photo_disk);

        $baseDir = 'homelabs/'.$post->ulid;
        $originalPath = $baseDir.'/original.'.$this->extFor($actual);
        $cardPath = $baseDir.'/card.webp';

        $written = [];
        try {
            $written[] = $this->writeOriginal($storage, $stripped, $originalPath, $actual);
            $written[] = $this->writeCard($storage, $stripped, $cardPath);

            $post->forceFill(['photo_path' => $cardPath])->save();
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $storage->delete($path);
                } catch (Throwable) {
                    // Best-effort cleanup.
                }
            }
            $post->delete();
            throw $e;
        }
    }

    private function writeOriginal(StorageInterface $storage, object $image, string $path, string $mime): string
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->scaleDown(self::ORIGINAL_MAX_LONG_EDGE, self::ORIGINAL_MAX_LONG_EDGE);
        $encoded = match ($mime) {
            'image/jpeg' => $copy->toJpeg(quality: 88),
            'image/png' => $copy->toPng(),
            'image/webp' => $copy->toWebp(quality: 88),
            default => $copy->toJpeg(quality: 88),
        };
        $storage->put($path, (string) $encoded);

        return $path;
    }

    private function writeCard(StorageInterface $storage, object $image, string $path): string
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->cover(600, 600);
        $storage->put($path, (string) $copy->toWebp(quality: 82));

        return $path;
    }

    private function extFor(string $mime): string
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

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=StoreHomelabPhotoJobTest`
Expected: 2 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add app/Jobs/Homelab/StoreHomelabPhotoJob.php tests/Feature/Homelab/StoreHomelabPhotoJobTest.php
git commit -m "Add homelab photo-ingest job (EXIF strip, original+card)"
```

---

### Task 3: Feed-pagina met post-formulier (`/homelabs`)

**Files:**
- Create: `app/Livewire/Homelab/Feed.php`
- Create: `resources/views/livewire/homelab/feed.blade.php`
- Modify: `routes/web.php` (na de statische pages-routes, regel ~36)
- Modify: `resources/views/components/marketing/footer.blade.php` (link in kolom "Links")
- Test: `tests/Feature/Homelab/HomelabFeedTest.php`

**Interfaces:**
- Consumes: `HomelabPost` (Task 1), `StoreHomelabPhotoJob` (Task 2).
- Produces: route `GET /homelabs` → `App\Livewire\Homelab\Feed` (naam `homelabs`); Livewire-props `photo`, `body`; actions `submit()`, `loadMore()`. Publieke feed toont alleen published posts, 12 per pagina.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('shows the feed to guests but not the post form', function () {
    HomelabPost::factory()->create(['body' => 'Mijn rack met drie NUCs']);

    $this->get('/homelabs')
        ->assertOk()
        ->assertSee('Mijn rack met drie NUCs')
        ->assertSee('Log in om jouw lab te tonen');
});

it('hides removed posts', function () {
    HomelabPost::factory()->removed()->create(['body' => 'weggemodereerde post']);

    $this->get('/homelabs')->assertOk()->assertDontSee('weggemodereerde post');
});

it('lets a logged-in user post a photo with body', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $upload = UploadedFile::fake()->createWithContent('lab.jpg', $bytes);

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', $upload)
        ->set('body', 'R730 + Unifi-switch, alles op 10G')
        ->call('submit')
        ->assertHasNoErrors();

    expect(HomelabPost::query()->count())->toBe(1)
        ->and(HomelabPost::query()->first()->user_id)->toBe($user->id);
});

it('validates photo required and body length', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('body', str_repeat('a', 501))
        ->call('submit')
        ->assertHasErrors(['photo', 'body']);
});

it('rate limits to one post per 24h per account', function () {
    $user = User::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', UploadedFile::fake()->createWithContent('a.jpg', $bytes))
        ->set('body', 'eerste post')
        ->call('submit')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->set('photo', UploadedFile::fake()->createWithContent('b.jpg', $bytes))
        ->set('body', 'tweede post te snel')
        ->call('submit')
        ->assertHasErrors(['body']);

    expect(HomelabPost::query()->count())->toBe(1);
});

it('404s when the feature flag is off', function () {
    config()->set('cloudmarktplaats.features.homelab_feed', false);

    $this->get('/homelabs')->assertNotFound();
});
```

NB: `RateLimiter` import blijft staan voor evt. `RateLimiter::clear()`-debughulp; laat Pint hem verwijderen als ongebruikt.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabFeedTest`
Expected: FAIL — route/component bestaan niet.

- [ ] **Step 3: Component, view, route, footerlink**

`app/Livewire/Homelab/Feed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Publieke homelab-feed + post-formulier voor ingelogde gebruikers.
 *
 * Anonimiteitscontract: deze component (en zijn view) rendert nooit
 * iets dat de poster identificeert. Zie de spec.
 */
#[Layout('components.layouts.marketing', ['title' => 'Uit de homelabs — Cloudmarktplaats'])]
class Feed extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $photo = null;

    public string $body = '';

    public int $perPage = 12;

    public int $page = 1;

    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);
    }

    public function submit(): void
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'body' => ['required', 'string', 'max:500'],
        ]);

        $userId = (int) auth()->id();
        $key = "homelab-post:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            $this->addError('body', 'Eén post per dag — probeer het morgen weer.');

            return;
        }

        $post = HomelabPost::query()->create([
            'user_id' => $userId,
            'body' => $this->body,
            'photo_path' => 'pending',
        ]);

        (new StoreHomelabPhotoJob(
            $post->id,
            (string) file_get_contents((string) $this->photo->getRealPath()),
            (string) $this->photo->getMimeType(),
        ))->handle();

        RateLimiter::hit($key, decaySeconds: 86400);

        $this->reset('photo', 'body');
        session()->flash('homelab-status', 'Je lab staat erop. Anoniem, zoals beloofd.');
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        return HomelabPost::query()
            ->published()
            ->orderByDesc('created_at')
            ->limit($this->perPage * $this->page)
            ->get();
    }

    public function render(): View
    {
        $posts = $this->posts();

        return view('livewire.homelab.feed', [
            'posts' => $posts,
            'hasMore' => HomelabPost::query()->published()->count() > $posts->count(),
        ]);
    }
}
```

`resources/views/livewire/homelab/feed.blade.php`:

```blade
<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Uit de homelabs</div>
    <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">Laat je lab zien.</h1>
    <p class="mt-4 max-w-xl text-sm text-cmp-muted">
        Eén foto, een korte beschrijving, volledig anoniem. Geen naam, geen profiel —
        alleen het rack. EXIF wordt gestript, zoals altijd.
    </p>

    @auth
        <form wire:submit="submit" class="mt-8 max-w-xl space-y-3 rounded-sm border border-cmp-border bg-cmp-surface p-5">
            @if (session('homelab-status'))
                <p class="font-mono text-sm text-cmp-signal">{{ session('homelab-status') }}</p>
            @endif

            <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp"
                   class="w-full text-sm file:mr-3 file:cmp-btn file:cmp-btn-secondary file:cursor-pointer">
            @error('photo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <textarea wire:model="body" rows="3" maxlength="500"
                      placeholder="Wat draait er, en waarom?"
                      class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal"></textarea>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] text-cmp-faint">max 500 tekens · 1 post per dag · anoniem</span>
                <button class="cmp-btn cmp-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Post je lab</span>
                    <span wire:loading>Uploaden…</span>
                </button>
            </div>
        </form>
    @else
        <div class="mt-8 max-w-xl rounded-sm border border-dashed border-cmp-border bg-cmp-surface p-5">
            <p class="text-sm text-cmp-muted">
                <a href="{{ route('login') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">Log in om jouw lab te tonen</a>
                — de feed toont nooit wie je bent.
            </p>
        </div>
    @endauth

    @if ($posts->isEmpty())
        <div class="mt-12 rounded-sm border border-dashed border-cmp-border bg-cmp-surface px-6 py-16 text-center">
            <p class="font-display text-xl font-bold">Nog geen labs. Die van jou kan de eerste zijn.</p>
        </div>
    @else
        <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <article wire:key="homelab-{{ $post->ulid }}"
                         class="flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
                    <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                        <img src="{{ $post->photoUrl('card') }}" alt="Homelab-foto" loading="lazy"
                             class="h-full w-full object-cover">
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <p class="text-sm text-cmp-text">{{ $post->body }}</p>
                        <div class="mt-auto flex items-center justify-between pt-1">
                            <span class="cmp-label-chip">Homelab</span>
                            <span class="font-mono text-[10px] text-cmp-faint">{{ $post->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($hasMore)
            <div x-data x-intersect.margin.400px="$wire.loadMore()" class="mt-10 flex justify-center">
                <button type="button" wire:click="loadMore" wire:loading.attr="disabled" class="cmp-btn cmp-btn-secondary">
                    <span wire:loading.remove wire:target="loadMore">Meer laden</span>
                    <span wire:loading wire:target="loadMore">Laden…</span>
                </button>
            </div>
        @endif
    @endif
</div>
```

Route in `routes/web.php`, direct na de statische pages (na de `roadmap`-regel):

```php
// Homelab-showcase: publieke feed, posten vereist login (flag-gated in mount()).
Route::get('/homelabs', \App\Livewire\Homelab\Feed::class)->name('homelabs');
```

(Gebruik een `use App\Livewire\Homelab\Feed as HomelabFeed;`-import bovenaan als het bestand elders al imports groepeert — volg de bestaande stijl.)

Footer, kolom "Links", na de "Roadmap"-regel:

```blade
                    <li><a href="{{ route('homelabs') }}" class="hover:text-cmp-text">Uit de homelabs</a></li>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabFeedTest`
Expected: 6 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add app/Livewire/Homelab/Feed.php resources/views/livewire/homelab/feed.blade.php routes/web.php resources/views/components/marketing/footer.blade.php tests/Feature/Homelab/HomelabFeedTest.php
git commit -m "Add /homelabs feed with post form, rate limit, feature flag"
```

---

### Task 4: Eigen post verwijderen + anonimiteits-tests

**Files:**
- Modify: `app/Livewire/Homelab/Feed.php`
- Modify: `resources/views/livewire/homelab/feed.blade.php`
- Test: `tests/Feature/Homelab/HomelabAnonymityTest.php`

**Interfaces:**
- Produces: Livewire-action `deleteOwn(string $ulid): void` — zet status `removed`, alleen voor de eigenaar.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use Livewire\Livewire;

it('never leaks the poster identity in the feed HTML', function () {
    $user = User::factory()->create([
        'username' => 'rackmaster9000',
        'display_name' => 'Rack Master',
    ]);
    HomelabPost::factory()->for($user)->create(['body' => 'stealth lab']);

    $this->get('/homelabs')
        ->assertOk()
        ->assertSee('stealth lab')
        ->assertDontSee('rackmaster9000')
        ->assertDontSee('Rack Master');
});

it('lets the owner remove their own post', function () {
    $user = User::factory()->create();
    $post = HomelabPost::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->call('deleteOwn', $post->ulid);

    expect($post->refresh()->status)->toBe('removed');
});

it('forbids removing someone elses post', function () {
    $post = HomelabPost::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($other)
        ->test(Feed::class)
        ->call('deleteOwn', $post->ulid)
        ->assertForbidden();

    expect($post->refresh()->status)->toBe('published');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabAnonymityTest`
Expected: eerste test slaagt mogelijk al (view toont geen namen); `deleteOwn`-tests FALEN — method bestaat niet.

- [ ] **Step 3: Implement deleteOwn + view-knop**

In `Feed.php`, na `loadMore()`:

```php
    public function deleteOwn(string $ulid): void
    {
        $post = HomelabPost::query()->where('ulid', $ulid)->firstOrFail();

        abort_unless((int) auth()->id() === $post->user_id, 403);

        $post->update(['status' => 'removed']);
    }
```

In de view, binnen de `<article>`, onder de chip-regel (alleen zichtbaar voor de eigenaar — de check op user_id gebeurt óók server-side in `deleteOwn`; dit is puur UI):

```blade
                        @auth
                            @if ($post->user_id === auth()->id())
                                <button type="button"
                                        wire:click="deleteOwn('{{ $post->ulid }}')"
                                        wire:confirm="Post verwijderen?"
                                        class="self-start font-mono text-[10px] text-cmp-muted underline hover:text-cmp-amber">
                                    Verwijder mijn post
                                </button>
                            @endif
                        @endauth
```

Let op: `$post->user_id === auth()->id()` in de view lekt níets naar anderen — de vergelijking rendert alleen een knop op je eigen post. Er mag géén andere plek zijn waar `$post->user` gerenderd wordt.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabAnonymityTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add app/Livewire/Homelab/Feed.php resources/views/livewire/homelab/feed.blade.php tests/Feature/Homelab/HomelabAnonymityTest.php
git commit -m "Homelab feed: own-post delete + anonymity regression tests"
```

---

### Task 5: Homepage-sectie "Uit de homelabs"

**Files:**
- Create: `app/Livewire/Homelab/Recent.php`
- Create: `resources/views/livewire/homelab/recent.blade.php`
- Modify: `resources/views/pages/home.blade.php` (tussen recent-listings-sectie en principes-sectie)
- Test: `tests/Feature/Homelab/HomelabHomeSectionTest.php`

**Interfaces:**
- Consumes: `HomelabPost::published()`, `photoUrl()` (Task 1).
- Produces: `<livewire:homelab.recent :limit="3" />`; rendert niets (lege string) bij 0 posts of flag uit.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\User;

it('shows the latest homelab posts on the homepage', function () {
    HomelabPost::factory()->create(['body' => 'thuisserver hoekje']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Uit de homelabs')
        ->assertSee('thuisserver hoekje');
});

it('hides the section entirely when there are no posts', function () {
    $this->get('/')->assertOk()->assertDontSee('Uit de homelabs');
});

it('hides the section when the flag is off', function () {
    config()->set('cloudmarktplaats.features.homelab_feed', false);
    HomelabPost::factory()->create();

    $this->get('/')->assertOk()->assertDontSee('Uit de homelabs');
});

it('does not leak identity on the homepage either', function () {
    $user = User::factory()->create(['username' => 'rackmaster9000']);
    HomelabPost::factory()->for($user)->create();

    $this->get('/')->assertOk()->assertDontSee('rackmaster9000');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabHomeSectionTest`
Expected: FAIL — sectie bestaat niet.

- [ ] **Step 3: Component, view, home-sectie**

`app/Livewire/Homelab/Recent.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;

class Recent extends Component
{
    public int $limit = 3;

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        if (! config('cloudmarktplaats.features.homelab_feed')) {
            return new Collection;
        }

        return HomelabPost::query()
            ->published()
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.homelab.recent', ['posts' => $this->posts()]);
    }
}
```

`resources/views/livewire/homelab/recent.blade.php`:

```blade
<div>
    @if ($posts->isNotEmpty())
        <section aria-labelledby="homelab-heading">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <div class="cmp-section-label mb-3">Community</div>
                    <h2 id="homelab-heading" class="text-2xl font-bold tracking-display-tight">Uit de homelabs</h2>
                </div>
                <a href="{{ route('homelabs') }}" class="hidden sm:inline text-sm text-cmp-muted hover:text-cmp-ink">
                    Alle labs →
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($posts as $post)
                    <article wire:key="homelab-recent-{{ $post->ulid }}"
                             class="flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
                        <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                            <img src="{{ $post->photoUrl('card') }}" alt="Homelab-foto" loading="lazy"
                                 class="h-full w-full object-cover">
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="line-clamp-2 text-sm text-cmp-text">{{ $post->body }}</p>
                            <div class="mt-auto flex items-center justify-between pt-1">
                                <span class="cmp-label-chip">Homelab</span>
                                <span class="font-mono text-[10px] text-cmp-faint">{{ $post->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
```

In `resources/views/pages/home.blade.php`, tussen de recent-listings-sectie en de principes-sectie:

```blade
    {{-- ========== UIT DE HOMELABS ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 pb-12">
        <livewire:homelab.recent :limit="3" />
    </section>
```

NB: Livewire-componenten renderen altijd een root-element; daarom zit de `@if` bínnen de wrapper-`div` en blijft de buitenste home-sectie staan (leeg = onzichtbaar, geen visuele ruimte door `pb-12` op een lege div is acceptabel; de kop verschijnt alleen mét posts).

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabHomeSectionTest`
Expected: 4 passed. Draai ook `--filter=HomepageTest` — de bestaande homepage-tests mogen niet breken.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add app/Livewire/Homelab/Recent.php resources/views/livewire/homelab/recent.blade.php resources/views/pages/home.blade.php tests/Feature/Homelab/HomelabHomeSectionTest.php
git commit -m "Show latest homelab posts on the homepage"
```

---

### Task 6: Rapporteren van homelab-posts

**Files:**
- Modify: `app/Http/Controllers/Listings/ReportController.php` (nieuwe handler; controller-docblock zegt al "more reportable types attach in later phases")
- Modify: `routes/web.php` (naast de bestaande reports-route, regel ~143)
- Modify: `resources/views/livewire/homelab/feed.blade.php` (rapporteerlink per kaart)
- Test: `tests/Feature/Homelab/HomelabReportTest.php`

**Interfaces:**
- Consumes: `Report` (polymorf, bestaat), `HomelabPost`.
- Produces: `POST /reports/homelab/{post:ulid}` (auth, naam `reports.homelab.store`) → `ReportController::storeForHomelabPost(Request $request, HomelabPost $post)`. Zelfde reasons-enum, rate-limit en dedup als listings.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\Report;
use App\Models\User;

it('lets a user report a homelab post', function () {
    $post = HomelabPost::factory()->create();
    $reporter = User::factory()->create();

    $this->actingAs($reporter)
        ->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam'])
        ->assertRedirect();

    expect(Report::query()->where('reportable_type', $post->getMorphClass())->count())->toBe(1);
});

it('dedupes an open report from the same reporter', function () {
    $post = HomelabPost::factory()->create();
    $reporter = User::factory()->create();

    $this->actingAs($reporter)->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam']);
    $this->actingAs($reporter)->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam']);

    expect(Report::query()->count())->toBe(1);
});

it('requires login to report', function () {
    $post = HomelabPost::factory()->create();

    $this->post("/reports/homelab/{$post->ulid}", ['reason' => 'spam'])
        ->assertRedirect('/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabReportTest`
Expected: FAIL — 404 op de route.

- [ ] **Step 3: Handler + route + view-link**

Lees eerst `ReportController::storeForListing` volledig. Voeg in dezelfde controller toe (de dedup/rate-limit-logica is bewust identiek; extraheer een private helper `storeFor(Request $request, Model $reportable)` als de duplicatie na het schrijven storend blijkt — Pint/PHPStan bewaken het):

```php
    public function storeForHomelabPost(Request $request, HomelabPost $post): RedirectResponse
    {
        $userId = (int) $request->user()?->id;

        $key = "reports:user:{$userId}";
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            return back()->withErrors(['reason' => 'Te veel rapportages, probeer het later opnieuw.']);
        }
        RateLimiter::hit($key, decaySeconds: 3600);

        $data = $request->validate([
            'reason' => ['required', 'in:illegal,stolen,spam,wrong_category,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $alreadyOpen = Report::query()
            ->where('reportable_type', $post->getMorphClass())
            ->where('reportable_id', $post->id)
            ->where('reporter_user_id', $userId)
            ->where('status', 'open')
            ->exists();
        if ($alreadyOpen) {
            return back()->with('status', 'Je hebt deze post al gerapporteerd; onze moderators bekijken het.');
        }

        Report::query()->create([
            'reportable_type' => $post->getMorphClass(),
            'reportable_id' => $post->id,
            'reporter_user_id' => $userId,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);

        return back()->with('status', 'Bedankt — onze moderators bekijken dit zo snel mogelijk.');
    }
```

Import bovenaan de controller: `use App\Models\HomelabPost;`.

Route in `routes/web.php`, direct onder de bestaande `reports.listing.store`-route, met dezelfde middleware (kopieer de middleware-chain van die route exact):

```php
Route::post('/reports/homelab/{post:ulid}', [ReportController::class, 'storeForHomelabPost'])
    ->middleware('auth')
    ->name('reports.homelab.store');
```

(Controleer of de listing-variant méér middleware draagt — bijv. verified — en spiegel dat.)

View: in `feed.blade.php`, in de kaart-footer naast de tijd, een minimaal rapporteer-formulier (geen aparte pagina — reason vast op `other` houden is te mager; gebruik een `<details>` met een select, consistent met wat de detailpagina van listings doet — kopieer het formulier-patroon van `resources/views/livewire/listings/detail.blade.php` rond de rapporteerlink en versimpel):

```blade
                        @auth
                            <details class="mt-1">
                                <summary class="cursor-pointer font-mono text-[10px] text-cmp-faint hover:text-cmp-amber">Rapporteer</summary>
                                <form method="POST" action="{{ route('reports.homelab.store', $post->ulid) }}" class="mt-2 flex items-center gap-2">
                                    @csrf
                                    <select name="reason" class="rounded-sm border-cmp-border text-xs focus:border-cmp-signal focus:ring-cmp-signal">
                                        <option value="illegal">Illegaal</option>
                                        <option value="spam">Spam</option>
                                        <option value="other" selected>Anders</option>
                                    </select>
                                    <button class="rounded-sm bg-cmp-ink px-2 py-1 text-[11px] text-white hover:bg-cmp-signal">Verstuur</button>
                                </form>
                            </details>
                        @endauth
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabReportTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
git add app/Http/Controllers/Listings/ReportController.php routes/web.php resources/views/livewire/homelab/feed.blade.php tests/Feature/Homelab/HomelabReportTest.php
git commit -m "Allow reporting homelab posts via the polymorphic reports flow"
```

---

### Task 7: Filament-resource (moderatie, poster alleen hier zichtbaar)

**Files:**
- Create: `app/Filament/Resources/HomelabPostResource.php`
- Create: `app/Filament/Resources/HomelabPostResource/Pages/ListHomelabPosts.php`
- Test: `tests/Feature/Admin/HomelabPostResourceTest.php`

**Interfaces:**
- Consumes: `HomelabPost`, `App\Services\Admin\AdminActionLogger::log(string $action, string $targetType, int $targetId, array $meta = [])`.
- Produces: read-only lijst met remove/restore-acties; audit-rows `homelab_post.remove` / `homelab_post.restore`.

Volg de structuur van `AdminActionResource` (read-only skelet) + de actie-stijl van `UserResource` (AdminActionLogger in `->action(...)` closures). Bekijk beide bestanden vóór het schrijven, en de bijbehorende `Pages/List*.php` van AdminActionResource voor het page-skelet.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\HomelabPostResource;
use App\Models\AdminAction;
use App\Models\HomelabPost;
use App\Models\User;
use Livewire\Livewire;

it('lists posts with the poster visible to admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $poster = User::factory()->create(['username' => 'rackmaster9000']);
    HomelabPost::factory()->for($poster)->create();

    Livewire::actingAs($admin)
        ->test(HomelabPostResource\Pages\ListHomelabPosts::class)
        ->assertOk()
        ->assertSee('rackmaster9000');
});

it('removes a post and writes an audit row', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $post = HomelabPost::factory()->create();

    Livewire::actingAs($admin)
        ->test(HomelabPostResource\Pages\ListHomelabPosts::class)
        ->callTableAction('remove', $post);

    expect($post->refresh()->status)->toBe('removed')
        ->and(AdminAction::query()->where('action', 'homelab_post.remove')->count())->toBe(1);
});
```

NB: bekijk hoe bestaande Filament-tests (`tests/Feature/Admin/`) admins bouwen en panels benaderen — als daar een helper of extra setup is (bijv. `Filament::setCurrentPanel`), kopieer die exact.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabPostResourceTest`
Expected: FAIL — resource bestaat niet.

- [ ] **Step 3: Resource + page**

`app/Filament/Resources/HomelabPostResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomelabPostResource\Pages;
use App\Models\HomelabPost;
use App\Services\Admin\AdminActionLogger;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Moderatie van homelab-posts. Dit is de ENIGE plek waar de poster
 * zichtbaar is — publiek is de feed volledig anoniem.
 */
class HomelabPostResource extends Resource
{
    protected static ?string $model = HomelabPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Moderatie';

    protected static ?string $modelLabel = 'Homelab-post';

    protected static ?string $pluralModelLabel = 'Homelab-posts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->state(fn (HomelabPost $record): string => $record->photoUrl('card'))
                    ->square(),
                Tables\Columns\TextColumn::make('body')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Poster (intern!)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['published' => 'published', 'removed' => 'removed']),
            ])
            ->actions([
                Tables\Actions\Action::make('remove')
                    ->visible(fn (HomelabPost $record): bool => $record->status === 'published')
                    ->requiresConfirmation()
                    ->action(function (HomelabPost $record): void {
                        $record->update(['status' => 'removed']);
                        AdminActionLogger::log('homelab_post.remove', 'homelab_post', $record->id);
                    }),
                Tables\Actions\Action::make('restore')
                    ->visible(fn (HomelabPost $record): bool => $record->status === 'removed')
                    ->requiresConfirmation()
                    ->action(function (HomelabPost $record): void {
                        $record->update(['status' => 'published']);
                        AdminActionLogger::log('homelab_post.restore', 'homelab_post', $record->id);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomelabPosts::route('/'),
        ];
    }
}
```

`app/Filament/Resources/HomelabPostResource/Pages/ListHomelabPosts.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomelabPostResource\Pages;

use App\Filament\Resources\HomelabPostResource;
use Filament\Resources\Pages\ListRecords;

class ListHomelabPosts extends ListRecords
{
    protected static string $resource = HomelabPostResource::class;
}
```

Controleer de exacte `AdminActionLogger::log()`-signatuur in `app/Services/Admin/AdminActionLogger.php` (regel ~37) en de kolomnamen van `AdminAction` — pas de aanroep aan als de helper andere parameters heeft dan hier aangenomen.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabPostResourceTest`
Expected: 2 passed.

- [ ] **Step 5: Pint + PHPStan + volledige suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse
docker compose exec -T php-fpm php artisan test
git add app/Filament/Resources/HomelabPostResource.php app/Filament/Resources/HomelabPostResource/Pages/ListHomelabPosts.php tests/Feature/Admin/HomelabPostResourceTest.php
git commit -m "Add Filament moderation for homelab posts with audit logging"
```

Expected: volledige suite groen (161 bestaand + ~21 nieuw).

---

### Task 8: Deploy

Geen TDD — ops-checklist (patroon uit memory/dit project):

- [ ] `npm run build` lokaal.
- [ ] `git push origin main`.
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/` — let op `--exclude bootstrap/cache` (dev-cache breekt prod: "Pail not found").
- [ ] Op CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan migrate --force && php artisan package:discover && php artisan view:clear && php artisan view:cache && php artisan config:cache && php artisan route:cache'`.
- [ ] Verifieer: `curl -s -o /dev/null -w '%{http_code}' https://cloudmarktplaats.nl/homelabs` → 200; homepage 200; post een testpost als `nick` en verwijder hem weer.
