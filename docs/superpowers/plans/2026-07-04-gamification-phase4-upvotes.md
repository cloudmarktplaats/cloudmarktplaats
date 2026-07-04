# Gamification Phase 4 — Homelab Upvotes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upvote-only appreciation on homelab posts — logged-in users can upvote (and un-upvote) a post; the post owner earns karma per upvote; counts are public but voters and posters stay anonymous. No downvotes, no ranking, no voter identity.

**Architecture:** A `homelab_post_upvotes` join table (unique per user+post). An `UpvoteService` toggles a vote and feeds the post owner's karma via the existing append-only `KarmaService` (award on a genuinely new vote, compensating reversal on removal). The homelab feed gains a toggle action + count; the homepage widget shows the count read-only. All behind `FEATURE_HOMELAB_UPVOTES`.

**Tech Stack:** Laravel 11, Livewire 3, Postgres, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-gamification-design.md` — Phase 4 (upvote-only appreciation, fed back into karma).
- **Anti-toxicity (the whole point):** upvote-ONLY. No downvote anywhere. The voter's identity is never shown (homelab posts are already anonymous; votes are too). No post ranking / leaderboard / "most upvoted" sort in this phase — just a count and the karma feedback.
- **Anti-abuse (hostile users):** one upvote per (user, post) enforced by a UNIQUE DB constraint. Self-upvote is blocked (an owner can't upvote their own post to farm karma). Banned users cannot cast new votes. Voting is rate-limited (60 toggles/user/hour) to bound karma-ledger churn from toggle-spam. Upvote karma feeds only the vanity karma number + the `pillar` badge — it does NOT feed trust/moderation-skip (that is sales-only, unchanged).
- **Karma:** on a genuinely new upvote, award the post owner `+1` karma (`KarmaService::award(owner, 'homelab_upvote', 1, $post)`). On removal of an existing upvote, write a compensating `-1` (`award(owner, 'homelab_upvote_reversal', -1, $post)`). Award only when a vote is actually created; reverse only when actually deleted (so they balance). Never award for a self-upvote (blocked before it reaches karma).
- Feature flag: `config('cloudmarktplaats.features.homelab_upvotes')`, env `FEATURE_HOMELAB_UPVOTES`, default `true`. Gates the vote action + the button.
- Privacy: the upvote table has `user_id`, but it is never rendered publicly (no "upvoted by X"); only the aggregate count is shown.
- Design per `docs/DESIGN.md`: rounded-sm, cmp-tokens, font-mono for the count, an upvote affordance consistent with the card style (e.g. a small outline button with a ▲/heart-free mono label — keep it dry/technical, e.g. "▲ 12" or "waarderen · 12").
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 green. **Run `./vendor/bin/pint --test` before each commit (CI lint gate).** Tests Pest under `tests/Feature/Gamification/`. Docker as before. Full suite currently 259 green. `withoutVite()` global.

---

### Task 1: Migration, model, factory, relation, flag

**Files:**
- Create: `database/migrations/2026_07_04_000500_create_homelab_post_upvotes_table.php`
- Create: `app/Models/HomelabPostUpvote.php`
- Create: `database/factories/HomelabPostUpvoteFactory.php`
- Modify: `app/Models/HomelabPost.php` (upvotes relation)
- Modify: `config/cloudmarktplaats.php` (features.homelab_upvotes)
- Modify: `.env.example` (FEATURE_HOMELAB_UPVOTES)
- Test: `tests/Feature/Gamification/HomelabUpvoteModelTest.php`

**Interfaces:**
- Produces `homelab_post_upvotes` table: `id`, `user_id` (fk users cascade), `homelab_post_id` (fk homelab_posts cascade), `timestamps`, UNIQUE `(user_id, homelab_post_id)`.
- Produces `App\Models\HomelabPostUpvote` (fillable `user_id`, `homelab_post_id`; relations `user()`, `post()`).
- Produces on `HomelabPost`: `upvotes(): HasMany` (to HomelabPostUpvote).
- Produces `HomelabPostUpvoteFactory`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Database\QueryException;

it('records an upvote and enforces one per user per post', function () {
    $post = HomelabPost::factory()->create();
    $user = User::factory()->create();

    HomelabPostUpvote::factory()->create(['user_id' => $user->id, 'homelab_post_id' => $post->id]);

    expect($post->upvotes()->count())->toBe(1);

    // Second upvote by the same user on the same post violates the unique index.
    expect(fn () => HomelabPostUpvote::factory()->create(['user_id' => $user->id, 'homelab_post_id' => $post->id]))
        ->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabUpvoteModelTest`
Expected: FAIL — table/model missing.

- [ ] **Step 3: Write migration, model, factory, relation, flag**

`database/migrations/2026_07_04_000500_create_homelab_post_upvotes_table.php`:

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
        Schema::create('homelab_post_upvotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'homelab_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_post_upvotes');
    }
};
```

`app/Models/HomelabPostUpvote.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomelabPostUpvoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomelabPostUpvote extends Model
{
    /** @use HasFactory<HomelabPostUpvoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'homelab_post_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<HomelabPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(HomelabPost::class, 'homelab_post_id');
    }
}
```

`database/factories/HomelabPostUpvoteFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPostUpvote> */
class HomelabPostUpvoteFactory extends Factory
{
    protected $model = HomelabPostUpvote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'homelab_post_id' => HomelabPost::factory(),
        ];
    }
}
```

On `app/Models/HomelabPost.php`, add a relation (mirror the existing relation style; add `use Illuminate\Database\Eloquent\Relations\HasMany;` if not present):

```php
    /** @return HasMany<HomelabPostUpvote, $this> */
    public function upvotes(): HasMany
    {
        return $this->hasMany(HomelabPostUpvote::class);
    }
```

`config/cloudmarktplaats.php` — add to `features` after `deals`:

```php
        'homelab_upvotes' => env('FEATURE_HOMELAB_UPVOTES', true),
```

`.env.example` after `FEATURE_DEALS=true`:

```
FEATURE_HOMELAB_UPVOTES=true
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=HomelabUpvoteModelTest`
Expected: 1 passed.

- [ ] **Step 5: Pint --test + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/migrations/2026_07_04_000500_create_homelab_post_upvotes_table.php app/Models/HomelabPostUpvote.php database/factories/HomelabPostUpvoteFactory.php app/Models/HomelabPost.php config/cloudmarktplaats.php .env.example tests/Feature/Gamification/HomelabUpvoteModelTest.php
git commit -m "Add homelab_post_upvotes table + model (unique per user+post)"
```

---

### Task 2: UpvoteService (toggle + karma feedback, abuse-guarded)

**Files:**
- Create: `app/Services/Gamification/UpvoteService.php`
- Test: `tests/Feature/Gamification/UpvoteServiceTest.php`

**Interfaces:**
- Consumes: `HomelabPost`, `HomelabPostUpvote`, `User`, `KarmaService::award`.
- Produces `App\Services\Gamification\UpvoteService`:
  - `toggle(HomelabPost $post, User $voter): bool` — if the voter has no upvote on the post, create one and award the owner +1 `homelab_upvote` karma → returns `true` (now upvoted); if they already have one, delete it and write a `-1` `homelab_upvote_reversal` → returns `false`. Throws `App\Exceptions\UpvoteException` on self-upvote (voter is the post owner) or a banned voter.
  - `hasUpvoted(HomelabPost $post, User $voter): bool`.
  - `countFor(HomelabPost $post): int`.
- Produces `App\Exceptions\UpvoteException extends \RuntimeException`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Exceptions\UpvoteException;
use App\Models\HomelabPost;
use App\Models\User;
use App\Services\Gamification\KarmaService;
use App\Services\Gamification\UpvoteService;

it('toggles an upvote and moves the owner\'s karma', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();
    $svc = app(UpvoteService::class);

    expect($svc->toggle($post, $voter))->toBeTrue()
        ->and($svc->countFor($post))->toBe(1)
        ->and($svc->hasUpvoted($post, $voter))->toBeTrue()
        ->and(app(KarmaService::class)->karmaFor($owner))->toBe(1);

    // toggle off
    expect($svc->toggle($post, $voter))->toBeFalse()
        ->and($svc->countFor($post))->toBe(0)
        ->and(app(KarmaService::class)->karmaFor($owner))->toBe(0);
});

it('blocks a self-upvote (no karma farming)', function () {
    $owner = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();

    expect(fn () => app(UpvoteService::class)->toggle($post, $owner))->toThrow(UpvoteException::class);
    expect(app(KarmaService::class)->karmaFor($owner))->toBe(0);
});

it('blocks a banned voter', function () {
    $post = HomelabPost::factory()->create();
    $banned = User::factory()->create(['is_banned' => true]);

    expect(fn () => app(UpvoteService::class)->toggle($post, $banned))->toThrow(UpvoteException::class);
});

it('counts one upvote per user even across voters', function () {
    $post = HomelabPost::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $svc = app(UpvoteService::class);

    $svc->toggle($post, $a);
    $svc->toggle($post, $b);

    expect($svc->countFor($post))->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=UpvoteServiceTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the exception + service**

`app/Exceptions/UpvoteException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UpvoteException extends RuntimeException {}
```

`app/Services/Gamification/UpvoteService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\UpvoteException;
use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpvoteService
{
    public function __construct(private readonly KarmaService $karma) {}

    public function toggle(HomelabPost $post, User $voter): bool
    {
        if ($voter->is_banned) {
            throw new UpvoteException('Geblokkeerde accounts kunnen niet waarderen.');
        }
        if ($voter->id === $post->user_id) {
            throw new UpvoteException('Je kunt je eigen post niet waarderen.');
        }

        return DB::transaction(function () use ($post, $voter): bool {
            $existing = HomelabPostUpvote::query()
                ->where('homelab_post_id', $post->id)
                ->where('user_id', $voter->id)
                ->lockForUpdate()
                ->first();

            $owner = $post->user;

            if ($existing !== null) {
                $existing->delete();
                if ($owner instanceof User) {
                    $this->karma->award($owner, 'homelab_upvote_reversal', -1, $post);
                }

                return false;
            }

            HomelabPostUpvote::query()->create([
                'homelab_post_id' => $post->id,
                'user_id' => $voter->id,
            ]);
            if ($owner instanceof User) {
                $this->karma->award($owner, 'homelab_upvote', 1, $post);
            }

            return true;
        });
    }

    public function hasUpvoted(HomelabPost $post, User $voter): bool
    {
        return HomelabPostUpvote::query()
            ->where('homelab_post_id', $post->id)
            ->where('user_id', $voter->id)
            ->exists();
    }

    public function countFor(HomelabPost $post): int
    {
        return HomelabPostUpvote::query()->where('homelab_post_id', $post->id)->count();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=UpvoteServiceTest`
Expected: 4 passed.

- [ ] **Step 5: Pint --test + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Exceptions/UpvoteException.php app/Services/Gamification/UpvoteService.php tests/Feature/Gamification/UpvoteServiceTest.php
git commit -m "Add UpvoteService: toggle upvote + owner karma, self/banned guarded"
```

---

### Task 3: Upvote button + count on the homelab feed (+ read-only count on homepage)

**Files:**
- Modify: `app/Livewire/Homelab/Feed.php` (upvote action + rate limit + per-post upvote state)
- Modify: `resources/views/livewire/homelab/feed.blade.php` (upvote button + count)
- Modify: `app/Livewire/Homelab/Recent.php` (expose counts)
- Modify: `resources/views/livewire/homelab/recent.blade.php` (read-only count)
- Test: `tests/Feature/Gamification/UpvoteUiTest.php`

**Interfaces:**
- Consumes: `UpvoteService` (Task 2), flag `features.homelab_upvotes`.
- Produces: Feed `upvote(string $ulid)` Livewire action (auth-only → 403 for guests; flag-gated → no-op/hidden when off; rate-limited 60/user/hour), catching `UpvoteException` into a flash/error. The feed cards show the count and (for logged-in users) a toggle button reflecting whether the viewer upvoted. Guests see the count and a "log in om te waarderen" link. The homepage `Recent` widget shows the count read-only (no button).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use App\Services\Gamification\UpvoteService;
use Livewire\Livewire;

it('lets a logged-in user upvote a post from the feed', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();

    Livewire::actingAs($voter)
        ->test(Feed::class)
        ->call('upvote', $post->ulid)
        ->assertHasNoErrors();

    expect(app(UpvoteService::class)->countFor($post))->toBe(1);
});

it('forbids a guest from upvoting', function () {
    $post = HomelabPost::factory()->create();

    Livewire::test(Feed::class)
        ->call('upvote', $post->ulid)
        ->assertForbidden();
});

it('shows the upvote count on the feed', function () {
    $post = HomelabPost::factory()->create(['body' => 'stealth lab']);
    HomelabPost::factory()->create(); // noise
    app(UpvoteService::class); // ensure container ok
    \App\Models\HomelabPostUpvote::factory()->count(3)->create(['homelab_post_id' => $post->id]);

    $this->get('/homelabs')->assertOk()->assertSee('3');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=UpvoteUiTest`
Expected: FAIL — no upvote action.

- [ ] **Step 3: Implement the action + view wiring**

In `app/Livewire/Homelab/Feed.php`, add the action (mirror the `deleteOwn` guard style; import `UpvoteException`, `UpvoteService`, `HomelabPost`, `RateLimiter`):

```php
    public function upvote(string $ulid): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_upvotes'), 404);
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $key = 'homelab-upvote:'.$user->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 60)) {
            $this->addError('upvote', 'Rustig aan met waarderen.');

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 3600);

        $post = HomelabPost::query()->where('ulid', $ulid)->firstOrFail();
        try {
            app(UpvoteService::class)->toggle($post, $user);
        } catch (UpvoteException $e) {
            $this->addError('upvote', $e->getMessage());
        }
    }
```

The `posts()`/`render()` must expose, per post, the upvote count and whether the current user upvoted. Simplest: in `render()`, after fetching `$posts`, eager-load `withCount('upvotes')` and (if logged in) the set of upvoted post ids. Adjust `posts()` to `->withCount('upvotes')`, and in `render()` compute:

```php
        $upvotedIds = auth()->check()
            ? \App\Models\HomelabPostUpvote::query()
                ->where('user_id', (int) auth()->id())
                ->whereIn('homelab_post_id', $posts->pluck('id'))
                ->pluck('homelab_post_id')->all()
            : [];
```

and pass `$upvotedIds` to the view (as an array; use `in_array($post->id, $upvotedIds, true)`).

In `resources/views/livewire/homelab/feed.blade.php`, in the card footer (near the `cmp-label-chip`/time row), add — only when the flag is on:

```blade
                            @if (config('cloudmarktplaats.features.homelab_upvotes'))
                                <div class="flex items-center gap-2">
                                    @auth
                                        <button type="button" wire:click="upvote('{{ $post->ulid }}')"
                                                @class([
                                                    'inline-flex items-center gap-1 rounded-sm border px-2 py-0.5 font-mono text-[11px] transition-colors',
                                                    'border-cmp-signal text-cmp-signal' => in_array($post->id, $upvotedIds, true),
                                                    'border-cmp-border text-cmp-muted hover:border-cmp-ink hover:text-cmp-ink' => ! in_array($post->id, $upvotedIds, true),
                                                ])>
                                            ▲ {{ $post->upvotes_count }}
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 font-mono text-[11px] text-cmp-faint">▲ {{ $post->upvotes_count }}</span>
                                    @endauth
                                </div>
                            @endif
```

(Place it so it doesn't disrupt the existing chip/time/delete layout — put the upvote control on the same footer row.)

In `app/Livewire/Homelab/Recent.php`, add `->withCount('upvotes')` to the posts query. In `resources/views/livewire/homelab/recent.blade.php`, show the count read-only next to the chip (only when the flag is on):

```blade
                            @if (config('cloudmarktplaats.features.homelab_upvotes'))
                                <span class="font-mono text-[10px] text-cmp-faint">▲ {{ $post->upvotes_count }}</span>
                            @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=UpvoteUiTest`
Expected: 3 passed. Also run `--filter="HomelabFeed|HomelabHomeSection|HomelabAnonymity"` (existing homelab behavior + the anonymity guarantee stay green — the upvote UI must not render voter identity).

- [ ] **Step 5: Pint --test + PHPStan + full suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T php-fpm php artisan test
git add app/Livewire/Homelab/Feed.php resources/views/livewire/homelab/feed.blade.php app/Livewire/Homelab/Recent.php resources/views/livewire/homelab/recent.blade.php tests/Feature/Gamification/UpvoteUiTest.php
git commit -m "Homelab feed: upvote-only appreciation with count, rate-limited"
```

Expected: full suite green (259 existing + ~8 new).

---

### Task 4: Deploy

Ops checklist (CT at 192.168.178.215, /opt/cloudmarktplaats):

- [ ] `npm run build` locally (homelab views changed).
- [ ] `./vendor/bin/pint --test` locally MUST be clean (CI lint gate).
- [ ] Merge to `main`, `git push origin main`, confirm CI green.
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache --exclude .superpowers /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/`
- [ ] On the CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:clear && php artisan view:cache'` (this phase HAS a migration — the upvotes table).
- [ ] Verify: on /homelabs, a logged-in user sees an upvote control on each card; upvoting increments the count and toggles off on a second click; a guest sees the count read-only; the homepage widget shows counts. Confirm no voter identity is ever shown (posts stay anonymous).
