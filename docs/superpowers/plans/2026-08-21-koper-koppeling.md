# Koper koppelen aan een verkoop — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een verkoper meldt een verkoop met één knop en krijgt een claim-link; de koper — ook een die anoniem mailde en geen account had — bevestigt die deal met één klik.

**Architecture:** `transactions` krijgt een nullable `buyer_user_id` plus een `claim_token`, zodat elke gemelde verkoop een rij is en de koper pas bij het bevestigen wordt ingevuld. `DealService` houdt alle statusovergangen onder `lockForUpdate()`, precies zoals nu. Een nieuwe publieke Livewire-pagina `/deal/{token}` doet claimen en weigeren in één handeling.

**Tech Stack:** Laravel 11, Livewire 3, Alpine (meegeleverd met Livewire), Postgres 16, Pest, Tailwind. Alles draait in Docker.

## Global Constraints

- Werk op branch `feature/koper-koppeling`. Die bestaat al en bevat het spec-document.
- Artisan en tests draaien **altijd** in de container: `docker compose exec -T php-fpm ...`. Buiten Docker faalt artisan op de log (`storage/` is van uid 82).
- Drie poorten groen vóór elke deploy: `./vendor/bin/pest`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=512M`.
- Nederlands in alle gebruikersgerichte teksten. Kort, tweede persoon, geen marketingtaal. Elk scherm legt uit wat het is en wat het oplevert.
- Geen nieuwe dependencies. Geen externe API's.
- `declare(strict_types=1);` bovenaan elk PHP-bestand, zoals de rest van de codebase.
- Commitberichten in het Engels, in de imperatief, zoals de bestaande git-historie. **Nick is enig auteur — geen Co-Authored-By-regel.**
- Spec: `docs/superpowers/specs/2026-08-21-koper-koppeling-design.md`.

---

## File Structure

**Nieuw:**
- `database/migrations/2026_08_21_000100_transactions_nullable_buyer_and_claim_token.php` — schema
- `app/Livewire/Deals/Claim.php` — de claim-pagina
- `resources/views/livewire/deals/claim.blade.php` — de claim-pagina
- `resources/views/components/deals/claim-link.blade.php` — één claim-link met kopieerknop
- `tests/Feature/Gamification/ClaimLinkTest.php` — service-gedrag rond de token
- `tests/Feature/Gamification/ClaimPageTest.php` — de pagina

**Gewijzigd:**
- `app/Services/Gamification/DealService.php` — `markSold` zonder gebruikersnaam, plus `claim` / `decline` / `refreshClaimToken` / `openClaims`
- `app/Models/Transaction.php`, `database/factories/TransactionFactory.php`
- `app/Livewire/Listings/Detail.php` + `resources/views/livewire/listings/detail.blade.php`
- `app/Livewire/Listings/Mine.php` + `resources/views/livewire/listings/mine.blade.php`
- `app/Livewire/Profile/Deals.php` + `resources/views/livewire/profile/deals.blade.php`
- `app/Livewire/ContactSeller.php` + `resources/views/livewire/contact-seller.blade.php`
- `app/Mail/SellerContactMail.php` + `resources/views/emails/seller-contact.blade.php`
- `app/Services/Ops/IntegrityReport.php` + `resources/views/emails/daily-integrity.blade.php`
- `routes/web.php`
- `docs/known-gaps.md`, `AGENTS.md`
- Bestaande tests: `DealServiceTest.php`, `MarkSoldUiTest.php`, `DealsPageTest.php`

---

### Task 1: Schema — een verkoop zonder koper mag bestaan

**Files:**
- Create: `database/migrations/2026_08_21_000100_transactions_nullable_buyer_and_claim_token.php`
- Modify: `app/Models/Transaction.php`
- Modify: `database/factories/TransactionFactory.php`
- Test: `tests/Feature/Gamification/ClaimLinkTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: kolommen `transactions.claim_token` (string 32, nullable, unique) en `transactions.claim_expires_at` (timestamp, nullable); `transactions.buyer_user_id` is nullable. `TransactionFactory::unclaimed()` levert een rij zonder koper mét geldige token.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Gamification/ClaimLinkTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Transaction;
use App\Models\User;

it('stores a sale that has no buyer yet', function () {
    $tx = Transaction::factory()->unclaimed()->create();

    expect($tx->buyer_user_id)->toBeNull()
        ->and($tx->status)->toBe('pending')
        ->and(strlen((string) $tx->claim_token))->toBe(32)
        ->and($tx->claim_expires_at->isFuture())->toBeTrue();
});

it('still refuses buyer == seller at the database level', function () {
    $u = User::factory()->create();

    expect(fn () => Transaction::factory()->create(['buyer_user_id' => $u->id, 'seller_user_id' => $u->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimLinkTest.php
```

Verwacht: FAIL — `Call to undefined method ...::unclaimed()`.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_08_21_000100_transactions_nullable_buyer_and_claim_token.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een gemelde verkoop zonder bekende koper is vanaf nu een geldige rij.
     *
     * De contact-relay is bewust anoniem: een koper heeft geen account nodig
     * en onthult alleen een e-mailadres. De verkoper kan zijn gebruikersnaam
     * dus niet kennen. In plaats daarvan legt elke melding een transactie vast
     * met een claim-token; de koper vult zichzelf in door die link te openen.
     *
     * De CHECK `transactions_buyer_ne_seller` blijft ongemoeid: in Postgres
     * slaagt een CHECK die op NULL uitkomt.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions ALTER COLUMN buyer_user_id DROP NOT NULL');

        Schema::table('transactions', function (Blueprint $t) {
            $t->string('claim_token', 32)->nullable()->unique();
            $t->timestamp('claim_expires_at')->nullable();
        });
    }

    /**
     * Terugdraaien faalt zolang er verkopen zonder koper staan. Dat is
     * opzettelijk: die rijen weggooien om een migratie te laten slagen is
     * geen beslissing die een migratie mag nemen.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropColumn(['claim_token', 'claim_expires_at']);
        });

        DB::statement('ALTER TABLE transactions ALTER COLUMN buyer_user_id SET NOT NULL');
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/Transaction.php`, voeg de twee kolommen toe aan `$fillable` (direct na `'external_tx_ref'`):

```php
        'external_tx_ref',
        'claim_token',
        'claim_expires_at',
```

En in `casts()`:

```php
    protected function casts(): array
    {
        return [
            'off_platform' => 'boolean',
            'completed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
        ];
    }
```

Werk de docblock van `buyer()` bij zodat de nullable relatie geen verrassing is:

```php
    /**
     * De koper. Null zolang de verkoop nog niet geclaimd is — melden legt
     * de deal vast, bevestigen vult de koper in.
     *
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
```

- [ ] **Step 5: Add the factory state**

In `database/factories/TransactionFactory.php`, voeg `Illuminate\Support\Str` toe aan de imports en zet deze state onder `completed()`:

```php
    /** Een gemelde verkoop die nog op een koper wacht. */
    public function unclaimed(): static
    {
        return $this->state(fn () => [
            'buyer_user_id' => null,
            'claim_token' => Str::random(32),
            'claim_expires_at' => now()->addDays(30),
        ]);
    }
```

- [ ] **Step 6: Run test to verify it passes**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimLinkTest.php
```

Verwacht: PASS, 2 tests.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_21_000100_transactions_nullable_buyer_and_claim_token.php \
        app/Models/Transaction.php database/factories/TransactionFactory.php \
        tests/Feature/Gamification/ClaimLinkTest.php
git commit -m "Let a recorded sale exist before its buyer does"
```

---

### Task 2: markSold legt altijd een verkoop vast

**Files:**
- Modify: `app/Services/Gamification/DealService.php`
- Test: `tests/Feature/Gamification/DealServiceTest.php` (herschrijven), `tests/Feature/Gamification/ClaimLinkTest.php` (aanvullen)

**Interfaces:**
- Consumes: `TransactionFactory::unclaimed()`, kolommen uit Task 1.
- Produces:
  - `DealService::markSold(Listing $listing, User $seller): Transaction` — géén gebruikersnaam-parameter meer, geeft altijd een `Transaction` terug (niet `?Transaction`).
  - `DealService::CLAIM_DAYS` (int, 30).
  - `DealService::openClaims(Listing $listing): Illuminate\Database\Eloquent\Collection<int, Transaction>` — openstaande, nog niet geclaimde verkopen van één advertentie, oplopend op id.

- [ ] **Step 1: Rewrite the failing tests**

Vervang in `tests/Feature/Gamification/DealServiceTest.php` de eerste drie tests én de race-test door onderstaande. Laat de laatste test (`is blocked at the database level ...`) staan — die is nu ook in `ClaimLinkTest` gedekt en mag hier weg; verwijder hem hier om dubbeling te voorkomen. Het bestand wordt daarmee:

```php
<?php

declare(strict_types=1);

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;

it('records every reported sale, buyer or no buyer', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 5000]);

    $tx = app(DealService::class)->markSold($listing, $seller);

    expect($tx->status)->toBe('pending')
        ->and($tx->buyer_user_id)->toBeNull()
        ->and($tx->seller_user_id)->toBe($seller->id)
        ->and($tx->amount_cents)->toBe(5000)
        ->and($tx->claim_token)->not->toBeNull()
        ->and($listing->fresh()->state)->toBe('sold');
});

it('rejects marking someone elses listing or a non-published one', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $published = Listing::factory()->published()->for($seller)->create();
    $draft = Listing::factory()->for($seller)->create(['state' => 'draft']);

    expect(fn () => app(DealService::class)->markSold($published, $stranger))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->markSold($draft, $seller))->toThrow(DealException::class);
});

it('counts down quantity instead of closing the listing, and hands out one link per unit', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['quantity' => 2]);

    $first = app(DealService::class)->markSold($listing, $seller);
    expect($listing->fresh()->state)->toBe('published')
        ->and($listing->fresh()->quantity)->toBe(1);

    $second = app(DealService::class)->markSold($listing->fresh(), $seller);
    expect($listing->fresh()->state)->toBe('sold')
        ->and($first->claim_token)->not->toBe($second->claim_token)
        ->and(app(DealService::class)->openClaims($listing)->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('rejects a second markSold on a sold listing (sequential proxy for the concurrent race)', function () {
    // De echte race is twee gelijktijdige markSold-aanroepen die allebei
    // state='published' zien voordat een van beide commit. Twee keer achter
    // elkaar aanroepen raakt dezelfde bewaking: de tweede aanroep leest de rij
    // opnieuw onder lockForUpdate() (nu 'sold') en moet hem weigeren. Dat
    // bewijst dat de controle onder lock gebeurt en niet op het mogelijk
    // verouderde $listing dat de aanroeper meegaf.
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    app(DealService::class)->markSold($listing, $seller);

    expect(fn () => app(DealService::class)->markSold($listing, $seller))->toThrow(DealException::class);
    expect($listing->fresh()->state)->toBe('sold')
        ->and(Transaction::query()->count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/DealServiceTest.php
```

Verwacht: FAIL — `markSold()` verwacht nog een derde argument en `openClaims()` bestaat niet.

- [ ] **Step 3: Rewrite markSold and add openClaims**

In `app/Services/Gamification/DealService.php`: vervang de `use`-regels bovenaan door onderstaande set (de `User`-lookup op username verdwijnt, `Str` en `Collection` komen erbij):

```php
use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
```

Vervang de hele methode `markSold()` door:

```php
    /** Hoeveel dagen een claim-link bruikbaar blijft. */
    public const CLAIM_DAYS = 30;

    /**
     * Meld een verkoop. Levert altijd een transactie op, ook zonder koper.
     *
     * De verkoper kán de koper niet aanwijzen: de contact-relay is anoniem en
     * geeft hem alleen een e-mailadres. Daarom legt melden de verkoop vast met
     * een claim-token, en vult de koper zichzelf later in via die link.
     */
    public function markSold(Listing $listing, User $seller): Transaction
    {
        if ($seller->id !== $listing->user_id) {
            throw new DealException('Alleen de verkoper kan deze advertentie als verkocht markeren.');
        }

        return DB::transaction(function () use ($listing, $seller): Transaction {
            /** @var Listing $locked */
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->state !== 'published') {
                throw new DealException('Alleen een gepubliceerde advertentie kan als verkocht worden gemarkeerd.');
            }

            // Eén exemplaar verkopen is niet hetzelfde als de advertentie
            // sluiten. Staan er nog meer, dan gaat er eentje af en blijft hij
            // gewoon staan; pas bij de laatste gaat hij op `sold`.
            if ($locked->quantity > 1) {
                $locked->decrement('quantity');
            } else {
                $this->state->transition($locked, 'sold');
            }

            return Transaction::query()->create([
                'listing_id' => $locked->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => null,
                'amount_cents' => $locked->price_cents,
                'currency' => 'EUR',
                'status' => 'pending',
                'off_platform' => true,
                'claim_token' => Str::random(32),
                'claim_expires_at' => now()->addDays(self::CLAIM_DAYS),
            ]);
        });
    }

    /**
     * Gemelde verkopen van deze advertentie die nog op een koper wachten.
     *
     * @return Collection<int, Transaction>
     */
    public function openClaims(Listing $listing): Collection
    {
        return Transaction::query()
            ->where('listing_id', $listing->id)
            ->where('status', 'pending')
            ->whereNull('buyer_user_id')
            ->orderBy('id')
            ->get();
    }
```

Laat `confirm()` en `confirmedSalesCount()` ongewijzigd staan.

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/DealServiceTest.php tests/Feature/Gamification/ClaimLinkTest.php
```

Verwacht: PASS. De rest van de suite is nu tijdelijk stuk (`Detail::markSold` geeft nog drie argumenten mee) — dat is Task 5.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gamification/DealService.php tests/Feature/Gamification/DealServiceTest.php
git commit -m "Record a sale on every report, with a claim token instead of a guessed username"
```

---

### Task 3: Claimen, weigeren en een nieuwe link

**Files:**
- Modify: `app/Services/Gamification/DealService.php`
- Test: `tests/Feature/Gamification/ClaimLinkTest.php`

**Interfaces:**
- Consumes: `DealService::markSold()`, `DealService::CLAIM_DAYS`.
- Produces:
  - `DealService::claim(string $token, User $buyer): Transaction` — vult koper in én zet `completed` + `completed_at`, in één DB-transactie.
  - `DealService::decline(string $token, User $buyer): Transaction` — zet `cancelled`.
  - `DealService::refreshClaimToken(Transaction $tx, User $seller): Transaction` — nieuwe token, nieuwe vervaldatum.
  - Alle drie gooien `App\Exceptions\DealException` met een Nederlandse boodschap die rechtstreeks aan de gebruiker getoond mag worden.

- [ ] **Step 1: Write the failing tests**

Voeg toe aan `tests/Feature/Gamification/ClaimLinkTest.php` (en breid de imports uit met `App\Exceptions\DealException`, `App\Models\Listing` en `App\Services\Gamification\DealService`):

```php
/** Een gemelde verkoop van een verse verkoper, klaar om geclaimd te worden. */
function reportedSale(?User $seller = null): Transaction
{
    $seller ??= User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 4500]);

    return app(DealService::class)->markSold($listing, $seller);
}

it('lets the buyer claim and confirm in one go, and counts it for the seller', function () {
    $tx = reportedSale();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    $claimed = app(DealService::class)->claim((string) $tx->claim_token, $buyer);

    expect($claimed->status)->toBe('completed')
        ->and($claimed->buyer_user_id)->toBe($buyer->id)
        ->and($claimed->completed_at)->not->toBeNull()
        ->and(app(DealService::class)->confirmedSalesCount($claimed->seller))->toBe(1);
});

it('does not count an unclaimed sale towards the seller', function () {
    $tx = reportedSale();

    expect(app(DealService::class)->confirmedSalesCount($tx->seller))->toBe(0);
});

it('refuses a second claim on the same token', function () {
    $tx = reportedSale();
    app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect(fn () => app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create()))
        ->toThrow(DealException::class, 'Deze deal is al bevestigd.');
});

it('refuses an unknown, an expired and a self-claimed token', function () {
    expect(fn () => app(DealService::class)->claim('nope', User::factory()->create()))
        ->toThrow(DealException::class, 'Deze link kennen we niet.');

    $seller = User::factory()->create();
    $expired = reportedSale($seller);
    $expired->forceFill(['claim_expires_at' => now()->subDay()])->save();
    expect(fn () => app(DealService::class)->claim((string) $expired->claim_token, User::factory()->create()))
        ->toThrow(DealException::class, 'Deze link is verlopen. Vraag de verkoper om een nieuwe.');

    $own = reportedSale($seller);
    expect(fn () => app(DealService::class)->claim((string) $own->claim_token, $seller))
        ->toThrow(DealException::class, 'Je kunt je eigen verkoop niet bevestigen.');
});

it('cancels the deal when the buyer says it was not them', function () {
    $tx = reportedSale();
    $listing = $tx->listing;

    $declined = app(DealService::class)->decline((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect($declined->status)->toBe('cancelled')
        ->and($declined->buyer_user_id)->toBeNull()
        // Weigeren zet de advertentie niet terug op published: of er nog iets
        // te koop staat bepaalt de verkoper, niet de koper die zegt dat hij
        // het niet was.
        ->and($listing?->fresh()->state)->toBe('sold');
});

it('gives the seller a fresh link when the old one expired', function () {
    $seller = User::factory()->create();
    $tx = reportedSale($seller);
    $old = (string) $tx->claim_token;
    $tx->forceFill(['claim_expires_at' => now()->subDay()])->save();

    $refreshed = app(DealService::class)->refreshClaimToken($tx, $seller);

    expect($refreshed->claim_token)->not->toBe($old)
        ->and($refreshed->claim_expires_at->isFuture())->toBeTrue();

    $buyer = User::factory()->create(['email_verified_at' => now()]);
    expect(app(DealService::class)->claim((string) $refreshed->claim_token, $buyer)->status)->toBe('completed');
});

it('does not let a stranger refresh someone elses link', function () {
    $tx = reportedSale();

    expect(fn () => app(DealService::class)->refreshClaimToken($tx, User::factory()->create()))
        ->toThrow(DealException::class, 'Alleen de verkoper kan een nieuwe link maken.');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimLinkTest.php
```

Verwacht: FAIL — `Call to undefined method App\Services\Gamification\DealService::claim()`.

- [ ] **Step 3: Implement claim, decline and refreshClaimToken**

Voeg in `app/Services/Gamification/DealService.php` toe, direct onder `openClaims()`:

```php
    /**
     * De koper vult zichzelf in en bevestigt, in één handeling.
     *
     * Een tussenstap via /profile/deals zou friction zonder doel zijn: wie de
     * link opent en op "ja" klikt zegt precies wat we willen weten.
     */
    public function claim(string $token, User $buyer): Transaction
    {
        return DB::transaction(function () use ($token, $buyer): Transaction {
            $tx = $this->lockClaimable($token, $buyer);

            $tx->forceFill([
                'buyer_user_id' => $buyer->id,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            return $tx;
        });
    }

    /**
     * "Nee, dit klopt niet." Zonder deze uitweg is een claim-link een
     * eenrichtingsclaim en kan een verkoper er ongestraft mee strooien.
     */
    public function decline(string $token, User $buyer): Transaction
    {
        return DB::transaction(function () use ($token, $buyer): Transaction {
            $tx = $this->lockClaimable($token, $buyer);

            $tx->forceFill(['status' => 'cancelled'])->save();

            return $tx;
        });
    }

    /** Verlopen link? De verkoper maakt een nieuwe, anders zit hij op dag 31 klem. */
    public function refreshClaimToken(Transaction $tx, User $seller): Transaction
    {
        if ($tx->seller_user_id !== $seller->id) {
            throw new DealException('Alleen de verkoper kan een nieuwe link maken.');
        }
        if ($tx->status !== 'pending') {
            throw new DealException('Deze deal is al afgehandeld.');
        }

        $tx->forceFill([
            'claim_token' => Str::random(32),
            'claim_expires_at' => now()->addDays(self::CLAIM_DAYS),
        ])->save();

        return $tx;
    }

    /**
     * De token blijft na afhandeling staan, zodat een tweede klik "al
     * bevestigd" kan zeggen in plaats van "onbekende link". De status is wat
     * telt, niet het bestaan van de token.
     */
    private function lockClaimable(string $token, User $buyer): Transaction
    {
        $tx = Transaction::query()->lockForUpdate()->where('claim_token', $token)->first();

        if ($tx === null) {
            throw new DealException('Deze link kennen we niet.');
        }
        if ($tx->status === 'completed') {
            throw new DealException('Deze deal is al bevestigd.');
        }
        if ($tx->status === 'cancelled') {
            throw new DealException('Deze deal is al afgewezen.');
        }
        if ($tx->claim_expires_at?->isPast() ?? false) {
            throw new DealException('Deze link is verlopen. Vraag de verkoper om een nieuwe.');
        }
        if ($tx->seller_user_id === $buyer->id) {
            throw new DealException('Je kunt je eigen verkoop niet bevestigen.');
        }

        return $tx;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimLinkTest.php
```

Verwacht: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gamification/DealService.php tests/Feature/Gamification/ClaimLinkTest.php
git commit -m "Let the buyer claim a reported sale, or say it was not them"
```

---

### Task 4: De claim-pagina op /deal/{token}

**Files:**
- Create: `app/Livewire/Deals/Claim.php`
- Create: `resources/views/livewire/deals/claim.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Gamification/ClaimPageTest.php`

**Interfaces:**
- Consumes: `DealService::claim()`, `DealService::decline()`.
- Produces: benoemde route `deals.claim` met parameter `token`; Livewire-component `App\Livewire\Deals\Claim` met publieke methoden `confirm()` en `decline()` en publieke property `string $done` (`''` | `'confirmed'` | `'declined'`).

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Gamification/ClaimPageTest.php`:

```php
<?php

declare(strict_types=1);

use App\Livewire\Deals\Claim;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

function saleWithToken(): string
{
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['title' => 'Dell R720']);

    return (string) app(DealService::class)->markSold($listing, $seller)->claim_token;
}

it('shows a verified buyer what they are confirming, and confirms it', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->assertSee('Dell R720')
        ->assertSee('Het is geen betaling en geen verplichting.')
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('done', 'confirmed');
});

it('lets the buyer decline', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->call('decline')
        ->assertSet('done', 'declined');
});

it('parks the url for a guest so login brings them back here', function () {
    $token = saleWithToken();

    $this->get("/deal/{$token}")
        ->assertOk()
        ->assertSee('Inloggen of registreren');

    expect(session('url.intended'))->toBe(route('deals.claim', ['token' => $token]));
});

it('refuses to confirm on an unverified account', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => null]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->call('confirm')
        ->assertForbidden();
});

it('explains an unknown link instead of 404ing', function () {
    $this->get('/deal/'.str_repeat('x', 32))
        ->assertOk()
        ->assertSee('Deze link kennen we niet');
});

it('404s when the deals feature is off', function () {
    config()->set('cloudmarktplaats.features.deals', false);

    $this->get('/deal/'.saleWithToken())->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimPageTest.php
```

Verwacht: FAIL — `Class "App\Livewire\Deals\Claim" not found`.

- [ ] **Step 3: Write the component**

`app/Livewire/Deals/Claim.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Deals;

use App\Exceptions\DealException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De koperskant van een gemelde verkoop.
 *
 * Deze pagina wordt koud geopend via een link die de verkoper zelf in zijn
 * antwoordmail heeft geplakt — wij kennen het adres van de koper niet en
 * kunnen hem dus niet mailen (`contact_relay_logs` bewaart bewust alleen
 * listing_id + tijdstip). De pagina is publiek bereikbaar, maar bevestigen en
 * weigeren vereisen een geverifieerd account: dezelfde lat als bij invites.
 */
#[Layout('components.layouts.marketing', ['title' => 'Deal bevestigen — Cloudmarktplaats'])]
class Claim extends Component
{
    public string $token = '';

    /** '' zolang er nog een keuze te maken valt, daarna 'confirmed' of 'declined'. */
    public string $done = '';

    public function mount(string $token): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 404);

        $this->token = $token;

        // Een gast komt hier binnen zonder sessie. Parkeer de URL zodat
        // inloggen én registreren hem op deze pagina terugzetten.
        if (! auth()->check()) {
            session()->put('url.intended', route('deals.claim', ['token' => $token]));
        }
    }

    public function confirm(): void
    {
        try {
            app(DealService::class)->claim($this->token, $this->verifiedUser());
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());

            return;
        }

        $this->done = 'confirmed';
    }

    public function decline(): void
    {
        try {
            app(DealService::class)->decline($this->token, $this->verifiedUser());
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());

            return;
        }

        $this->done = 'declined';
    }

    private function verifiedUser(): User
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->hasVerifiedEmail(), 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.deals.claim', [
            'transaction' => Transaction::query()
                ->with(['listing', 'seller'])
                ->where('claim_token', $this->token)
                ->first(),
        ]);
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/livewire/deals/claim.blade.php`:

```blade
<div class="mx-auto max-w-xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Vertrouwen') }}</div>

    @if ($transaction === null)
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Deze link kennen we niet') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('Misschien is er iets misgegaan bij het kopiëren. Vraag de verkoper om de link opnieuw te sturen.') }}
        </p>
    @elseif ($done === 'confirmed')
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Bevestigd. Bedankt.') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('De verkoop staat nu op naam van de verkoper. Je vindt deze deal terug bij Mijn deals.') }}
        </p>
        <a href="{{ route('profile.deals') }}" class="cmp-btn cmp-btn-secondary mt-6">{{ __('Naar Mijn deals') }}</a>
    @elseif ($done === 'declined')
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Genoteerd.') }}</h1>
        <p class="mt-3 text-sm text-cmp-muted">
            {{ __('We hebben vastgelegd dat deze deal niet doorging. Er telt niets mee voor de verkoper.') }}
        </p>
    @else
        <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Deal bevestigen') }}</h1>

        <p class="mt-4 text-cmp-text">
            {{ '@'.($transaction->seller?->username ?? '?') }}
            {{ __('geeft aan dat je') }}
            <strong>{{ $transaction->listing?->title ?? __('een advertentie') }}</strong>
            {{ __('van hem hebt gekocht voor') }}
            <span class="font-mono">€ {{ number_format($transaction->amount_cents / 100, 2, ',', '.') }}</span>.
        </p>

        <p class="mt-4 text-sm text-cmp-muted">
            {{ __('Bevestigen betekent alleen dat de deal is doorgegaan. Het is geen betaling en geen verplichting. Voor jou verandert er niets, behalve dat de verkoper een bevestigde verkoop op zijn naam krijgt — dat is hoe hier vertrouwen wordt opgebouwd.') }}
        </p>

        @error('deal') <p class="mt-4 text-sm text-red-600">{{ $message }}</p> @enderror

        @guest
            <p class="mt-6 text-sm text-cmp-muted">
                {{ __('Bevestigen kan alleen met een account, anders weten we niet wie het zegt.') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('login') }}" class="cmp-btn cmp-btn-primary">{{ __('Inloggen of registreren') }}</a>
            </div>
        @endguest

        @auth
            @if (! auth()->user()->hasVerifiedEmail())
                <p class="mt-6 text-sm text-cmp-muted">
                    {{ __('Bevestig eerst je e-mailadres. Daarna kun je hier terugkomen via dezelfde link.') }}
                </p>
                <a href="{{ route('verification.notice') }}" class="cmp-btn cmp-btn-primary mt-3">{{ __('E-mailadres bevestigen') }}</a>
            @else
                <div class="mt-6 flex flex-wrap gap-2">
                    <button wire:click="confirm" class="cmp-btn cmp-btn-primary">{{ __('Ja, dat klopt') }}</button>
                    <button wire:click="decline" class="cmp-btn cmp-btn-ghost">{{ __('Nee, dit klopt niet') }}</button>
                </div>
            @endif
        @endauth
    @endif
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`: voeg bij de overige `use`-regels toe

```php
use App\Livewire\Deals\Claim as DealClaim;
```

en zet de route direct onder de `/profile/deals`-route:

```php
// De koperskant van een gemelde verkoop. Publiek bereikbaar omdat de koper
// hier koud binnenkomt via een link uit de mail van de verkoper — vaak zonder
// account. Bevestigen zelf eist auth + verified; dat bewaakt de component.
Route::get('/deal/{token}', DealClaim::class)
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('deals.claim');
```

- [ ] **Step 6: Send verified users back where they came from**

In `routes/web.php`, in de `verification.verify`-route: vervang `return redirect('/');` door `return redirect()->intended('/');`, met deze toelichting erboven:

```php
    // `intended` in plaats van een vaste '/': wie via een claim-link
    // registreert, hoort na het verifiëren terug te komen op die deal.
    return redirect()->intended('/');
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/ClaimPageTest.php
```

Verwacht: PASS, 6 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Deals/Claim.php resources/views/livewire/deals/claim.blade.php \
        routes/web.php tests/Feature/Gamification/ClaimPageTest.php
git commit -m "Add the buyer-facing claim page at /deal/{token}"
```

---

### Task 5: Het verkoperspaneel — één knop, dan de link

**Files:**
- Modify: `app/Livewire/Listings/Detail.php`
- Modify: `resources/views/livewire/listings/detail.blade.php:34-46`
- Create: `resources/views/components/deals/claim-link.blade.php`
- Modify: `app/Livewire/Listings/Mine.php`
- Modify: `resources/views/livewire/listings/mine.blade.php:82-86`
- Test: `tests/Feature/Gamification/MarkSoldUiTest.php` (herschrijven), `tests/Feature/Listings/MarkSoldFromMineTest.php` (aanvullen)

**Interfaces:**
- Consumes: `DealService::markSold()`, `DealService::openClaims()`, `DealService::refreshClaimToken()`, route `deals.claim`.
- Produces: `Detail::markSold(): void` zonder parameters; `Detail::newLink(int $transactionId): void`; de view krijgt `$openClaims` (`Collection<int, Transaction>`). `Mine` geeft de view `$openClaimListingIds` (`list<int>`).

- [ ] **Step 1: Rewrite the failing tests**

Vervang `tests/Feature/Gamification/MarkSoldUiTest.php` volledig door:

```php
<?php

declare(strict_types=1);

use App\Livewire\Listings\Detail;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

/** @return array{0: User, 1: Listing} */
function sellerWithListing(): array
{
    $seller = User::factory()->create();

    return [$seller, Listing::factory()->published()->for($seller)->create()];
}

it('marks the listing sold with one button and shows the claim link afterwards', function () {
    [$seller, $listing] = sellerWithListing();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertHasNoErrors()
        ->assertSee('Stuur de koper deze link');

    $tx = Transaction::query()->sole();

    expect($listing->fresh()->state)->toBe('sold')
        ->and($tx->buyer_user_id)->toBeNull()
        ->and($tx->claim_token)->not->toBeNull();
});

it('keeps showing the panel on a sold listing while a claim is open', function () {
    [$seller, $listing] = sellerWithListing();
    app(DealService::class)->markSold($listing, $seller);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->assertSee('Nog niet bevestigd');
});

it('hands out a fresh link when the seller asks for one', function () {
    [$seller, $listing] = sellerWithListing();
    $tx = app(DealService::class)->markSold($listing, $seller);
    $old = (string) $tx->claim_token;

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('newLink', $tx->id)
        ->assertHasNoErrors();

    expect($tx->fresh()->claim_token)->not->toBe($old);
});

it('does not let a non-owner mark it sold or refresh a link', function () {
    [$seller, $listing] = sellerWithListing();
    $tx = app(DealService::class)->markSold($listing, $seller);
    $stranger = User::factory()->create();

    // Mount op een *gepubliceerde* advertentie van dezelfde verkoper: een
    // vreemde die een 'sold' advertentie opvraagt krijgt al in mount() een 404
    // van de view-ability, en dan komt newLink nooit aan de beurt.
    $other = Listing::factory()->published()->for($seller)->create();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $other->ulid, 'slug' => (string) $other->slug])
        ->call('markSold')
        ->assertForbidden();

    Livewire::actingAs($stranger)
        ->test(Detail::class, ['ulid' => (string) $other->ulid, 'slug' => (string) $other->slug])
        ->call('newLink', $tx->id)
        ->assertForbidden();
});

it('does not let the owner mark it sold when the deals feature is off', function () {
    config(['cloudmarktplaats.features.deals' => false]);
    [$seller, $listing] = sellerWithListing();

    Livewire::actingAs($seller)
        ->test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('markSold')
        ->assertForbidden();

    expect($listing->fresh()->state)->toBe('published')
        ->and(Transaction::query()->count())->toBe(0);
});
```

Voeg toe aan `tests/Feature/Listings/MarkSoldFromMineTest.php`:

```php
// Zonder deze regel is de claim-link kwijt zodra de verkoper de detailpagina
// sluit: de advertentie staat dan op 'sold' en verdwijnt uit zijn blikveld.
it('flags a sold listing whose buyer has not confirmed yet', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->published()->create();
    app(App\Services\Gamification\DealService::class)->markSold($listing, $seller);

    $this->actingAs($seller)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertSee('koper nog niet bevestigd');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/MarkSoldUiTest.php tests/Feature/Listings/MarkSoldFromMineTest.php
```

Verwacht: FAIL — `markSold()` geeft nog drie argumenten door aan de service en `newLink()` bestaat niet.

- [ ] **Step 3: Update the Detail component**

In `app/Livewire/Listings/Detail.php`: voeg `use App\Models\Transaction;` en `use Illuminate\Database\Eloquent\Collection;` toe aan de imports. Verwijder de property `public string $buyerUsername = '';` en vervang `markSold()` door:

```php
    public function markSold(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 403);

        $user = auth()->user();
        abort_unless($user?->can('markSold', $this->listing) ?? false, 403);

        try {
            app(DealService::class)->markSold($this->listing, $user);
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());

            return;
        }

        $this->listing->refresh();
    }

    /** Verlopen link vervangen. De verkoper is de enige die hem kan doorgeven. */
    public function newLink(int $transactionId): void
    {
        $user = auth()->user();
        abort_unless($user?->can('markSold', $this->listing) ?? false, 403);

        $tx = Transaction::query()
            ->where('listing_id', $this->listing->id)
            ->findOrFail($transactionId);

        try {
            app(DealService::class)->refreshClaimToken($tx, $user);
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());
        }
    }

    /**
     * Openstaande claims van deze advertentie, alleen voor de eigenaar.
     *
     * @return Collection<int, Transaction>
     */
    private function openClaims(): Collection
    {
        if (! config('cloudmarktplaats.features.deals') || auth()->id() !== $this->listing->user_id) {
            return new Collection;
        }

        return app(DealService::class)->openClaims($this->listing);
    }
```

Pas in dezelfde klasse `render()` aan: vervang de eerste regel

```php
        $view = view('livewire.listings.detail');
```

door

```php
        $view = view('livewire.listings.detail', ['openClaims' => $this->openClaims()]);
```

- [ ] **Step 4: Write the claim-link component**

`resources/views/components/deals/claim-link.blade.php` — het kopieerpatroon is overgenomen van `components/listings/share-panel.blade.php`, inclusief de eerlijke terugval als de clipboard-API niet beschikbaar is:

```blade
@props(['transaction'])

@php
    $url = route('deals.claim', ['token' => $transaction->claim_token]);
    $expired = $transaction->claim_expires_at?->isPast() ?? false;
    $copyText = __('Bedankt voor de koop. Wil je hier even bevestigen dat het is doorgegaan? :url — één klik, meer is het niet.', ['url' => $url]);
@endphp

{{-- `$attributes` moet op de root staan, anders valt de `wire:key` uit de
     lus in het paneel weg en hergebruikt Livewire DOM-nodes tussen claims. --}}
<div {{ $attributes->class(['mt-3 rounded-sm border border-cmp-border bg-cmp-bg2 p-3']) }}>
    @if ($expired)
        <p class="text-sm text-cmp-muted">{{ __('Deze link is verlopen.') }}</p>
        <button wire:click="newLink({{ $transaction->id }})" class="cmp-btn cmp-btn-secondary mt-2">
            {{ __('Nieuwe link') }}
        </button>
    @else
        <p class="break-all font-mono text-xs text-cmp-text">{{ $url }}</p>

        <div
            x-data="{
                copied: false,
                async copy() {
                    const input = $refs.claimText;

                    try {
                        if (! navigator.clipboard) {
                            throw new Error('clipboard api unavailable');
                        }
                        await navigator.clipboard.writeText(input.value);
                    } catch (error) {
                        // Nooit succes claimen dat we niet kunnen vaststellen:
                        // toon de tekst en laat de verkoper zelf kopiëren.
                        input.classList.remove('sr-only');
                        input.removeAttribute('aria-hidden');
                        input.removeAttribute('tabindex');
                        input.select();

                        return;
                    }

                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                },
            }"
            class="mt-2 flex flex-wrap items-center gap-2"
        >
            <button type="button" class="cmp-btn cmp-btn-ghost" @click="copy()">
                <span x-show="!copied">{{ __('Kopieer link + tekst') }}</span>
                <span x-show="copied" x-cloak>{{ __('Gekopieerd') }}</span>
            </button>

            <input
                type="text"
                x-ref="claimText"
                value="{{ $copyText }}"
                readonly
                class="sr-only w-full font-mono text-xs"
                tabindex="-1"
                aria-hidden="true"
            >
        </div>

        <p class="mt-2 font-mono text-[11px] text-cmp-faint">
            {{ __('Nog niet bevestigd. Link verloopt :date.', ['date' => $transaction->claim_expires_at?->translatedFormat('j F') ?? '—']) }}
        </p>
    @endif
</div>
```

- [ ] **Step 5: Rewrite the panel**

Vervang in `resources/views/livewire/listings/detail.blade.php` het hele blok van regel 34 t/m 46 (van `@if (auth()->id() === $listing->user_id && $listing->state === 'published' ...` tot en met de afsluitende `@endif`) door:

```blade
        @if (auth()->id() === $listing->user_id && config('cloudmarktplaats.features.deals')
             && ($listing->state === 'published' || $openClaims->isNotEmpty()))
            {{-- Ankerdoel voor de "Verkocht melden"-knop op Mijn advertenties: de
                 verkoper beheert daar, maar dit paneel woont hier. Het blijft
                 zichtbaar zolang er een claim openstaat — anders is de link
                 onbereikbaar zodra de advertentie op 'sold' staat. --}}
            <div id="deal-panel" class="mt-6 rounded-sm border border-cmp-border bg-cmp-surface p-4">
                <div class="cmp-section-label mb-3">{{ __('Verkocht?') }}</div>

                @if ($listing->state === 'published')
                    <p class="text-sm text-cmp-muted">
                        {{ __('Meld je verkoop. Je krijgt daarna een link die je aan de koper stuurt; bevestigt hij die, dan telt de verkoop mee voor je verkopersprofiel.') }}
                    </p>
                    <div class="mt-3">
                        <button wire:click="markSold" wire:confirm="{{ __('Advertentie als verkocht markeren?') }}" class="cmp-btn cmp-btn-primary">
                            {{ __('Markeer als verkocht') }}
                        </button>
                    </div>
                @endif

                @error('deal') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($openClaims->isNotEmpty())
                    <p class="mt-4 text-sm text-cmp-muted">
                        {{ trans_choice('Verkocht. Stuur de koper deze link, dan kan hij de deal bevestigen.|Verkocht. Stuur elke koper zijn eigen link, dan kunnen ze de deals bevestigen.', $openClaims->count()) }}
                    </p>
                    @foreach ($openClaims as $claim)
                        <x-deals.claim-link :transaction="$claim" wire:key="claim-{{ $claim->id }}" />
                    @endforeach
                @endif
            </div>
        @endif
```

- [ ] **Step 6: Flag unconfirmed sales on Mijn advertenties**

In `app/Livewire/Listings/Mine.php`: voeg `use App\Models\Transaction;` toe en vervang `render()` door:

```php
    public function render(): View
    {
        /** @var Collection<int, Listing> $listings */
        $listings = Listing::query()
            ->where('user_id', auth()->id())
            ->with('photos')
            ->orderByDesc('created_at')
            ->get();

        // Zonder deze markering raakt de claim-link kwijt: de advertentie staat
        // op 'sold' en de verkoper heeft geen reden meer om de detailpagina te
        // openen, terwijl de koper daar nog op wacht.
        $openClaimListingIds = Transaction::query()
            ->whereIn('listing_id', $listings->pluck('id'))
            ->where('status', 'pending')
            ->whereNull('buyer_user_id')
            ->pluck('listing_id')
            ->all();

        return view('livewire.listings.mine', [
            'listings' => $listings,
            'openClaimListingIds' => $openClaimListingIds,
        ]);
    }
```

In `resources/views/livewire/listings/mine.blade.php`: voeg direct ná het bestaande `@if ($listing->state === 'published' && config(...))`-blok (dus na de `@endif` op regel 86) toe:

```blade
                        @if (in_array($listing->id, $openClaimListingIds, true))
                            <a href="/listings/{{ $listing->ulid }}-{{ $listing->slug }}#deal-panel" class="cmp-btn cmp-btn-ghost px-3 py-1.5 text-sm">
                                {{ __('koper nog niet bevestigd') }}
                            </a>
                        @endif
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/MarkSoldUiTest.php tests/Feature/Listings/MarkSoldFromMineTest.php
```

Verwacht: PASS, 5 + 4 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Listings/Detail.php app/Livewire/Listings/Mine.php \
        resources/views/livewire/listings/detail.blade.php \
        resources/views/livewire/listings/mine.blade.php \
        resources/views/components/deals/claim-link.blade.php \
        tests/Feature/Gamification/MarkSoldUiTest.php \
        tests/Feature/Listings/MarkSoldFromMineTest.php
git commit -m "Give the seller one button and a link to hand over"
```

---

### Task 6: Mijn deals wordt een overzicht

**Files:**
- Modify: `app/Livewire/Profile/Deals.php`
- Modify: `resources/views/livewire/profile/deals.blade.php`
- Test: `tests/Feature/Gamification/DealsPageTest.php`

**Interfaces:**
- Consumes: `Transaction` met nullable koper.
- Produces: `Deals::confirmed(): Collection<int, Transaction>` — afgeronde deals waarin de gebruiker koper óf verkoper is, nieuwste eerst. `Deals::pending()` en `Deals::confirm()` blijven bestaan voor oude rijen mét koper.

- [ ] **Step 1: Write the failing test**

Voeg toe aan `tests/Feature/Gamification/DealsPageTest.php` (breid de imports uit met `App\Services\Gamification\DealService`):

```php
it('shows a confirmed deal to both the buyer and the seller', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['title' => 'HP MicroServer']);
    $tx = app(DealService::class)->markSold($listing, $seller);
    $buyer = User::factory()->create(['email_verified_at' => now()]);
    app(DealService::class)->claim((string) $tx->claim_token, $buyer);

    Livewire::actingAs($buyer)->test(Deals::class)->assertSee('HP MicroServer')->assertSee('Gekocht');
    Livewire::actingAs($seller)->test(Deals::class)->assertSee('HP MicroServer')->assertSee('Verkocht');
});

it('explains itself when there is nothing yet', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Deals::class)
        ->assertSee('Hier komen de deals te staan die jij of je tegenpartij bevestigd heeft.');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/DealsPageTest.php
```

Verwacht: FAIL — de pagina toont "Gekocht" noch de nieuwe lege-staattekst.

- [ ] **Step 3: Add confirmed() to the component**

In `app/Livewire/Profile/Deals.php`: voeg deze methode toe onder `pending()` en werk de docblock van de klasse bij.

Zet boven de klasse:

```php
/**
 * "Mijn deals" — het eigen handelsverleden.
 *
 * Bevestigen gebeurt sinds de claim-link op /deal/{token}: een `pending`-rij
 * heeft daarom geen koper meer, en de oude lijst zou permanent leeg staan.
 * Deze pagina toont daarom afgeronde deals in beide rollen. `pending()` en
 * `confirm()` blijven staan voor rijen van vóór die wijziging — die hebben
 * wél een koper en verdienen nog steeds hun knop.
 */
```

En de methode:

```php
    /** @return Collection<int, Transaction> */
    public function confirmed(): Collection
    {
        $id = (int) auth()->id();

        return Transaction::query()
            ->where('status', 'completed')
            ->where(fn ($q) => $q->where('buyer_user_id', $id)->orWhere('seller_user_id', $id))
            ->with('listing')
            ->latest('completed_at')
            ->get();
    }
```

Pas `render()` aan:

```php
    public function render(): View
    {
        return view('livewire.profile.deals', [
            'pending' => $this->pending(),
            'confirmed' => $this->confirmed(),
        ]);
    }
```

- [ ] **Step 4: Rewrite the view**

Vervang `resources/views/livewire/profile/deals.blade.php` volledig door:

```blade
<div class="mx-auto max-w-2xl px-5 py-10 sm:px-8 sm:py-14">
    <div class="cmp-section-label mb-3">{{ __('Vertrouwen') }}</div>
    <h1 class="text-3xl font-bold tracking-display-tighter">{{ __('Mijn deals') }}</h1>
    <p class="mt-3 text-sm text-cmp-muted">
        {{ __('Je bevestigde deals, gekocht en verkocht. Een verkoop telt pas mee voor een verkopersprofiel als de koper hem bevestigd heeft.') }}
    </p>
    @error('deal') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

    {{-- Rijen van vóór de claim-link: die hebben al een koper en wachten nog
         op zijn bevestiging. Nieuwe deals komen hier niet meer bij. --}}
    @if ($pending->isNotEmpty())
        <h2 class="mt-8 font-display text-lg font-bold tracking-display-tight">{{ __('Wacht op jouw bevestiging') }}</h2>
        <div class="mt-3 space-y-2">
            @foreach ($pending as $tx)
                <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                    <span class="text-sm text-cmp-text">{{ $tx->listing?->title ?? __('Advertentie') }}</span>
                    <button wire:click="confirm({{ $tx->id }})" class="cmp-btn cmp-btn-primary">{{ __('Deal bevestigen') }}</button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8 space-y-2">
        @forelse ($confirmed as $tx)
            <div class="flex items-center justify-between rounded-sm border border-cmp-border bg-cmp-surface px-4 py-3">
                <div class="min-w-0">
                    <span class="cmp-label-chip border-cmp-signal text-cmp-signal">
                        {{ $tx->seller_user_id === auth()->id() ? __('Verkocht') : __('Gekocht') }}
                    </span>
                    <span class="ml-2 text-sm text-cmp-text">{{ $tx->listing?->title ?? __('Advertentie') }}</span>
                </div>
                <div class="shrink-0 text-right font-mono text-[11px] text-cmp-faint">
                    <div>€ {{ number_format($tx->amount_cents / 100, 2, ',', '.') }}</div>
                    <div>{{ $tx->completed_at?->format('Y-m-d') }}</div>
                </div>
            </div>
        @empty
            <p class="text-sm text-cmp-muted">
                {{ __('Hier komen de deals te staan die jij of je tegenpartij bevestigd heeft.') }}
            </p>
        @endforelse
    </div>
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Gamification/DealsPageTest.php
```

Verwacht: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Profile/Deals.php resources/views/livewire/profile/deals.blade.php \
        tests/Feature/Gamification/DealsPageTest.php
git commit -m "Turn Mijn deals into a record of confirmed trades"
```

---

### Task 7: De dagelijkse check kan het weer zien

**Files:**
- Modify: `app/Services/Ops/IntegrityReport.php:41`
- Modify: `resources/views/emails/daily-integrity.blade.php:28`
- Test: `tests/Feature/Console/` — voeg toe aan het bestaande testbestand voor de dagelijkse check als dat er is; bestaat het niet, maak `tests/Feature/Ops/IntegrityReportDealsTest.php`

**Interfaces:**
- Consumes: `Transaction` met `completed_at`.
- Produces: `IntegrityReport::build()` levert `cijfers.deals_bevestigd` (afgerond in de periode) en `cijfers.verkopen_gemeld` (nieuw gemeld in de periode).

- [ ] **Step 1: Write the failing test**

Bepaal eerst waar de bestaande test staat:

```bash
docker compose exec -T php-fpm grep -rln "IntegrityReport" tests/
```

Voeg onderstaande toe aan het gevonden bestand; is er geen, maak dan `tests/Feature/Ops/IntegrityReportDealsTest.php` met deze inhoud (inclusief de `<?php declare(strict_types=1);`-kop):

```php
<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\DealService;
use App\Services\Ops\IntegrityReport;

/*
 * `deals_bevestigd` telde `status = 'confirmed'`, een waarde die de enum
 * (pending|completed|cancelled) niet kent. Het cijfer stond dus structureel op
 * nul en zou daar ook zijn blijven staan als de claim-link perfect werkte.
 */
it('counts a reported sale and a confirmed one', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();
    $tx = app(DealService::class)->markSold($listing, $seller);

    $rapport = app(IntegrityReport::class)->build(now());
    expect($rapport['cijfers']['verkopen_gemeld'])->toBe(1)
        ->and($rapport['cijfers']['deals_bevestigd'])->toBe(0);

    app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect(app(IntegrityReport::class)->build(now())['cijfers']['deals_bevestigd'])->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest --filter="counts a reported sale"
```

Verwacht: FAIL — `Undefined array key "verkopen_gemeld"`.

- [ ] **Step 3: Fix the report**

In `app/Services/Ops/IntegrityReport.php`, vervang regel 41 door twee regels:

```php
            // Tellen op `completed_at`, niet op `updated_at`: dat laatste
            // beweegt ook als er iets anders aan de rij verandert.
            'deals_bevestigd' => Transaction::query()->where('status', 'completed')->where('completed_at', '>=', $since)->count(),
            'verkopen_gemeld' => Transaction::query()->where('created_at', '>=', $since)->count(),
```

- [ ] **Step 4: Show it in the mail**

In `resources/views/emails/daily-integrity.blade.php`, breid de labelmap op regel 28 uit — zet `'verkopen_gemeld' => 'Verkopen gemeld',` direct vóór `'deals_bevestigd' => 'Deals bevestigd'`:

```blade
            @foreach (['nieuwe_leden' => 'Nieuwe leden', 'gepubliceerd' => 'Advertenties gepubliceerd', 'fotos' => "Foto's geüpload", 'contactverzoeken' => 'Contactverzoeken', 'verkopen_gemeld' => 'Verkopen gemeld', 'deals_bevestigd' => 'Deals bevestigd', 'mislukte_jobs' => 'Mislukte jobs (totaal)', 'concepten_zonder_foto' => 'Concepten zonder foto'] as $sleutel => $label)
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest --filter="counts a reported sale"
docker compose exec -T php-fpm php artisan platform:daily-check --show
```

Verwacht: PASS, en in de tabel staan `verkopen_gemeld` en `deals_bevestigd`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Ops/IntegrityReport.php resources/views/emails/daily-integrity.blade.php tests/
git commit -m "Count deals with a status the enum actually has, and report reported sales"
```

---

### Task 8: De koper mag zich bekendmaken in de relay

**Files:**
- Modify: `app/Livewire/ContactSeller.php`
- Modify: `resources/views/livewire/contact-seller.blade.php`
- Modify: `app/Mail/SellerContactMail.php`
- Modify: `resources/views/emails/seller-contact.blade.php`
- Test: `tests/Feature/Listings/ContactSellerTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `ContactSeller::$revealUsername` (bool, default `true`); `SellerContactMail::__construct(Listing $listing, string $buyerEmail, string $messageBody, ?string $buyerUsername = null)`.

- [ ] **Step 1: Write the failing test**

Voeg toe aan `tests/Feature/Listings/ContactSellerTest.php`:

```php
it('names a logged-in buyer in the relay mail, unless they opt out', function () {
    Mail::fake();
    $buyer = User::factory()->create(['username' => 'robin']);

    $this->actingAs($buyer);
    relayForm()
        ->set('email', 'robin@example.test')
        ->set('body', 'Is deze nog beschikbaar en wat is de laagste prijs?')
        ->call('send')
        ->assertHasNoErrors();

    Mail::assertSent(SellerContactMail::class, fn (SellerContactMail $m) => $m->buyerUsername === 'robin');
});

it('stays anonymous when the buyer unticks the box', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create(['username' => 'robin']));

    relayForm()
        ->set('revealUsername', false)
        ->set('email', 'robin@example.test')
        ->set('body', 'Is deze nog beschikbaar en wat is de laagste prijs?')
        ->call('send');

    Mail::assertSent(SellerContactMail::class, fn (SellerContactMail $m) => $m->buyerUsername === null);
});

it('never names a buyer who is not logged in', function () {
    Mail::fake();

    relayForm()
        ->set('email', 'onbekend@example.test')
        ->set('body', 'Is deze nog beschikbaar en wat is de laagste prijs?')
        ->call('send');

    Mail::assertSent(SellerContactMail::class, fn (SellerContactMail $m) => $m->buyerUsername === null);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Listings/ContactSellerTest.php
```

Verwacht: FAIL — `Property [revealUsername] not found` / `$buyerUsername` bestaat niet.

- [ ] **Step 3: Add the checkbox to the component**

In `app/Livewire/ContactSeller.php`: voeg onder `public string $website = '';` toe:

```php
    /**
     * Ingelogde kopers mogen zich bekendmaken. Standaard aan, omdat een vinkje
     * dat standaard uit staat in de praktijk nooit wordt aangeraakt — en de
     * verkoper anders nooit weet met wie hij handelt. Anoniem blijven is één
     * klik weg, en dat is precies wat "optioneel anoniem" hoort te betekenen.
     */
    public bool $revealUsername = true;
```

En in `send()`, vervang de mailverzending door:

```php
        $seller = $this->listing->user;
        if ($seller !== null) {
            $buyer = auth()->user();

            Mail::to($seller->email)->send(new SellerContactMail(
                listing: $this->listing,
                buyerEmail: $this->email,
                messageBody: $this->body,
                buyerUsername: $this->revealUsername ? $buyer?->username : null,
            ));

            ContactRelayLog::create(['listing_id' => $this->listing->id]);
        }
```

- [ ] **Step 4: Carry the username in the mailable**

In `app/Mail/SellerContactMail.php`: breid de constructor uit en geef de naam door aan de view:

```php
    public function __construct(
        public Listing $listing,
        public string $buyerEmail,
        public string $messageBody,
        public ?string $buyerUsername = null,
    ) {}
```

```php
    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-contact',
            with: [
                'title' => $this->listing->title,
                'url' => url('/listings/'.$this->listing->ulid.'-'.$this->listing->slug),
                'body' => $this->messageBody,
                'username' => $this->buyerUsername,
            ],
        );
    }
```

- [ ] **Step 5: Show it in both views**

In `resources/views/livewire/contact-seller.blade.php`, direct boven de verstuurknop:

```blade
            @auth
                <label class="flex items-start gap-2 text-sm text-cmp-muted">
                    <input type="checkbox" wire:model="revealUsername" class="mt-0.5 rounded-sm border-cmp-border bg-cmp-bg2 text-cmp-blue focus:ring-cmp-blue">
                    <span>{{ __('Laat de verkoper zien dat ik :username ben.', ['username' => '@'.auth()->user()->username]) }}</span>
                </label>
            @endauth
```

In `resources/views/emails/seller-contact.blade.php`, direct ná de alinea "Iemand heeft via de site gereageerd…":

```blade
        @if ($username)
            <p style="margin:0 0 16px;color:#7B8DB0;font-size:14px;">
                De afzender is ingelogd als <strong style="color:#E8EDF8;">{{ '@'.$username }}</strong>.
            </p>
        @endif
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Listings/ContactSellerTest.php
```

Verwacht: PASS, alle tests inclusief de drie nieuwe.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/ContactSeller.php app/Mail/SellerContactMail.php \
        resources/views/livewire/contact-seller.blade.php \
        resources/views/emails/seller-contact.blade.php \
        tests/Feature/Listings/ContactSellerTest.php
git commit -m "Let a logged-in buyer say who they are, without making it the default answer"
```

---

### Task 9: Poorten, documentatie en de oude rijen

**Files:**
- Modify: `docs/known-gaps.md`
- Modify: `AGENTS.md`

**Interfaces:**
- Consumes: alles hierboven.
- Produces: groene suite, vastgelegde grenzen.

- [ ] **Step 1: Run the full suite and both static gates**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
```

Verwacht: alle drie groen. Faalt pint, draai `./vendor/bin/pint` en commit de opmaak apart. Klaagt phpstan over de nullable `buyer_user_id` in bestaande code, los dat op in het bestand dat het meldt — niet met een baseline-uitzondering.

- [ ] **Step 2: Write down the gap that stays**

Voeg aan `docs/known-gaps.md` toe, onder een nieuwe kop `## Deals` (of aan een bestaande kop over deals als die er inmiddels is):

```markdown
## Deals

### Wie de claim-link heeft, kan zich koper noemen
Een verkoop wordt bevestigd door wie de link opent, niet door een geverifieerde
koper. Een verkoper die de link naar zijn eigen tweede account stuurt, kweekt
daarmee trustlevel. Dat gold precies zo voor het gebruikersnaamveld dat hiervoor
in de plaats kwam, dus het is geen nieuw gat — maar het is er wel een.

Wat het dempt: de DB-constraint `transactions_buyer_ne_seller` en de controle in
`DealService::lockClaimable()` blokkeren de directe zelfkoop, en
`Transaction::scopeConfirmedSaleFor()` telt gebande kopers niet mee. Wat het niet
dempt: een tweede account dat nooit opvalt. Een echte oplossing vraagt om
identiteit aan de koperskant, en dat botst met "optioneel anoniem" — dus die
afweging hoort in een eigen sub-project, niet in een patch.
```

- [ ] **Step 3: Update the working memory**

Voeg in `AGENTS.md`, onder "Beslissingen die vastliggen", toe:

```markdown
- **De koper koppelen aan een verkoop**: "Markeer als verkocht" is één knop en legt
  altijd een `transaction` vast, ook zonder koper. De koper vult zichzelf in via
  een claim-link (`/deal/{token}`, 30 dagen, eenmalig) die de **verkoper zelf** in
  zijn antwoordmail plakt — wij kennen het adres van de koper niet en kunnen hem
  dus niet mailen. Ontwerp in
  `docs/superpowers/specs/2026-08-21-koper-koppeling-design.md`. Het oude
  gebruikersnaamveld vroeg om iets wat de verkoper structureel niet kon weten; dat
  meldde een verkoper zelf op 21-08.
```

En in de sectie "Meten zonder analytics", achter de opsomming van metingen:

```markdown
`deals_bevestigd` telde tot 21-08 `status = 'confirmed'` — een waarde die de enum
(`pending|completed|cancelled`) niet kent. Dat cijfer stond dus structureel op nul,
ongeacht wat gebruikers deden. Controleer bij een nulmeting altijd eerst of het
getal überhaupt kán bewegen.
```

- [ ] **Step 4: Count the legacy rows on production**

```bash
ssh root@192.168.178.88 'pct exec 214 -- bash -lc "cd /opt/cloudmarktplaats \
  && docker compose -f docker-compose.prod.yml exec -T postgres psql -U app -d cloudmarktplaats \
     -c \"select count(*) from transactions where status = '\''pending'\'' and buyer_user_id is not null;\""'
```

Is de uitkomst `0`, verwijder dan `pending()` en `confirm()` uit `app/Livewire/Profile/Deals.php`, het `@if ($pending->isNotEmpty())`-blok uit de view, `'pending' => $this->pending(),` uit `render()`, en de twee tests in `DealsPageTest.php` die `confirm` aanroepen. Draai daarna de suite opnieuw. Is de uitkomst groter dan nul, laat alles staan en noteer het aantal in de commitboodschap.

- [ ] **Step 5: Commit**

```bash
git add docs/known-gaps.md AGENTS.md app/Livewire/Profile/Deals.php \
        resources/views/livewire/profile/deals.blade.php tests/Feature/Gamification/DealsPageTest.php
git commit -m "Write down what the claim link does not solve"
```

- [ ] **Step 6: Build the assets and deploy**

```bash
npm run build
```

Daarna de sync uit `AGENTS.md`, met `route:cache` erbij — `/deal/{token}` is nieuw en de routecache op productie geeft hem anders een 404. Stuur `public/build` mee (die map staat in `.gitignore`). Chown alleen de paden die je stuurde, nooit `bootstrap/` of `storage/` als geheel.

```bash
tar czf - app resources routes database docs AGENTS.md public/build \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats \
  && tar xzf - && chown -R 1000:1000 app resources routes database docs AGENTS.md public/build \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan migrate --force \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan route:cache \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan config:cache \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear \
  && docker compose -f docker-compose.prod.yml restart php-fpm queue-worker'"
```

- [ ] **Step 7: Check the deploy**

```bash
ssh root@192.168.178.88 'pct exec 214 -- bash -lc "cd /opt/cloudmarktplaats \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan platform:daily-check --show"'
```

Verwacht: geen nieuwe foutregels, en `verkopen_gemeld` staat in de tabel.
