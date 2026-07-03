# Gamification Phase 2 — Stats, Badges, E-waste Counter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A private "stats for nerds" dashboard at `/profile/stats`, a derived badge engine (achievements computed from existing data, no storage), and a cooperative platform-wide "devices rescued from e-waste" counter on the public homepage.

**Architecture:** A `StatsService` computes a user's numbers from existing models (listings, homelab posts, karma, invite activations) plus a cached platform-wide rescued count. A pure `BadgeService` derives earned badges from a stats array (no badges table — YAGNI). A flag-gated Livewire `/profile/stats` page renders both in datasheet/monitoring style. A small Livewire widget shows the cooperative counter on the homepage. All behind `FEATURE_STATS`.

**Tech Stack:** Laravel 11, Livewire 3, Redis cache, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-gamification-design.md`. This is Phase 2 (stats-dashboard + badges + cooperative e-waste counter). The opt-in **public trading card** from the spec's "stats voor nerds" section is DEFERRED (out of scope here — reduces privacy surface); note it, don't build it.
- **Anti-toxicity / privacy:** the personal dashboard shows ONLY the authenticated user's own stats — no leaderboard, no comparison, no other users' numbers. Badges are earned, never ranked. The cooperative counter is a single shared number for everyone (not per-user), which is the point.
- **Build only on data that exists.** No kg/weight, no response-time, no "oldest hardware" (no such fields). "Devices rescued" = count of listings in state `sold` platform-wide. Per-user: published count, sold count, homelab-post count, karma, people-activated (invitees who earned you karma), member-since.
- Derived badges (pure function of the stats array), recomputed on render — no badge table, no award events, no cron.
- Feature flag: `config('cloudmarktplaats.features.stats')`, env `FEATURE_STATS`, default `true`. The `/profile/stats` route + the homepage counter respect it.
- The cooperative counter is cached in Redis (`Cache::remember`) with a 300s TTL to avoid a COUNT on every homepage hit.
- Design per `docs/DESIGN.md`: rounded-sm, cmp-tokens, `font-mono` for numbers, monitoring/datasheet feel (stat tiles like the inventory-label aesthetic). Match the existing `resources/views/livewire/profile/invites.blade.php` stat-tile pattern.
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 green. Tests are Pest feature tests under `tests/Feature/Gamification/`. Run in Docker: `docker compose exec -T php-fpm php artisan test --filter=<Name>`; Pint `./vendor/bin/pint --dirty`; PHPStan `./vendor/bin/phpstan analyse --memory-limit=512M`. `withoutVite()` is global in TestCase.

---

### Task 1: StatsService + sold() factory state + FEATURE_STATS flag

**Files:**
- Create: `app/Services/Gamification/StatsService.php`
- Modify: `database/factories/ListingFactory.php` (add `sold()` state)
- Modify: `config/cloudmarktplaats.php` (features.stats)
- Modify: `.env.example` (FEATURE_STATS)
- Test: `tests/Feature/Gamification/StatsServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Listing` (state enum incl. `published`/`sold`, `user()`), `App\Models\HomelabPost` (scopePublished), `App\Models\KarmaEvent`, `App\Models\User` (`karma` accessor).
- Produces `App\Services\Gamification\StatsService`:
  - `forUser(User $user): array` returning exactly these keys: `member_since` (Carbon, = user->created_at), `listings_published` (int, count state=published), `listings_sold` (int, count state=sold), `homelab_posts` (int, published homelab posts by user), `karma` (int), `people_activated` (int, count of karma_events where user_id=user AND type='invite_activation').
  - `rescuedCount(): int` — cached (`Cache::remember('stats:rescued', 300, ...)`) count of listings in state `sold` platform-wide.
- Produces `ListingFactory::sold()` state (`['state' => 'sold', 'published_at' => now()]`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\StatsService;

it('computes a user\'s own stats from existing data', function () {
    $user = User::factory()->create();
    Listing::factory()->published()->for($user)->count(2)->create();
    Listing::factory()->sold()->for($user)->create();
    HomelabPost::factory()->for($user)->create();
    KarmaEvent::factory()->for($user)->create(['type' => 'invite_activation', 'points' => 10]);
    KarmaEvent::factory()->for($user)->create(['type' => 'invite_activation', 'points' => 10]);

    // Another user's data must NOT leak in.
    Listing::factory()->sold()->create();

    $stats = app(StatsService::class)->forUser($user);

    expect($stats['listings_published'])->toBe(2)
        ->and($stats['listings_sold'])->toBe(1)
        ->and($stats['homelab_posts'])->toBe(1)
        ->and($stats['karma'])->toBe(20)
        ->and($stats['people_activated'])->toBe(2)
        ->and($stats['member_since']->timestamp)->toBe($user->created_at->timestamp);
});

it('counts platform-wide rescued (sold) listings', function () {
    Listing::factory()->sold()->count(3)->create();
    Listing::factory()->published()->create();

    expect(app(StatsService::class)->rescuedCount())->toBe(3);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=StatsServiceTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service, factory state, config, flag**

`app/Services/Gamification/StatsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\HomelabPost;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class StatsService
{
    /**
     * A user's own stats. Never includes anyone else's data.
     *
     * @return array{member_since: \Illuminate\Support\Carbon, listings_published: int, listings_sold: int, homelab_posts: int, karma: int, people_activated: int}
     */
    public function forUser(User $user): array
    {
        return [
            'member_since' => $user->created_at,
            'listings_published' => Listing::query()->where('user_id', $user->id)->where('state', 'published')->count(),
            'listings_sold' => Listing::query()->where('user_id', $user->id)->where('state', 'sold')->count(),
            'homelab_posts' => HomelabPost::query()->where('user_id', $user->id)->published()->count(),
            'karma' => $user->karma,
            'people_activated' => KarmaEvent::query()
                ->where('user_id', $user->id)
                ->where('type', 'invite_activation')
                ->count(),
        ];
    }

    /**
     * Platform-wide cooperative counter: devices given a second life.
     * Cached to keep the public homepage cheap.
     */
    public function rescuedCount(): int
    {
        return (int) Cache::remember(
            'stats:rescued',
            300,
            fn (): int => Listing::query()->where('state', 'sold')->count(),
        );
    }
}
```

In `database/factories/ListingFactory.php`, add after the `published()` method:

```php
    public function sold(): static
    {
        return $this->state(fn () => ['state' => 'sold', 'published_at' => now()]);
    }
```

`config/cloudmarktplaats.php` — add to the `features` array after `invites`:

```php
        'stats' => env('FEATURE_STATS', true),
```

`.env.example` after `FEATURE_INVITES=true`:

```
FEATURE_STATS=true
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=StatsServiceTest`
Expected: 2 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Gamification/StatsService.php database/factories/ListingFactory.php config/cloudmarktplaats.php .env.example tests/Feature/Gamification/StatsServiceTest.php
git commit -m "Add StatsService: per-user stats + cached rescued counter, FEATURE_STATS"
```

---

### Task 2: BadgeService (derived achievements)

**Files:**
- Create: `app/Services/Gamification/BadgeService.php`
- Test: `tests/Feature/Gamification/BadgeServiceTest.php`

**Interfaces:**
- Consumes: the stats array shape from `StatsService::forUser` (Task 1).
- Produces `App\Services\Gamification\BadgeService`:
  - `earnedFor(array $stats): array` — returns a list of earned badges, each `['key' => string, 'label' => string, 'description' => string]`, computed purely from the stats array. Badge definitions (key → label, description, predicate):
    - `first_listing` — "Eerste advertentie" / "Je plaatste je eerste advertentie." — `listings_published + listings_sold >= 1`
    - `first_sale` — "Eerste verkoop" / "Je eerste stuk hardware kreeg een tweede leven." — `listings_sold >= 1`
    - `trader` — "Handelaar" / "Tien of meer verkopen." — `listings_sold >= 10`
    - `homelab_hero` — "Homelab-held" / "Je liet je lab zien." — `homelab_posts >= 1`
    - `host` — "Gastheer" / "Iemand die je uitnodigde werd actief." — `people_activated >= 1`
    - `pillar` — "Community-pilaar" / "Vijftig of meer karma." — `karma >= 50`

  Note: `veteran` (member ≥ 1 year) is intentionally omitted for Phase 2 — the platform launched in 2026-07 so nobody can earn it yet (YAGNI); add it later.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Gamification\BadgeService;

function statsWith(array $overrides = []): array
{
    return array_merge([
        'listings_published' => 0,
        'listings_sold' => 0,
        'homelab_posts' => 0,
        'karma' => 0,
        'people_activated' => 0,
    ], $overrides);
}

it('awards no badges for an empty account', function () {
    expect(app(BadgeService::class)->earnedFor(statsWith()))->toBe([]);
});

it('derives badges from stats', function () {
    $badges = app(BadgeService::class)->earnedFor(statsWith([
        'listings_published' => 1,
        'listings_sold' => 10,
        'homelab_posts' => 1,
        'karma' => 50,
        'people_activated' => 1,
    ]));

    $keys = array_column($badges, 'key');
    expect($keys)->toContain('first_listing', 'first_sale', 'trader', 'homelab_hero', 'host', 'pillar');
    // Every badge carries a label + description.
    expect($badges[0])->toHaveKeys(['key', 'label', 'description']);
});

it('does not award trader below ten sales', function () {
    $keys = array_column(app(BadgeService::class)->earnedFor(statsWith(['listings_sold' => 9])), 'key');
    expect($keys)->toContain('first_sale')->not->toContain('trader');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=BadgeServiceTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

/**
 * Derives earned achievement badges purely from a stats array
 * (see StatsService::forUser). No storage, no award events — badges are
 * recomputed on render. Earned, never ranked (anti-toxicity).
 */
class BadgeService
{
    /**
     * @param  array<string, mixed>  $stats
     * @return list<array{key: string, label: string, description: string}>
     */
    public function earnedFor(array $stats): array
    {
        $published = (int) ($stats['listings_published'] ?? 0);
        $sold = (int) ($stats['listings_sold'] ?? 0);
        $homelab = (int) ($stats['homelab_posts'] ?? 0);
        $karma = (int) ($stats['karma'] ?? 0);
        $activated = (int) ($stats['people_activated'] ?? 0);

        $definitions = [
            ['key' => 'first_listing', 'label' => 'Eerste advertentie', 'description' => 'Je plaatste je eerste advertentie.', 'earned' => ($published + $sold) >= 1],
            ['key' => 'first_sale', 'label' => 'Eerste verkoop', 'description' => 'Je eerste stuk hardware kreeg een tweede leven.', 'earned' => $sold >= 1],
            ['key' => 'trader', 'label' => 'Handelaar', 'description' => 'Tien of meer verkopen.', 'earned' => $sold >= 10],
            ['key' => 'homelab_hero', 'label' => 'Homelab-held', 'description' => 'Je liet je lab zien.', 'earned' => $homelab >= 1],
            ['key' => 'host', 'label' => 'Gastheer', 'description' => 'Iemand die je uitnodigde werd actief.', 'earned' => $activated >= 1],
            ['key' => 'pillar', 'label' => 'Community-pilaar', 'description' => 'Vijftig of meer karma.', 'earned' => $karma >= 50],
        ];

        return array_values(array_map(
            fn (array $d): array => ['key' => $d['key'], 'label' => $d['label'], 'description' => $d['description']],
            array_filter($definitions, fn (array $d): bool => $d['earned'] === true),
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=BadgeServiceTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Gamification/BadgeService.php tests/Feature/Gamification/BadgeServiceTest.php
git commit -m "Add BadgeService: derive earned achievements from stats"
```

---

### Task 3: /profile/stats dashboard page

**Files:**
- Create: `app/Livewire/Profile/Stats.php`
- Create: `resources/views/livewire/profile/stats.blade.php`
- Modify: `routes/web.php` (route, flag-gated)
- Modify: `resources/views/livewire/profile/security.blade.php` (wayfinding link, mirroring the invites link block)
- Test: `tests/Feature/Gamification/StatsPageTest.php`

**Interfaces:**
- Consumes: `StatsService::forUser` (Task 1), `BadgeService::earnedFor` (Task 2), flag `features.stats`.
- Produces: route `GET /profile/stats` (auth, flag-gated in mount → 404 when off), name `profile.stats`, Livewire `App\Livewire\Profile\Stats`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Profile\Stats;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('shows the user their own stats and earned badges', function () {
    $user = User::factory()->create();
    Listing::factory()->sold()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Stats::class)
        ->assertOk()
        ->assertSee('Eerste verkoop'); // a derived badge
});

it('only reflects the authenticated user (no other user data)', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Listing::factory()->sold()->for($other)->count(5)->create();

    Livewire::actingAs($me)
        ->test(Stats::class)
        ->assertOk()
        ->assertDontSee('Handelaar'); // 'other' would have it; I must not
});

it('404s when the stats feature is off', function () {
    config()->set('cloudmarktplaats.features.stats', false);

    Livewire::actingAs(User::factory()->create())->test(Stats::class)->assertStatus(404);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=StatsPageTest`
Expected: FAIL — component/route missing.

- [ ] **Step 3: Component, view, route, wayfinding link**

`app/Livewire/Profile/Stats.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StatsService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketing', ['title' => 'Statistieken — Cloudmarktplaats'])]
class Stats extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.stats'), 404);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $stats = app(StatsService::class)->forUser($user);

        return view('livewire.profile.stats', [
            'stats' => $stats,
            'badges' => app(BadgeService::class)->earnedFor($stats),
        ]);
    }
}
```

`resources/views/livewire/profile/stats.blade.php`:

```blade
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Jouw cijfers</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">Statistieken</h1>
    <p class="mt-3 text-sm text-cmp-muted">Alleen jij ziet deze pagina. Geen ranglijst, geen vergelijking — gewoon jouw activiteit.</p>

    <dl class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
        @php
            $tiles = [
                ['Lid sinds', $stats['member_since']->format('M Y')],
                ['Advertenties live', $stats['listings_published']],
                ['Verkocht', $stats['listings_sold']],
                ['Homelab-posts', $stats['homelab_posts']],
                ['Karma', $stats['karma']],
                ['Mensen geactiveerd', $stats['people_activated']],
            ];
        @endphp
        @foreach ($tiles as [$label, $value])
            <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
                <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">{{ $label }}</div>
                <div class="mt-1 font-mono text-2xl font-medium">{{ $value }}</div>
            </div>
        @endforeach
    </dl>

    <div class="cmp-section-label mb-3 mt-10">Badges</div>
    @if (count($badges) > 0)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($badges as $badge)
                <div class="flex items-start gap-3 rounded-sm border border-cmp-border bg-cmp-surface p-4">
                    <span class="cmp-label-chip">{{ $badge['label'] }}</span>
                    <p class="text-sm text-cmp-muted">{{ $badge['description'] }}</p>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-cmp-muted">Nog geen badges. Plaats een advertentie of laat je homelab zien om er een te verdienen.</p>
    @endif
</div>
```

Route in `routes/web.php`, next to `/profile/invites`:

```php
Route::get('/profile/stats', \App\Livewire\Profile\Stats::class)
    ->middleware('auth')
    ->name('profile.stats');
```

Wayfinding link in `resources/views/livewire/profile/security.blade.php` — add next to the invites link, wrapped in its own flag check:

```blade
        @if (config('cloudmarktplaats.features.stats'))
            <a href="{{ route('profile.stats') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">Bekijk je statistieken</a>
        @endif
```

(Match the surrounding markup/section structure of the existing invites link; if the invites link sits inside a specific block, mirror it.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=StatsPageTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Profile/Stats.php resources/views/livewire/profile/stats.blade.php routes/web.php resources/views/livewire/profile/security.blade.php tests/Feature/Gamification/StatsPageTest.php
git commit -m "Add /profile/stats: private stats dashboard + earned badges"
```

---

### Task 4: Cooperative "gered van de sloop" counter on the homepage

**Files:**
- Create: `app/Livewire/RescuedCounter.php`
- Create: `resources/views/livewire/rescued-counter.blade.php`
- Modify: `resources/views/pages/home.blade.php` (add the counter section)
- Test: `tests/Feature/Gamification/RescuedCounterTest.php`

**Interfaces:**
- Consumes: `StatsService::rescuedCount` (Task 1), flag `features.stats`.
- Produces: `<livewire:rescued-counter />` — renders a single cooperative number; renders nothing when the flag is off or the count is 0 (mirrors the `Homelab\Recent` self-hiding pattern).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\RescuedCounter;
use App\Models\Listing;
use Livewire\Livewire;

it('shows the cooperative rescued count', function () {
    Listing::factory()->sold()->count(4)->create();

    Livewire::test(RescuedCounter::class)
        ->assertSee('4')
        ->assertSee('gered');
});

it('renders nothing when there are no sold listings', function () {
    Livewire::test(RescuedCounter::class)->assertDontSee('gered');
});

it('renders nothing when the stats feature is off', function () {
    config()->set('cloudmarktplaats.features.stats', false);
    Listing::factory()->sold()->create();

    Livewire::test(RescuedCounter::class)->assertDontSee('gered');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=RescuedCounterTest`
Expected: FAIL — component missing.

- [ ] **Step 3: Component, view, homepage section**

`app/Livewire/RescuedCounter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Gamification\StatsService;
use Illuminate\View\View;
use Livewire\Component;

class RescuedCounter extends Component
{
    public function render(): View
    {
        $count = config('cloudmarktplaats.features.stats')
            ? app(StatsService::class)->rescuedCount()
            : 0;

        return view('livewire.rescued-counter', ['count' => $count]);
    }
}
```

`resources/views/livewire/rescued-counter.blade.php`:

```blade
<div>
    @if ($count > 0)
        <section class="rounded-sm border border-cmp-border bg-cmp-surface px-6 py-8 text-center">
            <div class="cmp-section-label justify-center mb-3">Samen</div>
            <p class="font-mono text-4xl font-bold text-cmp-signal sm:text-5xl">{{ number_format($count, 0, ',', '.') }}</p>
            <p class="mt-2 text-sm text-cmp-muted">apparaten gered van de sloop — en geteld.</p>
        </section>
    @endif
</div>
```

In `resources/views/pages/home.blade.php`, add the counter as its own section (place it after the recent-listings section and before the principles/datasheet section — mirror the existing section wrapper spacing):

```blade
    {{-- ========== COÖPERATIEVE E-WASTE-TELLER ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 pb-12">
        <livewire:rescued-counter />
    </section>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=RescuedCounterTest`
Expected: 3 passed. Also run `--filter=HomelabHomeSection` and `--filter=BrowseDetail` to confirm the homepage still renders for guests.

- [ ] **Step 5: Pint + PHPStan + full suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T php-fpm php artisan test
git add app/Livewire/RescuedCounter.php resources/views/livewire/rescued-counter.blade.php resources/views/pages/home.blade.php tests/Feature/Gamification/RescuedCounterTest.php
git commit -m "Add cooperative 'devices rescued from e-waste' counter to the homepage"
```

Expected: full suite green (222 existing + ~11 new).

---

### Task 5: Deploy

Ops checklist (CT at 192.168.178.215, app in /opt/cloudmarktplaats):

- [ ] `npm run build` locally (home + profile views changed).
- [ ] Merge the feature branch to `main`, `git push origin main`, confirm CI green.
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache --exclude .superpowers /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/`
- [ ] On the CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:clear && php artisan view:cache'` (no migrations this phase — nothing schema-changed).
- [ ] Verify: `curl -s -o /dev/null -w '%{http_code}' https://cloudmarktplaats.nl/` (200); log in, open `/profile/stats`, confirm tiles + badges render; the homepage counter appears only once there is ≥1 sold listing.
