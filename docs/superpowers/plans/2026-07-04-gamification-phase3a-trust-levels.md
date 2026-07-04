# Gamification Phase 3a — Trust Levels + Moderation-Skip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A derived trust-level engine (new → member → trusted → veteran) computed from a user's own proven activity, unlocking optional moderation-skip (auto-publish) for veterans — gated on real completed sales so it cannot be farmed with sockpuppets.

**Architecture:** A pure `TrustLevelService` derives a level from email-verified, account age, and sold-listing count (no storage, recomputed like badges). The listing wizard consults it: a veteran's submission auto-publishes (skips the moderation queue) when `FEATURE_TRUST_AUTOPUBLISH` is on. The level is surfaced read-only on `/profile/stats` and the Filament users table. No new tables, no schema changes.

**Tech Stack:** Laravel 11, Livewire 3, Filament 3, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-gamification-design.md`. This is Phase 3a (trust-levels). Phase 3b (transaction feedback: seller tags a buyer account, both confirm) is a SEPARATE plan — do not build it here.
- **Anti-sockpuppet (the security gate the final review of Phase 1 warned about):** moderation-skip is gated on `listings_sold >= 5` — real completed sales, each of which required a moderated listing. It is **NOT** gated on karma or invite activations, so an invite ring cannot buy moderation-skip. This is the deliberate defense; do not let any task make trust depend on karma/invites for the auto-publish threshold.
- **Auto-publish defaults OFF.** `FEATURE_TRUST_AUTOPUBLISH` defaults to `false` — unmoderated publishing on a hostile-user platform is the owner's risk decision to enable. The mechanism ships tested but dormant; Nick flips `FEATURE_TRUST_AUTOPUBLISH=true` when ready.
- Trust-level display flag: `FEATURE_TRUST` (env `FEATURE_TRUST`, default `true`) gates the /profile/stats trust tile and the Filament column. Both flags in `config/cloudmarktplaats.php` features + `.env.example`.
- Levels (derived, no storage): `new` (email not verified), `member` (verified), `trusted` (member + age ≥ 14 days + sold ≥ 2), `veteran` (member + age ≥ 30 days + sold ≥ 5). A banned user is always `new`.
- Anti-toxicity/privacy: trust level shows only on the user's own /profile/stats and to staff in Filament — never a public per-user badge or leaderboard.
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 green. Tests Pest under `tests/Feature/Gamification/`. Docker: `docker compose exec -T php-fpm php artisan test --filter=<Name>`; Pint `./vendor/bin/pint --dirty`; PHPStan `./vendor/bin/phpstan analyse --memory-limit=512M`. Full suite currently 233 green. `withoutVite()` is global in TestCase.

---

### Task 1: TrustLevelService + feature flags

**Files:**
- Create: `app/Services/Gamification/TrustLevelService.php`
- Modify: `config/cloudmarktplaats.php` (features.trust, features.trust_autopublish)
- Modify: `.env.example` (FEATURE_TRUST, FEATURE_TRUST_AUTOPUBLISH)
- Test: `tests/Feature/Gamification/TrustLevelServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (email_verified_at, created_at, is_banned), `App\Models\Listing` (state=sold count).
- Produces `App\Services\Gamification\TrustLevelService`:
  - `forUser(User $user): array` → `['key' => string, 'label' => string, 'rank' => int]` where key/rank is one of new/0, member/1, trusted/2, veteran/3. A banned user is `new`.
  - `canSkipModeration(User $user): bool` → true iff `FEATURE_TRUST_AUTOPUBLISH` is on AND the user is `veteran` (rank 3) AND not banned.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\TrustLevelService;

it('is new when email is unverified', function () {
    $u = User::factory()->create(['email_verified_at' => null]);
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('new');
});

it('is member when verified but new', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('member');
});

it('is trusted at 14 days + 2 sales', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(20)]);
    Listing::factory()->sold()->for($u)->count(2)->create();
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('trusted');
});

it('is veteran at 30 days + 5 sales and may skip moderation when enabled', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($u)->count(5)->create();

    $svc = app(TrustLevelService::class);
    expect($svc->forUser($u)['key'])->toBe('veteran')
        ->and($svc->canSkipModeration($u))->toBeTrue();
});

it('never skips moderation on karma alone (anti-sockpuppet)', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    // Old, verified, high karma — but ZERO sales.
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(90)]);
    \App\Models\KarmaEvent::factory()->for($u)->count(50)->create(['points' => 10]);

    $svc = app(TrustLevelService::class);
    expect($svc->forUser($u)['key'])->not->toBe('veteran')
        ->and($svc->canSkipModeration($u))->toBeFalse();
});

it('never skips moderation when the flag is off', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', false);
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($u)->count(5)->create();

    expect(app(TrustLevelService::class)->canSkipModeration($u))->toBeFalse();
});

it('a banned user is always new', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(90), 'is_banned' => true]);
    Listing::factory()->sold()->for($u)->count(10)->create();
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('new');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=TrustLevelServiceTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service + flags**

`app/Services/Gamification/TrustLevelService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\Listing;
use App\Models\User;

/**
 * Derives a user's trust level from their OWN proven activity — verified
 * email, account age, and completed sales. Recomputed on demand (no
 * storage), like badges.
 *
 * Moderation-skip (auto-publish) is gated on sales, never on karma or
 * invite activations: a completed sale requires a moderated listing plus
 * a real buyer, so a sockpuppet invite ring cannot farm its way to
 * skipping moderation. This is the deliberate anti-abuse boundary.
 */
class TrustLevelService
{
    /**
     * @return array{key: string, label: string, rank: int}
     */
    public function forUser(User $user): array
    {
        if ($user->is_banned) {
            return ['key' => 'new', 'label' => 'Nieuw', 'rank' => 0];
        }

        $verified = $user->email_verified_at !== null;
        if (! $verified) {
            return ['key' => 'new', 'label' => 'Nieuw', 'rank' => 0];
        }

        $ageDays = $user->created_at->diffInDays(now());
        $sold = Listing::query()->where('user_id', $user->id)->where('state', 'sold')->count();

        if ($ageDays >= 30 && $sold >= 5) {
            return ['key' => 'veteran', 'label' => 'Veteraan', 'rank' => 3];
        }
        if ($ageDays >= 14 && $sold >= 2) {
            return ['key' => 'trusted', 'label' => 'Vertrouwd', 'rank' => 2];
        }

        return ['key' => 'member', 'label' => 'Lid', 'rank' => 1];
    }

    public function canSkipModeration(User $user): bool
    {
        return (bool) config('cloudmarktplaats.features.trust_autopublish')
            && $this->forUser($user)['rank'] >= 3;
    }
}
```

`config/cloudmarktplaats.php` — add to the `features` array after `stats`:

```php
        'trust' => env('FEATURE_TRUST', true),
        'trust_autopublish' => env('FEATURE_TRUST_AUTOPUBLISH', false),
```

`.env.example` after `FEATURE_STATS=true`:

```
FEATURE_TRUST=true
FEATURE_TRUST_AUTOPUBLISH=false
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=TrustLevelServiceTest`
Expected: 7 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Gamification/TrustLevelService.php config/cloudmarktplaats.php .env.example tests/Feature/Gamification/TrustLevelServiceTest.php
git commit -m "Add TrustLevelService: sales-gated trust levels (sockpuppet-safe), flags"
```

---

### Task 2: Moderation-skip for veterans in the listing wizard

**Files:**
- Modify: `app/Livewire/Listings/Wizard.php` (submit() — auto-publish path)
- Test: `tests/Feature/Gamification/ModerationSkipTest.php`

**Interfaces:**
- Consumes: `TrustLevelService::canSkipModeration` (Task 1), `ListingStateService::transition` (exists).
- Produces: in `Wizard::submit()`, after the existing `transition($listing, 'pending_review')`, if `canSkipModeration(auth user)` is true, immediately `transition($listing, 'published')` — so a veteran's listing goes live without the queue. Default (flag off / non-veteran) is unchanged: stays `pending_review`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->category = Category::factory()->create();
});

function photoUpload(): UploadedFile
{
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $u = UploadedFile::fake()->createWithContent('lab.jpg', $bytes);

    return $u;
}

it('auto-publishes a veteran\'s listing when autopublish is on', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $veteran = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($veteran)->count(5)->create();

    // Drive the wizard's submit for a fresh draft owned by the veteran.
    // (Mirror how tests/Feature/Listings/WizardTest.php builds a draft +
    //  fills the required fields through the steps; reuse that exact setup.)
    // After submit(), the newly created listing must be 'published'.
    // ... build draft via the wizard helper used in WizardTest, then:
    // expect($listing->fresh()->state)->toBe('published');
})->todo('fill in using the WizardTest draft-building helper');

it('keeps a normal member\'s listing in pending_review', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $member = User::factory()->create(['email_verified_at' => now()]);
    // Same wizard flow → expect 'pending_review'.
})->todo('fill in using the WizardTest draft-building helper');
```

IMPORTANT for the implementer: the two tests above are `->todo()` placeholders because the exact wizard-driving setup lives in `tests/Feature/Listings/WizardTest.php`. Before implementing, READ that file and reproduce its draft-building + step-filling flow (it uses `Livewire::test(Wizard::class)`, sets basic fields, category, and photos, then calls `submit()`). Write TWO real tests: (1) a veteran with autopublish on ends at `state = 'published'`; (2) a plain member ends at `state = 'pending_review'`. Remove the `->todo()`. The assertion is on the created listing's `state` after `submit()`. Use the real fixture `tests/Fixtures/photo-with-gps.jpg` as WizardTest does.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=ModerationSkipTest`
Expected: FAIL — veteran listing is `pending_review`, not `published`.

- [ ] **Step 3: Implement the auto-publish path**

In `app/Livewire/Listings/Wizard.php` `submit()`, replace the transition block:

```php
        try {
            app(ListingStateService::class)->transition($listing, 'pending_review');

            // Trusted veterans skip the moderation queue (flag-gated,
            // sales-gated — see TrustLevelService). Goes through the same
            // auditable state service and fires ListingPublished.
            $user = auth()->user();
            if ($user !== null && app(\App\Services\Gamification\TrustLevelService::class)->canSkipModeration($user)) {
                app(ListingStateService::class)->transition($listing, 'published');
            }
        } catch (InvalidStateTransition $e) {
            $this->addError('state', $e->getMessage());

            return;
        }
```

(Keep the surrounding code — photo handling above, redirect below — unchanged.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=ModerationSkipTest`
Expected: 2 passed. Also run `--filter=Wizard` (existing wizard tests must stay green — a normal member still lands in pending_review).

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Listings/Wizard.php tests/Feature/Gamification/ModerationSkipTest.php
git commit -m "Wizard: veterans skip the moderation queue (flag-gated auto-publish)"
```

---

### Task 3: Surface the trust level (own dashboard + Filament)

**Files:**
- Modify: `app/Livewire/Profile/Stats.php` (pass trust level to the view)
- Modify: `resources/views/livewire/profile/stats.blade.php` (a trust tile, flag-gated)
- Modify: `app/Filament/Resources/UserResource.php` (trust-level column)
- Test: `tests/Feature/Gamification/TrustLevelDisplayTest.php`

**Interfaces:**
- Consumes: `TrustLevelService::forUser` (Task 1), flag `features.trust`.
- Produces: the /profile/stats page shows the user's own trust-level label when `FEATURE_TRUST` is on; the Filament users table shows a `trust` column (staff-only).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Livewire\Profile\Stats;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('shows the user their own trust level on the stats page', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($u)->test(Stats::class)->assertOk()->assertSee('Lid'); // member label
});

it('hides the trust tile when FEATURE_TRUST is off', function () {
    config()->set('cloudmarktplaats.features.trust', false);
    $u = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($u)->test(Stats::class)->assertOk()->assertDontSee('Vertrouwensniveau');
});

it('shows a trust column on the Filament users table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $veteran = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($veteran)->count(5)->create();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableColumnExists('trust')
        ->assertTableColumnStateSet('trust', 'Veteraan', record: $veteran);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=TrustLevelDisplayTest`
Expected: FAIL — no trust display yet.

- [ ] **Step 3: Implement the display**

In `app/Livewire/Profile/Stats.php` `render()`, add the trust level to the view data:

```php
        return view('livewire.profile.stats', [
            'stats' => $stats,
            'badges' => app(BadgeService::class)->earnedFor($stats),
            'trust' => config('cloudmarktplaats.features.trust')
                ? app(\App\Services\Gamification\TrustLevelService::class)->forUser($user)
                : null,
        ]);
```

In `resources/views/livewire/profile/stats.blade.php`, add a trust tile after the opening intro paragraph (before the `<dl>` grid) — flag-gated by the null:

```blade
    @if ($trust !== null)
        <div class="mt-6 flex items-center gap-3 rounded-sm border-2 border-cmp-ink bg-cmp-surface px-4 py-3">
            <span class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">Vertrouwensniveau</span>
            <span class="cmp-label-chip">{{ $trust['label'] }}</span>
        </div>
    @endif
```

In `app/Filament/Resources/UserResource.php`, add to the table `->columns([...])` array (after the karma column added in Phase 1):

```php
                Tables\Columns\TextColumn::make('trust')
                    ->label('Trust')
                    ->state(fn (User $record): string => app(\App\Services\Gamification\TrustLevelService::class)->forUser($record)['label'])
                    ->badge(),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=TrustLevelDisplayTest`
Expected: 3 passed. Also run `--filter="StatsPage|UserResource"` (existing displays stay green).

- [ ] **Step 5: Pint + PHPStan + full suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T php-fpm php artisan test
git add app/Livewire/Profile/Stats.php resources/views/livewire/profile/stats.blade.php app/Filament/Resources/UserResource.php tests/Feature/Gamification/TrustLevelDisplayTest.php
git commit -m "Surface trust level on /profile/stats + Filament users table"
```

Expected: full suite green (233 existing + ~12 new).

---

### Task 4: Deploy

Ops checklist (CT at 192.168.178.215, /opt/cloudmarktplaats):

- [ ] `npm run build` locally (stats view changed).
- [ ] Merge to `main`, `git push origin main`, confirm CI green (run `pint --test` locally first — a docblock-only import triggers `no_unused_imports` in CI's lint job).
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache --exclude .superpowers /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/`
- [ ] On the CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:clear && php artisan view:cache'` (no migrations).
- [ ] Verify: `/profile/stats` shows a trust tile; the Filament users table shows a Trust column. **Auto-publish stays OFF** (FEATURE_TRUST_AUTOPUBLISH=false in prod .env) until Nick decides to enable it — to enable later: set `FEATURE_TRUST_AUTOPUBLISH=true` in the CT `.env` and `php artisan config:cache`.
