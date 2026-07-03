# Gamification Phase 1 — Invites + Karma Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An invite-code system with a traceable invite tree, plus an append-only karma ledger where an inviter earns karma when their invitee publishes their first listing (reversed if that invitee is later banned).

**Architecture:** Two new tables (`invite_codes`, `karma_events`) and two `users` columns (`invited_by`, `invite_credits`). Two services own the logic: `InviteService` (generate/redeem with validation) and `KarmaService` (award/revoke/sum, append-only). Karma is triggered off the existing `ListingPublished` event via a listener. Registration gains an optional invite code; a user-facing `/profile/invites` page issues codes. All gated behind a `FEATURE_INVITES` flag.

**Tech Stack:** Laravel 11, Livewire 3, Filament 3, Postgres, Pest 3.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-gamification-design.md`. Locked decisions: public downvotes dropped (not in this phase), open registration + invites-for-a-head-start, karma trigger = invitee's **first published listing**.
- **Anti-toxicity:** no public karma number, no leaderboard, no public "invited by X". Karma/invite tree are visible only to the user themselves and to staff in Filament.
- **Append-only karma:** never delete or mutate a `karma_events` row. Reversals are compensating rows with negative points. `karma = SUM(points)`.
- **Skin in the game:** when an invitee is banned, the inviter's `invite_activation` karma from that invitee is reversed.
- Feature flag: `config('cloudmarktplaats.features.invites')`, env `FEATURE_INVITES`, default `true`. Route + register field + profile page respect it.
- Config values live in `config/cloudmarktplaats.php` under `gamification`: `starting_invite_credits` (3), `karma_invite_activation` (10).
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 green. Tests are Pest feature tests under `tests/Feature/Gamification/`. Run in Docker: `docker compose exec -T php-fpm php artisan test --filter=<Name>`; Pint: `./vendor/bin/pint --dirty`; PHPStan: `./vendor/bin/phpstan analyse --memory-limit=512M`.
- `withoutVite()` is already global in `Tests\TestCase::setUp()`; page-render tests need no manifest.

---

### Task 1: Migrations, models, User columns/relations, config + flag

**Files:**
- Create: `database/migrations/2026_07_04_000100_create_invite_codes_table.php`
- Create: `database/migrations/2026_07_04_000200_create_karma_events_table.php`
- Create: `database/migrations/2026_07_04_000300_add_invite_columns_to_users.php`
- Create: `app/Models/InviteCode.php`
- Create: `app/Models/KarmaEvent.php`
- Create: `database/factories/InviteCodeFactory.php`
- Create: `database/factories/KarmaEventFactory.php`
- Modify: `app/Models/User.php` (relations + karma accessor + fillable/casts additions)
- Modify: `config/cloudmarktplaats.php` (features.invites + gamification block)
- Modify: `.env.example` (FEATURE_INVITES)
- Modify: `app/Providers/AppServiceProvider.php` (morph map: add `user`)
- Test: `tests/Feature/Gamification/GamificationModelsTest.php`

**Interfaces:**
- Produces `App\Models\InviteCode`: fillable `[code, inviter_user_id, invitee_user_id, expires_at, revoked_at, used_at]`; `inviter(): BelongsTo`, `invitee(): BelongsTo`; `scopeRedeemable(Builder): Builder` (used_at null, revoked_at null, expires_at null-or-future); auto-`code` on create if empty.
- Produces `App\Models\KarmaEvent`: fillable `[user_id, type, points, source_type, source_id]`; `user(): BelongsTo`, `source(): MorphTo`.
- Produces on `User`: `invitedBy(): BelongsTo`, `invitesSent(): HasMany`, `karmaEvents(): HasMany`, `karma(): int` accessor (SUM points), columns `invited_by` (nullable fk), `invite_credits` (int default from config at insert time — set explicitly by callers, DB default 0).
- Produces morph alias `'user' => User::class`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\InviteCode;
use App\Models\KarmaEvent;
use App\Models\User;

it('auto-generates a code and scopes redeemable', function () {
    $open = InviteCode::factory()->create();
    InviteCode::factory()->used()->create();
    InviteCode::factory()->create(['expires_at' => now()->subDay()]);

    expect($open->code)->toBeString()->not->toBe('')
        ->and(InviteCode::query()->redeemable()->pluck('id')->all())->toBe([$open->id]);
});

it('sums karma from the ledger', function () {
    $user = User::factory()->create();
    KarmaEvent::factory()->for($user)->create(['points' => 10]);
    KarmaEvent::factory()->for($user)->create(['points' => -10]);
    KarmaEvent::factory()->for($user)->create(['points' => 5]);

    expect($user->refresh()->karma)->toBe(5);
});

it('links an invite tree', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);

    expect($invitee->invitedBy->is($inviter))->toBeTrue()
        ->and($inviter->invitesSent)->toHaveCount(0); // invitesSent = codes, not users
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=GamificationModelsTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write migrations, models, factories, User changes, config, morph alias**

`database/migrations/2026_07_04_000100_create_invite_codes_table.php`:

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
        Schema::create('invite_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invitee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index('inviter_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_codes');
    }
};
```

`database/migrations/2026_07_04_000200_create_karma_events_table.php`:

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
        // Append-only ledger. karma = SUM(points). Reversals are negative rows.
        Schema::create('karma_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type');
            $t->integer('points');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->timestamps();
            $t->index(['user_id']);
            $t->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karma_events');
    }
};
```

`database/migrations/2026_07_04_000300_add_invite_columns_to_users.php`:

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
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('invited_by')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $t->unsignedInteger('invite_credits')->default(0)->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invited_by');
            $t->dropColumn('invite_credits');
        });
    }
};
```

`app/Models/InviteCode.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InviteCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InviteCode extends Model
{
    /** @use HasFactory<InviteCodeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'inviter_user_id',
        'invitee_user_id',
        'used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InviteCode $c) {
            if ($c->code === null || $c->code === '') {
                // 10-char, unambiguous uppercase (no O/0/I/1) codes.
                $c->code = strtoupper(Str::password(10, false, true, false, false));
            }
        });
    }

    /**
     * @param  Builder<InviteCode>  $query
     * @return Builder<InviteCode>
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}
```

Note: `Str::password()` may include symbols; the args `(length, letters=false, numbers=true, symbols=false, spaces=false)` — verify the exact Laravel 11 signature is `password(int $length = 32, bool $letters = true, bool $numbers = true, bool $symbols = true, bool $spaces = false)`. Use `Str::password(10, true, true, false, false)` then `strtoupper` and strip ambiguous chars: replace `[O0I1]` with a fixed safe char via `str_replace(['O','0','I','1'], ['X','Y','Z','W'], ...)`. If that feels fragile, use `strtoupper(Str::random(10))` and rely on the unique index + retry-on-collision (unique index makes a dup insert throw; acceptable at this volume). Implementer: pick whichever is simplest that yields a unique uppercase alphanumeric code; the test only asserts non-empty + unique.

`app/Models/KarmaEvent.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KarmaEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KarmaEvent extends Model
{
    /** @use HasFactory<KarmaEventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'points',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
```

`database/factories/InviteCodeFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InviteCode> */
class InviteCodeFactory extends Factory
{
    protected $model = InviteCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inviter_user_id' => User::factory(),
            'invitee_user_id' => null,
            'used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'invitee_user_id' => User::factory(),
            'used_at' => now(),
        ]);
    }
}
```

`database/factories/KarmaEventFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KarmaEvent> */
class KarmaEventFactory extends Factory
{
    protected $model = KarmaEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'invite_activation',
            'points' => 10,
            'source_type' => null,
            'source_id' => null,
        ];
    }
}
```

In `app/Models/User.php` add to `$fillable`: `'invited_by'`, `'invite_credits'`. Add to `casts()`: `'invite_credits' => 'integer'`. Add relations + accessor (place near other relations):

```php
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function invitedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<InviteCode, $this> */
    public function invitesSent(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InviteCode::class, 'inviter_user_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<KarmaEvent, $this> */
    public function karmaEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KarmaEvent::class);
    }

    public function getKarmaAttribute(): int
    {
        return (int) $this->karmaEvents()->sum('points');
    }
```

Add the imports `use App\Models\InviteCode;` etc. are unnecessary (same namespace `App\Models`). Follow the file's existing style for relation return-type imports (it may already `use Illuminate\...\BelongsTo`). If those use-statements exist, use the short names instead of FQCN.

`config/cloudmarktplaats.php` — add to the `features` array after `homelab_feed`:

```php
        'invites' => env('FEATURE_INVITES', true),
```

and add a top-level block after `features`:

```php
    'gamification' => [
        'starting_invite_credits' => 3,
        'karma_invite_activation' => 10,
    ],
```

`.env.example` after `FEATURE_HOMELAB_FEED=true`:

```
FEATURE_INVITES=true
```

`app/Providers/AppServiceProvider.php` morph map — add `'user' => User::class,` to the `enforceMorphMap([...])` array (needed so KarmaEvent.source can point at a User with a stable alias). Import `use App\Models\User;` if not present.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=GamificationModelsTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/migrations/2026_07_04_000100_create_invite_codes_table.php database/migrations/2026_07_04_000200_create_karma_events_table.php database/migrations/2026_07_04_000300_add_invite_columns_to_users.php app/Models/InviteCode.php app/Models/KarmaEvent.php database/factories/InviteCodeFactory.php database/factories/KarmaEventFactory.php app/Models/User.php config/cloudmarktplaats.php .env.example app/Providers/AppServiceProvider.php tests/Feature/Gamification/GamificationModelsTest.php
git commit -m "Add invite_codes + karma_events tables, models, user invite columns"
```

---

### Task 2: KarmaService (award / revoke / sum, append-only)

**Files:**
- Create: `app/Services/Gamification/KarmaService.php`
- Test: `tests/Feature/Gamification/KarmaServiceTest.php`

**Interfaces:**
- Consumes: `KarmaEvent`, `User` (Task 1).
- Produces `App\Services\Gamification\KarmaService`:
  - `award(User $user, string $type, int $points, ?Model $source = null): KarmaEvent`
  - `revokeInviteActivation(User $invitee): void` — for every `invite_activation` event whose source is this invitee (source_type alias `user`, source_id = invitee->id), writes one compensating `invite_reversal` row with negated points to the same beneficiary; idempotent (skips activations already reversed).
  - `karmaFor(User $user): int`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Gamification\KarmaService;

it('awards karma with an optional source', function () {
    $user = User::factory()->create();
    $source = User::factory()->create();

    $svc = app(KarmaService::class);
    $svc->award($user, 'invite_activation', 10, $source);

    expect($svc->karmaFor($user))->toBe(10)
        ->and($user->karmaEvents()->first()->source->is($source))->toBeTrue();
});

it('reverses an invitee activation exactly once', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $svc = app(KarmaService::class);
    $svc->award($inviter, 'invite_activation', 10, $invitee);

    $svc->revokeInviteActivation($invitee);
    $svc->revokeInviteActivation($invitee); // idempotent — no double reversal

    expect($svc->karmaFor($inviter))->toBe(0)
        ->and($inviter->karmaEvents()->count())->toBe(2); // +10 award, -10 reversal
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=KarmaServiceTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\KarmaEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only karma ledger. karma = SUM(points). Nothing is ever
 * mutated or deleted; a reversal is a compensating negative row.
 */
class KarmaService
{
    public function award(User $user, string $type, int $points, ?Model $source = null): KarmaEvent
    {
        return KarmaEvent::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'points' => $points,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * Reverse every invite_activation earned *from* this invitee. Skips
     * activations that already have a matching reversal (idempotent) so
     * a repeated ban action can't double-dock the inviter.
     */
    public function revokeInviteActivation(User $invitee): void
    {
        $alias = $invitee->getMorphClass();

        $activations = KarmaEvent::query()
            ->where('type', 'invite_activation')
            ->where('source_type', $alias)
            ->where('source_id', $invitee->getKey())
            ->get();

        foreach ($activations as $activation) {
            $alreadyReversed = KarmaEvent::query()
                ->where('type', 'invite_reversal')
                ->where('source_type', $alias)
                ->where('source_id', $invitee->getKey())
                ->where('user_id', $activation->user_id)
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            KarmaEvent::query()->create([
                'user_id' => $activation->user_id,
                'type' => 'invite_reversal',
                'points' => -$activation->points,
                'source_type' => $alias,
                'source_id' => $invitee->getKey(),
            ]);
        }
    }

    public function karmaFor(User $user): int
    {
        return (int) $user->karmaEvents()->sum('points');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=KarmaServiceTest`
Expected: 2 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Gamification/KarmaService.php tests/Feature/Gamification/KarmaServiceTest.php
git commit -m "Add KarmaService: append-only award/revoke/sum"
```

---

### Task 3: InviteService (generate / redeem with validation)

**Files:**
- Create: `app/Exceptions/InviteException.php`
- Create: `app/Services/Gamification/InviteService.php`
- Test: `tests/Feature/Gamification/InviteServiceTest.php`

**Interfaces:**
- Consumes: `InviteCode`, `User` (Task 1).
- Produces `App\Exceptions\InviteException extends \RuntimeException`.
- Produces `App\Services\Gamification\InviteService`:
  - `generate(User $inviter): InviteCode` — requires `invite_credits > 0`, verified email, not banned; decrements a credit; throws `InviteException` otherwise. Runs in a transaction with a row lock on the user to avoid credit races.
  - `redeem(string $code, User $invitee): InviteCode` — locks the code row `FOR UPDATE`, validates redeemable + not-self-invite (inviter !== invitee) + invitee not already invited; sets `invitee_user_id`, `used_at`, and `invitee.invited_by`. Throws `InviteException` on any failure.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Exceptions\InviteException;
use App\Models\InviteCode;
use App\Models\User;
use App\Services\Gamification\InviteService;

function verifiedUser(int $credits = 3): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
        'invite_credits' => $credits,
    ]);
}

it('generates a code and spends a credit', function () {
    $inviter = verifiedUser(2);
    $code = app(InviteService::class)->generate($inviter);

    expect($code->inviter_user_id)->toBe($inviter->id)
        ->and($inviter->refresh()->invite_credits)->toBe(1);
});

it('refuses to generate without credits', function () {
    $inviter = verifiedUser(0);

    expect(fn () => app(InviteService::class)->generate($inviter))
        ->toThrow(InviteException::class);
});

it('redeems a code and links the invitee', function () {
    $inviter = verifiedUser();
    $code = app(InviteService::class)->generate($inviter);
    $invitee = User::factory()->create();

    app(InviteService::class)->redeem($code->code, $invitee);

    expect($invitee->refresh()->invited_by)->toBe($inviter->id)
        ->and($code->refresh()->invitee_user_id)->toBe($invitee->id)
        ->and($code->used_at)->not->toBeNull();
});

it('rejects a reused, self, or unknown code', function () {
    $inviter = verifiedUser();
    $code = app(InviteService::class)->generate($inviter);
    $invitee = User::factory()->create();
    app(InviteService::class)->redeem($code->code, $invitee);

    // reused
    $other = User::factory()->create();
    expect(fn () => app(InviteService::class)->redeem($code->code, $other))
        ->toThrow(InviteException::class);

    // unknown
    expect(fn () => app(InviteService::class)->redeem('NOPE000000', $other))
        ->toThrow(InviteException::class);

    // self-invite
    $selfCode = app(InviteService::class)->generate($inviter);
    expect(fn () => app(InviteService::class)->redeem($selfCode->code, $inviter))
        ->toThrow(InviteException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=InviteServiceTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the exception + service**

`app/Exceptions/InviteException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InviteException extends RuntimeException {}
```

`app/Services/Gamification/InviteService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\InviteException;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InviteService
{
    public function generate(User $inviter): InviteCode
    {
        return DB::transaction(function () use ($inviter): InviteCode {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($inviter->id);

            if ($locked->email_verified_at === null) {
                throw new InviteException('Verifieer eerst je e-mailadres.');
            }
            if ($locked->is_banned) {
                throw new InviteException('Geblokkeerde accounts kunnen geen uitnodigingen maken.');
            }
            if ($locked->invite_credits < 1) {
                throw new InviteException('Je hebt geen uitnodigingen meer over.');
            }

            $locked->decrement('invite_credits');

            return InviteCode::query()->create([
                'inviter_user_id' => $locked->id,
            ]);
        });
    }

    public function redeem(string $code, User $invitee): InviteCode
    {
        return DB::transaction(function () use ($code, $invitee): InviteCode {
            /** @var InviteCode|null $row */
            $row = InviteCode::query()->where('code', $code)->lockForUpdate()->first();

            if ($row === null || $row->used_at !== null || $row->revoked_at !== null
                || ($row->expires_at !== null && $row->expires_at->isPast())) {
                throw new InviteException('Deze uitnodigingscode is ongeldig of al gebruikt.');
            }
            if ($row->inviter_user_id === $invitee->id) {
                throw new InviteException('Je kunt je eigen code niet inwisselen.');
            }
            if ($invitee->invited_by !== null) {
                throw new InviteException('Dit account is al aan een uitnodiging gekoppeld.');
            }

            $row->forceFill([
                'invitee_user_id' => $invitee->id,
                'used_at' => now(),
            ])->save();

            $invitee->forceFill(['invited_by' => $row->inviter_user_id])->save();

            return $row;
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=InviteServiceTest`
Expected: 4 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Exceptions/InviteException.php app/Services/Gamification/InviteService.php tests/Feature/Gamification/InviteServiceTest.php
git commit -m "Add InviteService: generate (credit-gated) + redeem (validated, locked)"
```

---

### Task 4: Register integration — optional invite code

**Files:**
- Modify: `app/Livewire/Auth/Register.php`
- Modify: `resources/views/livewire/auth/register.blade.php`
- Test: `tests/Feature/Gamification/RegisterWithInviteTest.php`

**Interfaces:**
- Consumes: `InviteService::redeem` (Task 3), config `gamification.starting_invite_credits`, flag `features.invites`.
- Produces: `Register` gains public `string $invite_code`, prefilled from `?invite=` via `mount()`. New users are created with `invite_credits = config('cloudmarktplaats.features.invites') ? config('cloudmarktplaats.gamification.starting_invite_credits') : 0`. A supplied code is validated and redeemed inside the registration transaction; an invalid code blocks submission with a field error (the user can clear it and register without one).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\InviteCode;
use App\Models\User;
use App\Services\Gamification\InviteService;
use Livewire\Livewire;

it('prefills the invite code from the query string', function () {
    $this->get('/register?invite=ABC1234567')
        ->assertOk()
        ->assertSee('ABC1234567');
});

it('links the new account to the inviter when a valid code is used', function () {
    $inviter = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 3]);
    $code = app(InviteService::class)->generate($inviter);

    Livewire::test(Register::class)
        ->set('email', 'new@b.nl')->set('username', 'newbie')->set('display_name', 'New')
        ->set('password', 'secret-1234')->set('password_confirmation', 'secret-1234')
        ->set('accept_tos', true)
        ->set('invite_code', $code->code)
        ->call('submit')
        ->assertHasNoErrors();

    $new = User::query()->where('email', 'new@b.nl')->first();
    expect($new->invited_by)->toBe($inviter->id)
        ->and($new->invite_credits)->toBe(3);
});

it('rejects registration with an invalid invite code', function () {
    Livewire::test(Register::class)
        ->set('email', 'x@b.nl')->set('username', 'xuser')->set('display_name', 'X')
        ->set('password', 'secret-1234')->set('password_confirmation', 'secret-1234')
        ->set('accept_tos', true)
        ->set('invite_code', 'BOGUS00000')
        ->call('submit')
        ->assertHasErrors('invite_code');

    expect(User::query()->where('email', 'x@b.nl')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=RegisterWithInviteTest`
Expected: FAIL — no invite_code handling.

- [ ] **Step 3: Modify Register component + view**

In `app/Livewire/Auth/Register.php`: add property and mount, inject starting credits, redeem inside the transaction. Add imports `use App\Exceptions\InviteException;` and `use App\Services\Gamification\InviteService;`.

Add property (after `$accept_tos`):

```php
    public string $invite_code = '';

    public function mount(): void
    {
        $code = request()->query('invite');
        if (is_string($code)) {
            $this->invite_code = $code;
        }
    }
```

Replace the `$user = DB::transaction(...)` assignment so it (a) sets starting credits and (b) redeems a supplied code, converting an `InviteException` into a field error. Wrap the existing transaction body; after `$u` is created and before returning, redeem:

```php
        $invitesOn = (bool) config('cloudmarktplaats.features.invites');
        $startingCredits = $invitesOn ? (int) config('cloudmarktplaats.gamification.starting_invite_credits') : 0;
        $code = trim($this->invite_code);

        try {
            $user = DB::transaction(function () use ($startingCredits, $invitesOn, $code): User {
                $u = User::create([
                    'email' => $this->email,
                    'username' => strtolower($this->username),
                    'display_name' => $this->display_name,
                    'password_hash' => Hash::make($this->password),
                    'invite_credits' => $startingCredits,
                ]);
                UserIdentity::create([
                    'user_id' => $u->id,
                    'provider' => 'password',
                    'provider_uid' => (string) $u->id,
                ]);
                foreach (['tos', 'privacy'] as $type) {
                    $doc = LegalDocument::current($type, app()->getLocale());
                    if ($doc) {
                        LegalAcceptance::create([
                            'user_id' => $u->id,
                            'legal_document_id' => $doc->id,
                            'accepted_at' => now(),
                            'ip_hash' => hash('sha256', request()->ip().config('app.key')),
                        ]);
                    }
                }
                if ($invitesOn && $code !== '') {
                    app(InviteService::class)->redeem($code, $u);
                }

                return $u;
            });
        } catch (InviteException $e) {
            $this->addError('invite_code', $e->getMessage());

            return;
        }
```

Keep the existing `event(new Registered($user)); auth()->login($user); $this->redirect('/email/verify-notice');` after the try/catch.

In `resources/views/livewire/auth/register.blade.php`, add an optional invite field before the submit button (only when the feature is on):

```blade
        @if (config('cloudmarktplaats.features.invites'))
            <input wire:model="invite_code" placeholder="uitnodigingscode (optioneel)" class="w-full rounded-sm border-cmp-border p-2 focus:border-cmp-signal focus:ring-cmp-signal">
            @error('invite_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=RegisterWithInviteTest`
Expected: 3 passed. Also run `--filter=RegisterTest` (existing registration tests must stay green).

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Auth/Register.php resources/views/livewire/auth/register.blade.php tests/Feature/Gamification/RegisterWithInviteTest.php
git commit -m "Register: optional invite code (prefill, validate, redeem, starting credits)"
```

---

### Task 5: Karma on the invitee's first published listing

**Files:**
- Create: `app/Listeners/Gamification/AwardInviteKarmaOnFirstListing.php`
- Modify: `app/Providers/AppServiceProvider.php` (register the listener)
- Test: `tests/Feature/Gamification/InviteKarmaOnPublishTest.php`

**Interfaces:**
- Consumes: `App\Events\Listings\ListingPublished` (exists; constructor `public Listing $listing`), `KarmaService::award` (Task 2), config `gamification.karma_invite_activation`.
- Produces: a listener that, when a listing is published, awards `invite_activation` karma to the owner's inviter **iff** this is the owner's first published listing and the owner was invited — idempotent (guarded by a pre-existing activation check).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Events\Listings\ListingPublished;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;

it('awards the inviter karma on the invitees first published listing', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    $listing = Listing::factory()->published()->for($invitee)->create();

    event(new ListingPublished($listing));

    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);
});

it('does not award on a second listing or for an uninvited user', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    $first = Listing::factory()->published()->for($invitee)->create();
    $second = Listing::factory()->published()->for($invitee)->create();

    event(new ListingPublished($first));
    event(new ListingPublished($first)); // replay — still idempotent
    event(new ListingPublished($second));

    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);

    $loner = User::factory()->create(['invited_by' => null]);
    event(new ListingPublished(Listing::factory()->published()->for($loner)->create()));
    expect(KarmaEvent::query()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=InviteKarmaOnPublishTest`
Expected: FAIL — listener not wired.

- [ ] **Step 3: Write the listener + register it**

`app/Listeners/Gamification/AwardInviteKarmaOnFirstListing.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners\Gamification;

use App\Events\Listings\ListingPublished;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;

class AwardInviteKarmaOnFirstListing
{
    public function __construct(private readonly KarmaService $karma) {}

    public function handle(ListingPublished $event): void
    {
        $owner = $event->listing->user;
        if (! $owner instanceof User || $owner->invited_by === null) {
            return;
        }

        // First published listing only. published_at is set before this
        // event fires, so the count includes the current listing.
        $publishedCount = Listing::query()
            ->where('user_id', $owner->id)
            ->where('state', 'published')
            ->count();
        if ($publishedCount !== 1) {
            return;
        }

        $inviter = User::query()->find($owner->invited_by);
        if (! $inviter instanceof User) {
            return;
        }

        // Idempotency: never award twice for the same invitee.
        $already = $inviter->karmaEvents()
            ->where('type', 'invite_activation')
            ->where('source_type', $owner->getMorphClass())
            ->where('source_id', $owner->id)
            ->exists();
        if ($already) {
            return;
        }

        $this->karma->award(
            $inviter,
            'invite_activation',
            (int) config('cloudmarktplaats.gamification.karma_invite_activation'),
            $owner,
        );
    }
}
```

In `app/Providers/AppServiceProvider.php` `boot()`, register the listener next to the existing `Event::listen(...)` call:

```php
        Event::listen(
            \App\Events\Listings\ListingPublished::class,
            \App\Listeners\Gamification\AwardInviteKarmaOnFirstListing::class,
        );
```

(The `SocialiteWasCalled` listener stays. Add the `use` imports or use FQCN as shown.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=InviteKarmaOnPublishTest`
Expected: 2 passed. Also run `--filter=ListingStateService` to confirm publishing still works.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Listeners/Gamification/AwardInviteKarmaOnFirstListing.php app/Providers/AppServiceProvider.php tests/Feature/Gamification/InviteKarmaOnPublishTest.php
git commit -m "Award inviter karma on the invitee's first published listing"
```

---

### Task 6: Reverse invite karma when an invitee is banned

**Files:**
- Modify: `app/Filament/Resources/UserResource.php` (ban action closure)
- Test: `tests/Feature/Gamification/BanReversesKarmaTest.php`

**Interfaces:**
- Consumes: `KarmaService::revokeInviteActivation` (Task 2).
- Produces: the Filament `ban` action, after setting `is_banned`, calls `app(KarmaService::class)->revokeInviteActivation($user)` so the inviter loses the karma earned from this now-banned invitee.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;
use Livewire\Livewire;

it('reverses the inviter karma when the invitee is banned', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    event(new App\Events\Listings\ListingPublished(
        Listing::factory()->published()->for($invitee)->create()
    ));
    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('ban', $invitee, data: ['reason' => 'scammer']);

    expect($invitee->refresh()->is_banned)->toBeTrue()
        ->and(app(KarmaService::class)->karmaFor($inviter))->toBe(0);
});
```

Confirm the Filament page class name for the users list (likely `App\Filament\Resources\UserResource\Pages\ListUsers`); if the resource uses a different page class, match it. Confirm how existing Filament action tests pass form data (`callTableAction(..., data: [...])`) by reading `tests/Feature/Admin/` — mirror that exactly.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=BanReversesKarmaTest`
Expected: FAIL — ban doesn't touch karma yet.

- [ ] **Step 3: Modify the ban action**

In `app/Filament/Resources/UserResource.php`, in the `ban` action closure, after the `AdminActionLogger::log('user.ban', ...)` call, add:

```php
                        app(\App\Services\Gamification\KarmaService::class)->revokeInviteActivation($user);
```

(Keep everything else in the closure unchanged.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=BanReversesKarmaTest`
Expected: 1 passed. Also run `--filter=UserResource` to confirm the existing ban/unban tests stay green.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Filament/Resources/UserResource.php tests/Feature/Gamification/BanReversesKarmaTest.php
git commit -m "Reverse inviter karma when an invitee is banned"
```

---

### Task 7: User-facing /profile/invites page

**Files:**
- Create: `app/Livewire/Profile/Invites.php`
- Create: `resources/views/livewire/profile/invites.blade.php`
- Modify: `routes/web.php` (route, flag-gated)
- Modify: `resources/views/livewire/profile/security.blade.php` (add a link to invites) — OPTIONAL wayfinding; if the security view has no obvious slot, add the link in the navbar user area instead and note it.
- Test: `tests/Feature/Gamification/InvitesPageTest.php`

**Interfaces:**
- Consumes: `InviteService::generate` (Task 3), `KarmaService::karmaFor` (Task 2), flag `features.invites`.
- Produces: route `GET /profile/invites` (auth + verified, flag-gated in `mount()`), Livewire `App\Livewire\Profile\Invites` with action `generate()`. Shows karma total, remaining credits, and the user's codes with a shareable `register?invite=CODE` link.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Profile\Invites;
use App\Models\User;
use Livewire\Livewire;

it('shows karma and lets a verified user generate a code', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 2]);

    Livewire::actingAs($user)
        ->test(Invites::class)
        ->call('generate')
        ->assertHasNoErrors();

    expect($user->refresh()->invitesSent()->count())->toBe(1)
        ->and($user->invite_credits)->toBe(1);
});

it('shows an error when out of credits', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 0]);

    Livewire::actingAs($user)->test(Invites::class)->call('generate')->assertHasErrors('generate');
});

it('404s when the invites feature is off', function () {
    config()->set('cloudmarktplaats.features.invites', false);
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)->test(Invites::class)->assertStatus(404);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=InvitesPageTest`
Expected: FAIL — component/route missing.

- [ ] **Step 3: Component, view, route**

`app/Livewire/Profile/Invites.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Exceptions\InviteException;
use App\Models\InviteCode;
use App\Services\Gamification\InviteService;
use App\Services\Gamification\KarmaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketing', ['title' => 'Uitnodigingen — Cloudmarktplaats'])]
class Invites extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.invites'), 404);
    }

    public function generate(): void
    {
        try {
            app(InviteService::class)->generate(auth()->user());
        } catch (InviteException $e) {
            $this->addError('generate', $e->getMessage());
        }
    }

    /** @return Collection<int, InviteCode> */
    public function codes(): Collection
    {
        return auth()->user()->invitesSent()->latest()->get();
    }

    public function render(): View
    {
        return view('livewire.profile.invites', [
            'karma' => app(KarmaService::class)->karmaFor(auth()->user()),
            'credits' => (int) auth()->user()->invite_credits,
            'codes' => $this->codes(),
        ]);
    }
}
```

`resources/views/livewire/profile/invites.blade.php`:

```blade
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Community</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">Uitnodigingen</h1>

    <div class="mt-6 grid grid-cols-2 gap-4">
        <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
            <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">Karma</div>
            <div class="mt-1 font-mono text-2xl font-medium">{{ $karma }}</div>
        </div>
        <div class="rounded-sm border border-cmp-border bg-cmp-surface p-4">
            <div class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">Codes over</div>
            <div class="mt-1 font-mono text-2xl font-medium">{{ $credits }}</div>
        </div>
    </div>

    <div class="mt-6">
        <button wire:click="generate" class="cmp-btn cmp-btn-primary" @disabled($credits < 1)>Genereer een code</button>
        @error('generate') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="mt-8 space-y-2">
        @forelse ($codes as $code)
            @php $used = $code->used_at !== null; @endphp
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div>
                    <span class="font-mono text-sm">{{ $code->code }}</span>
                    <span class="ml-3 cmp-label-chip">{{ $used ? 'Gebruikt' : 'Open' }}</span>
                </div>
                @unless ($used)
                    <span class="font-mono text-[11px] text-cmp-faint">{{ url('/register?invite='.$code->code) }}</span>
                @endunless
            </div>
        @empty
            <p class="text-sm text-cmp-muted">Nog geen codes. Genereer er een en deel de link met iemand die je vertrouwt.</p>
        @endforelse
    </div>
</div>
```

Route in `routes/web.php`, next to the other `/profile` routes (mirror their middleware — check the existing `profile.security` route uses `->middleware('auth')`; add `verified` too, matching how listings gate):

```php
Route::get('/profile/invites', \App\Livewire\Profile\Invites::class)
    ->middleware(['auth', 'verified'])
    ->name('profile.invites');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=InvitesPageTest`
Expected: 3 passed.

- [ ] **Step 5: Pint + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Profile/Invites.php resources/views/livewire/profile/invites.blade.php routes/web.php tests/Feature/Gamification/InvitesPageTest.php
git commit -m "Add /profile/invites: generate codes, show karma + credits"
```

---

### Task 8: Filament admin visibility (karma + invite tree)

**Files:**
- Modify: `app/Filament/Resources/UserResource.php` (table columns)
- Test: `tests/Feature/Gamification/UserResourceKarmaColumnTest.php`

**Interfaces:**
- Consumes: `User::karma` accessor + `invitedBy` relation (Task 1).
- Produces: two read-only columns on the users table — `karma` (via the accessor) and `invitedBy.username` (who invited them). Staff-only (the panel is already `role:admin,moderator`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\KarmaEvent;
use App\Models\User;
use Livewire\Livewire;

it('shows karma and inviter on the users table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inviter = User::factory()->create(['username' => 'thementor']);
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    KarmaEvent::factory()->for($invitee)->create(['points' => 7]);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertSee('thementor')
        ->assertSee('7');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=UserResourceKarmaColumnTest`
Expected: FAIL — columns absent.

- [ ] **Step 3: Add the columns**

In `app/Filament/Resources/UserResource.php`, add to the table `->columns([...])` array (after the existing columns):

```php
                Tables\Columns\TextColumn::make('karma')
                    ->state(fn (User $record): int => $record->karma)
                    ->badge(),
                Tables\Columns\TextColumn::make('invitedBy.username')
                    ->label('Invited by')
                    ->placeholder('—')
                    ->searchable(),
```

(`Tables` is already imported in this file.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=UserResourceKarmaColumnTest`
Expected: 1 passed.

- [ ] **Step 5: Pint + PHPStan + full suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T php-fpm php artisan test
git add app/Filament/Resources/UserResource.php tests/Feature/Gamification/UserResourceKarmaColumnTest.php
git commit -m "Show karma + inviter columns on the Filament users table"
```

Expected: full suite green (198 existing + ~19 new).

---

### Task 9: Deploy

Ops checklist (mirror the project's proven flow; the CT is at 192.168.178.215, app in /opt/cloudmarktplaats):

- [ ] `npm run build` locally (the register + invites views changed; harmless to rebuild regardless).
- [ ] Merge the feature branch to `main`, `git push origin main`, confirm CI goes green.
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache --exclude .superpowers /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/`
- [ ] On the CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:clear && php artisan view:cache'`
- [ ] Verify: `curl -s -o /dev/null -w '%{http_code}' https://cloudmarktplaats.nl/register` (200); log in and open `/profile/invites`, generate a code, register a second account with `?invite=`, publish a listing, confirm the first account's karma rose in Filament.
- [ ] Seed the first admin some invite credits if desired: the migration defaults existing users to 0 credits — grant Nick's account a few via tinker (`User::where('email','ikben@nickaldewereld.nl')->update(['invite_credits'=>10])`) or via the Filament edit form.
