# Gamification Phase 3b — Transaction Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A seller marks a listing sold and optionally tags a registered buyer; the buyer confirms; the confirmed transaction is the reputation signal. Crucially, trust (hence Phase 3a's moderation-skip) is rewired to count **buyer-confirmed** transactions, so a seller can no longer self-farm toward veteran.

**Architecture:** Reuse the existing `transactions` table + `Transaction` model (listing/buyer/seller, status pending→completed). A `DealService` owns mark-sold (seller-initiated, creates a pending transaction) and confirm (buyer-only, → completed). `TrustLevelService` counts completed transactions instead of `state='sold'` listings — shipped in the SAME merge so seller-controlled sold-marking can't reopen the farming hole. Seller UI on the listing detail; buyer confirmation on `/profile/deals`. All behind `FEATURE_DEALS`.

**Tech Stack:** Laravel 11, Livewire 3, Pest 3. Existing: `transactions` table (status enum pending/completed/cancelled, `completed_at`), `Transaction` model with `listing()/buyer()/seller()` relations, `ListingStateService` (published→sold transition sets `sold_at`, fires `ListingSold`).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-03-gamification-design.md`. This is Phase 3b (transaction feedback via seller-tags-buyer, both confirm — the model Nick chose).
- **SECURITY GATE (the whole point of this phase):** Phase 3a gated moderation-skip on `Listing state='sold'` count, but nothing wrote `sold` yet. This phase ADDS seller-controlled sold-marking, so it MUST simultaneously rewire `TrustLevelService` to count **buyer-confirmed transactions** (`Transaction where seller_user_id=user AND status='completed'`), NOT `state='sold'`. A seller marking sold alone (status pending) must NOT advance trust — only a real buyer confirming does. Ship Task 2 (rewire) in the same branch/merge as Task 1. After this phase, `FEATURE_TRUST_AUTOPUBLISH` becomes safe to enable (documented residual risk: a seller colluding with sockpuppet *buyer* accounts — much costlier than self-marking; note it, add per-seller velocity caps in a later phase before relying heavily on auto-publish).
- **Authorization (hostile users):** mark-sold is owner-only (`seller.id === listing.user_id`) and only from `published`. Buyer tag must be a DISTINCT, email-verified account (not self). Confirm is buyer-only, pending→completed only, idempotent. All enforced server-side, not just UI.
- Feature flag: `config('cloudmarktplaats.features.deals')`, env `FEATURE_DEALS`, default `true`. Gates the seller action + the deals page.
- Anti-toxicity: feedback is a binary "deal confirmed" (no public rating, no score). No public per-user reputation number.
- Design per `docs/DESIGN.md`: rounded-sm, cmp-tokens, font-mono for data, cmp-label-chip.
- All PHP: `declare(strict_types=1);`. Pint + PHPStan level 8 green. **Run `./vendor/bin/pint --test` before committing each task — a docblock-only import trips CI's lint job.** Tests Pest under `tests/Feature/Gamification/`. Docker as before. Full suite currently 246 green. `withoutVite()` global.

---

### Task 1: DealService (mark-sold + confirm) + Transaction factory + flag

**Files:**
- Create: `app/Exceptions/DealException.php`
- Create: `app/Services/Gamification/DealService.php`
- Create: `database/factories/TransactionFactory.php`
- Modify: `config/cloudmarktplaats.php` (features.deals)
- Modify: `.env.example` (FEATURE_DEALS)
- Test: `tests/Feature/Gamification/DealServiceTest.php`

**Interfaces:**
- Consumes: `Transaction`, `Listing`, `User`, `ListingStateService::transition`.
- Produces `App\Exceptions\DealException extends \RuntimeException`.
- Produces `App\Services\Gamification\DealService`:
  - `markSold(Listing $listing, User $seller, ?string $buyerUsername = null): ?Transaction` — asserts `$seller->id === $listing->user_id` and `$listing->state === 'published'` (throws DealException otherwise); transitions the listing `published→sold`; if `$buyerUsername` is a non-empty string, resolves it to a User — must exist, be email-verified, and not be the seller — and creates a `Transaction` (status `pending`, seller/buyer/listing, `amount_cents` = listing `price_cents`, `off_platform` = true). Returns the Transaction (or null if no buyer tagged). The whole thing runs in a DB transaction.
  - `confirm(Transaction $tx, User $buyer): void` — asserts `$buyer->id === $tx->buyer_user_id` and `$tx->status === 'pending'` (throws DealException otherwise); sets `status='completed'`, `completed_at=now()`. Row-locked.
  - `confirmedSalesCount(User $seller): int` — count of `Transaction where seller_user_id=$seller->id AND status='completed'`.
- Produces `TransactionFactory` with a `completed()` state.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;

it('marks a listing sold without a buyer tag', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    $tx = app(DealService::class)->markSold($listing, $seller, null);

    expect($tx)->toBeNull()
        ->and($listing->fresh()->state)->toBe('sold');
});

it('marks sold and creates a pending transaction when a buyer is tagged', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 5000]);

    $tx = app(DealService::class)->markSold($listing, $seller, 'koper');

    expect($tx->status)->toBe('pending')
        ->and($tx->buyer_user_id)->toBe($buyer->id)
        ->and($tx->seller_user_id)->toBe($seller->id)
        ->and($tx->amount_cents)->toBe(5000)
        ->and($listing->fresh()->state)->toBe('sold');
});

it('rejects marking someone elses listing, a non-published listing, and self/invalid buyer', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $published = Listing::factory()->published()->for($seller)->create();
    $draft = Listing::factory()->for($seller)->create(['state' => 'draft']);

    expect(fn () => app(DealService::class)->markSold($published, $stranger, null))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->markSold($draft, $seller, null))->toThrow(DealException::class);
    // self as buyer:
    $p2 = Listing::factory()->published()->for($seller)->create();
    expect(fn () => app(DealService::class)->markSold($p2, $seller, $seller->username))->toThrow(DealException::class);
    // unknown buyer:
    $p3 = Listing::factory()->published()->for($seller)->create();
    expect(fn () => app(DealService::class)->markSold($p3, $seller, 'nobody'))->toThrow(DealException::class);
});

it('lets the tagged buyer confirm, exactly once, and counts confirmed sales', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create();
    $tx = app(DealService::class)->markSold($listing, $seller, 'koper');

    app(DealService::class)->confirm($tx, $buyer);

    expect($tx->fresh()->status)->toBe('completed')
        ->and($tx->fresh()->completed_at)->not->toBeNull()
        ->and(app(DealService::class)->confirmedSalesCount($seller))->toBe(1);

    // a stranger cannot confirm; a second confirm is rejected
    expect(fn () => app(DealService::class)->confirm($tx->fresh(), User::factory()->create()))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->confirm($tx->fresh(), $buyer))->toThrow(DealException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=DealServiceTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write exception, service, factory, flag**

`app/Exceptions/DealException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DealException extends RuntimeException {}
```

`app/Services/Gamification/DealService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\DB;

class DealService
{
    public function __construct(private readonly ListingStateService $state) {}

    public function markSold(Listing $listing, User $seller, ?string $buyerUsername = null): ?Transaction
    {
        if ($seller->id !== $listing->user_id) {
            throw new DealException('Alleen de verkoper kan deze advertentie als verkocht markeren.');
        }
        if ($listing->state !== 'published') {
            throw new DealException('Alleen een gepubliceerde advertentie kan als verkocht worden gemarkeerd.');
        }

        return DB::transaction(function () use ($listing, $seller, $buyerUsername): ?Transaction {
            $buyer = null;
            if (is_string($buyerUsername) && trim($buyerUsername) !== '') {
                $buyer = User::query()->where('username', strtolower(trim($buyerUsername)))->first();
                if ($buyer === null || $buyer->email_verified_at === null) {
                    throw new DealException('Onbekende of niet-geverifieerde koper.');
                }
                if ($buyer->id === $seller->id) {
                    throw new DealException('Je kunt jezelf niet als koper opgeven.');
                }
            }

            $this->state->transition($listing, 'sold');

            if ($buyer === null) {
                return null;
            }

            return Transaction::query()->create([
                'listing_id' => $listing->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'amount_cents' => $listing->price_cents,
                'currency' => 'EUR',
                'status' => 'pending',
                'off_platform' => true,
            ]);
        });
    }

    public function confirm(Transaction $tx, User $buyer): void
    {
        DB::transaction(function () use ($tx, $buyer): void {
            /** @var Transaction $locked */
            $locked = Transaction::query()->lockForUpdate()->findOrFail($tx->id);

            if ($locked->buyer_user_id !== $buyer->id) {
                throw new DealException('Alleen de gemarkeerde koper kan deze deal bevestigen.');
            }
            if ($locked->status !== 'pending') {
                throw new DealException('Deze deal is al afgehandeld.');
            }

            $locked->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });
    }

    public function confirmedSalesCount(User $seller): int
    {
        return Transaction::query()
            ->where('seller_user_id', $seller->id)
            ->where('status', 'completed')
            ->count();
    }
}
```

`database/factories/TransactionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'seller_user_id' => User::factory(),
            'buyer_user_id' => User::factory(),
            'amount_cents' => 2500,
            'currency' => 'EUR',
            'status' => 'pending',
            'off_platform' => true,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'completed_at' => now()]);
    }
}
```

Add `use Illuminate\Database\Eloquent\Factories\HasFactory;` + `/** @use HasFactory<TransactionFactory> */ use HasFactory;` to `app/Models/Transaction.php` (it currently has no factory trait — add it, mirroring other models).

`config/cloudmarktplaats.php` — add to `features` after `trust_autopublish`:

```php
        'deals' => env('FEATURE_DEALS', true),
```

`.env.example` after `FEATURE_TRUST_AUTOPUBLISH=false`:

```
FEATURE_DEALS=true
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=DealServiceTest`
Expected: 4 passed.

- [ ] **Step 5: Pint --test + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Exceptions/DealException.php app/Services/Gamification/DealService.php database/factories/TransactionFactory.php app/Models/Transaction.php config/cloudmarktplaats.php .env.example tests/Feature/Gamification/DealServiceTest.php
git commit -m "Add DealService: seller mark-sold + buyer confirm (transaction feedback)"
```

---

### Task 2: Rewire TrustLevelService to count buyer-confirmed sales (the security gate)

**Files:**
- Modify: `app/Services/Gamification/TrustLevelService.php`
- Modify: `tests/Feature/Gamification/TrustLevelServiceTest.php` (fixtures → completed transactions)
- Modify: `tests/Feature/Gamification/ModerationSkipTest.php` (veteran fixtures → completed transactions)
- Modify: `tests/Feature/Gamification/TrustLevelDisplayTest.php` (veteran fixture → completed transactions)
- Test: (the three modified tests are the coverage)

**Interfaces:**
- Consumes: `DealService::confirmedSalesCount` (Task 1) — or query `Transaction` directly. Use the same count semantics: completed transactions where the user is the seller.
- Produces: `TrustLevelService::forUser` computes `sold` from **confirmed transactions** (`Transaction where seller_user_id=user AND status='completed'`), NOT `Listing where state='sold'`. Thresholds unchanged (trusted: 14d + 2; veteran: 30d + 5). `canSkipModeration` unchanged.

- [ ] **Step 1: Update the tests to the new source, watch them fail**

In `TrustLevelServiceTest.php`, replace every `Listing::factory()->sold()->for($u)->count(N)->create()` used to reach trusted/veteran with confirmed transactions:

```php
    // was: Listing::factory()->sold()->for($u)->count(2)->create();
    App\Models\Transaction::factory()->completed()->count(2)->create(['seller_user_id' => $u->id]);
```

Do the same for the veteran cases (count 5) in `TrustLevelServiceTest.php`, `ModerationSkipTest.php`, and `TrustLevelDisplayTest.php`. Keep the `sold()` listings ONLY where a test genuinely needs a sold *listing* for another reason; for reaching trust level, use completed transactions. The "never skips on karma alone" test stays (still zero sales/transactions).

Run: `docker compose exec -T php-fpm php artisan test --filter="TrustLevelService|ModerationSkip|TrustLevelDisplay"`
Expected: FAIL — the service still counts `state='sold'` listings, so users built with transactions are no longer veteran.

- [ ] **Step 2: (RED confirmed above)**

- [ ] **Step 3: Rewire the service**

In `app/Services/Gamification/TrustLevelService.php` `forUser()`, replace the sold-count line:

```php
        $sold = \App\Models\Transaction::query()
            ->where('seller_user_id', $user->id)
            ->where('status', 'completed')
            ->count();
```

(Add `use App\Models\Transaction;` and drop the now-unused `use App\Models\Listing;` if nothing else in the file uses it — run `pint --test` to catch an unused import. Update the class docblock: the "completed SALES" wording is now literally true — sales are buyer-confirmed transactions, so remove the "PRECONDITION ... no code writes state='sold'" warning added in 3a and replace with a line noting trust counts buyer-confirmed transactions, which cannot be self-marked.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec -T php-fpm php artisan test --filter="TrustLevelService|ModerationSkip|TrustLevelDisplay"`
Expected: all green. The anti-sockpuppet guarantee is now stronger: a seller cannot self-advance trust; a buyer must confirm.

- [ ] **Step 5: Pint --test + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Gamification/TrustLevelService.php tests/Feature/Gamification/TrustLevelServiceTest.php tests/Feature/Gamification/ModerationSkipTest.php tests/Feature/Gamification/TrustLevelDisplayTest.php
git commit -m "Trust counts buyer-confirmed transactions, not seller-set sold state (close farm gate)"
```

---

### Task 3: Seller mark-sold action on the listing detail

**Files:**
- Modify: `app/Livewire/Listings/Detail.php` (markSold action, owner-only)
- Modify: `resources/views/livewire/listings/detail.blade.php` (owner-only "markeer verkocht" form, flag-gated)
- Test: `tests/Feature/Gamification/MarkSoldUiTest.php`

**Interfaces:**
- Consumes: `DealService::markSold` (Task 1), flag `features.deals`.
- Produces: on the listing detail, when the viewer is the owner AND the listing is `published` AND `FEATURE_DEALS` is on, a form with an optional buyer-username field and a "Markeer als verkocht" button calling a Livewire `markSold()` action that delegates to `DealService`, catching `DealException` into a field error.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Listings\Detail;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('lets the owner mark their listing sold and tag a buyer', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->set('buyerUsername', 'koper')
        ->call('markSold')
        ->assertHasNoErrors();

    expect($listing->fresh()->state)->toBe('sold')
        ->and(Transaction::query()->where('buyer_user_id', $buyer->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('does not let a non-owner mark it sold', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=MarkSoldUiTest`
Expected: FAIL — no markSold action.

- [ ] **Step 3: Implement the action + view**

In `app/Livewire/Listings/Detail.php`, add a public property and action:

```php
    public string $buyerUsername = '';

    public function markSold(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->id === $this->listing->user_id, 403);

        try {
            app(\App\Services\Gamification\DealService::class)->markSold(
                $this->listing,
                $user,
                $this->buyerUsername !== '' ? $this->buyerUsername : null,
            );
        } catch (\App\Exceptions\DealException $e) {
            $this->addError('buyerUsername', $e->getMessage());

            return;
        }

        $this->listing->refresh();
    }
```

In `resources/views/livewire/listings/detail.blade.php`, add an owner-only block (near the existing owner/status area — after the status banner or in the info column). Only show when the viewer owns the listing, it's published, and the flag is on:

```blade
        @auth
            @if (auth()->id() === $listing->user_id && $listing->state === 'published' && config('cloudmarktplaats.features.deals'))
                <div class="mt-6 rounded-sm border border-cmp-border bg-cmp-surface p-4">
                    <div class="cmp-section-label mb-3">Verkocht?</div>
                    <p class="text-sm text-cmp-muted">Markeer als verkocht. Geef optioneel de gebruikersnaam van de koper op; die kan de deal dan bevestigen.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <input wire:model="buyerUsername" placeholder="gebruikersnaam koper (optioneel)" class="rounded-sm border-cmp-border p-2 text-sm focus:border-cmp-signal focus:ring-cmp-signal">
                        <button wire:click="markSold" wire:confirm="Advertentie als verkocht markeren?" class="cmp-btn cmp-btn-primary">Markeer als verkocht</button>
                    </div>
                    @error('buyerUsername') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        @endauth
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=MarkSoldUiTest`
Expected: 2 passed. Also run `--filter=BrowseDetail` (existing detail behavior stays green).

- [ ] **Step 5: Pint --test + PHPStan + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Listings/Detail.php resources/views/livewire/listings/detail.blade.php tests/Feature/Gamification/MarkSoldUiTest.php
git commit -m "Listing detail: owner can mark sold + tag a buyer (flag-gated)"
```

---

### Task 4: Buyer confirmation page `/profile/deals`

**Files:**
- Create: `app/Livewire/Profile/Deals.php`
- Create: `resources/views/livewire/profile/deals.blade.php`
- Modify: `routes/web.php` (route, auth, flag-gated in mount)
- Modify: `resources/views/livewire/profile/security.blade.php` (wayfinding link, flag-gated)
- Test: `tests/Feature/Gamification/DealsPageTest.php`

**Interfaces:**
- Consumes: `DealService::confirm` (Task 1), `Transaction` (buyer relation), flag `features.deals`.
- Produces: route `GET /profile/deals` (auth, flag-gated in mount → 404 when off), name `profile.deals`, Livewire `App\Livewire\Profile\Deals` with a `confirm(int $id)` action. Lists the authenticated user's PENDING transactions as buyer; confirming delegates to `DealService::confirm`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Profile\Deals;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('lists the buyer\'s pending deals and confirms one', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->create(['state' => 'sold', 'title' => 'Dell R720']);
    $tx = Transaction::factory()->create([
        'listing_id' => $listing->id, 'seller_user_id' => $seller->id,
        'buyer_user_id' => $buyer->id, 'status' => 'pending',
    ]);

    Livewire::actingAs($buyer)
        ->test(Deals::class)
        ->assertSee('Dell R720')
        ->call('confirm', $tx->id)
        ->assertHasNoErrors();

    expect($tx->fresh()->status)->toBe('completed');
});

it('does not let a user confirm a deal that is not theirs', function () {
    $tx = Transaction::factory()->create(['status' => 'pending']);
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(Deals::class)
        ->call('confirm', $tx->id)
        ->assertForbidden();
});

it('404s when the deals feature is off', function () {
    config()->set('cloudmarktplaats.features.deals', false);
    Livewire::actingAs(User::factory()->create())->test(Deals::class)->assertStatus(404);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm php artisan test --filter=DealsPageTest`
Expected: FAIL — component/route missing.

- [ ] **Step 3: Component, view, route, wayfinding**

`app/Livewire/Profile/Deals.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Exceptions\DealException;
use App\Models\Transaction;
use App\Services\Gamification\DealService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketing', ['title' => 'Mijn deals — Cloudmarktplaats'])]
class Deals extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 404);
    }

    public function confirm(int $id): void
    {
        $tx = Transaction::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user !== null && $tx->buyer_user_id === $user->id, 403);

        try {
            app(DealService::class)->confirm($tx, $user);
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());
        }
    }

    /** @return Collection<int, Transaction> */
    public function pending(): Collection
    {
        return Transaction::query()
            ->where('buyer_user_id', (int) auth()->id())
            ->where('status', 'pending')
            ->with('listing')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.profile.deals', ['pending' => $this->pending()]);
    }
}
```

`resources/views/livewire/profile/deals.blade.php`:

```blade
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">Vertrouwen</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">Mijn deals</h1>
    <p class="mt-3 text-sm text-cmp-muted">Een verkoper heeft jou als koper gemarkeerd. Bevestig de deals die klopten.</p>
    @error('deal') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

    <div class="mt-8 space-y-2">
        @forelse ($pending as $tx)
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div>
                    <span class="text-sm text-cmp-text">{{ $tx->listing?->title ?? 'Advertentie' }}</span>
                    <span class="ml-3 font-mono text-[11px] text-cmp-faint">€ {{ number_format($tx->amount_cents / 100, 2, ',', '.') }}</span>
                </div>
                <button wire:click="confirm({{ $tx->id }})" class="cmp-btn cmp-btn-primary">Deal bevestigen</button>
            </div>
        @empty
            <p class="text-sm text-cmp-muted">Geen openstaande deals om te bevestigen.</p>
        @endforelse
    </div>
</div>
```

Route in `routes/web.php`, next to `/profile/stats`:

```php
Route::get('/profile/deals', \App\Livewire\Profile\Deals::class)
    ->middleware('auth')
    ->name('profile.deals');
```

Wayfinding link in `security.blade.php`, mirroring the stats/invites links, flag-gated by `@if (config('cloudmarktplaats.features.deals'))` linking to `route('profile.deals')` with label "Mijn deals".

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm php artisan test --filter=DealsPageTest`
Expected: 3 passed.

- [ ] **Step 5: Pint --test + PHPStan + full suite + commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pint --dirty
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T php-fpm php artisan test
git add app/Livewire/Profile/Deals.php resources/views/livewire/profile/deals.blade.php routes/web.php resources/views/livewire/profile/security.blade.php tests/Feature/Gamification/DealsPageTest.php
git commit -m "Add /profile/deals: buyer confirms tagged transactions"
```

Expected: full suite green (246 existing, adjusted, + ~13 new).

---

### Task 5: Deploy

Ops checklist (CT at 192.168.178.215, /opt/cloudmarktplaats):

- [ ] `npm run build` locally (detail + profile views changed).
- [ ] `./vendor/bin/pint --test` locally MUST be clean before pushing (CI lint gate).
- [ ] Merge to `main`, `git push origin main`, confirm CI green.
- [ ] `rsync -az --delete --exclude node_modules --exclude vendor --exclude .env --exclude storage --exclude bootstrap/cache --exclude .superpowers /mnt/nvme1tb/projects/cloudmarktplaats/ root@192.168.178.215:/opt/cloudmarktplaats/`
- [ ] On the CT: `docker compose -f docker-compose.prod.yml exec -T php-fpm sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:clear && php artisan view:cache'` (no migrations — transactions table already exists).
- [ ] Verify: as a seller, open your published listing, mark sold + tag a buyer username; as that buyer, open /profile/deals and confirm; check the seller's trust/veteran progression counts the confirmed deal (Filament users table trust column).
- [ ] **Only now is `FEATURE_TRUST_AUTOPUBLISH` safe to enable** (buyer-confirmed sales exist). Enabling remains Nick's explicit call: set `FEATURE_TRUST_AUTOPUBLISH=true` in the CT `.env` + `php artisan config:cache`. Residual risk to weigh first: a seller colluding with sockpuppet *buyer* accounts can still farm veteran (costlier than self-marking); consider per-seller confirmed-deal velocity caps before relying on auto-publish at scale.
