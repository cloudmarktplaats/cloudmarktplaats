# Mailinglijst-fundament Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een mailinglijst waarop iemand zich met of zonder account kan zetten, met toestemming die je kunt bewijzen, afmelden in 1 klik, en de terughoudendheid afgedwongen in code.

**Architecture:** 1 tabel `mail_subscriptions` gesleuteld op e-mailadres, met een `user_id` die leeg mag zijn (dat is de segmentatie). Alle schrijfacties lopen door `MailSubscriptionService`, zodat toestemming, dubbele opt-in en tokens op 1 plek zitten. Het publieke formulier en de verzendcommando's zitten achter de vlag `features.mail_list`, die pas aan gaat als de LinkedIn-poll gesloten is.

**Tech Stack:** Laravel 11, Livewire 3, Postgres 16, Pest, Filament 3. Mail via Hostinger SMTP.

## Global Constraints

- Ontwerp: `docs/superpowers/specs/2026-08-31-mailinglijst-design.md`. Bij twijfel wint de spec.
- **Geen tracking.** Geen open-pixels, geen klikregistratie, geen unieke links per ontvanger anders dan het afmeldtoken.
- **Geen IP-adres opslaan bij toestemming.** `IpStripperJob` wist IP's na 24 uur en dat is een architectuurbelofte.
- **Afmelden mag nooit een login vereisen.** Elke mail draagt een tokenlink plus de `List-Unsubscribe`-header.
- **De 28 rijen in `waitlist_entries` blijven ongemoeid.** Niet importeren, niet mailen, niet aanraken.
- Toon: Nederlands, kort, tweede persoon, geen marketingtaal, geen uitroeptekens. Vaktaal mag onuitgelegd blijven.
- Schrijfregels voor alle teksten: geen gedachtestreepjes (`—`), en `1` in plaats van "één".
- Elke nieuwe `__()`-string moet in `lang/en.json`, anders faalt `TranslationParityTest`.
- Kwaliteitspoorten vóór elke commit: `docker compose exec -T php-fpm ./vendor/bin/pest`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=512M`.
- De 12 hoofdcategorieën zijn de ltree-rootlabels: `compute`, `networking`, `servers`, `av`, `misc`, `power`, `fabrication`, `kabels`, `storage`, `books`, `licenses`, `meet`.
- `storage/` en `bootstrap/cache` zijn van uid 82. Artisan draait via `docker compose exec -T php-fpm`.

---

### Task 1: Tabel, model en erasure

**Files:**
- Create: `database/migrations/2026_08_31_100000_create_mail_subscriptions_table.php`
- Create: `app/Models/MailSubscription.php`
- Create: `database/factories/MailSubscriptionFactory.php`
- Test: `tests/Feature/Mail/MailSubscriptionErasureTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: model `App\Models\MailSubscription` met kolommen `email`, `user_id`, `wants_offers`, `wants_updates`, `categories` (array), `confirm_token`, `confirmed_at`, `unsubscribe_token`, `consent_text`, `consent_given_at`, `consent_source`, `offers_sent_at`, `updates_sent_at`. Scope `confirmed()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Profile\AccountRemovalService;

/*
 * Post krijgen van een platform waar je net vertrokken bent is precies de fout
 * die op 21-08 een lid kostte. Accountverwijdering moet de inschrijving dus
 * echt meenemen, en niet alleen `deleted_at` zetten.
 */
it('removes the mailing list subscription when the account is erased', function () {
    $user = User::factory()->create(['email' => 'nick@example.test']);
    MailSubscription::factory()->create([
        'user_id' => $user->id,
        'email' => 'nick@example.test',
    ]);

    app(AccountRemovalService::class)->remove($user);

    expect(MailSubscription::query()->where('email', 'nick@example.test')->exists())->toBeFalse();
});

it('keeps a subscription that never belonged to an account', function () {
    MailSubscription::factory()->create(['user_id' => null, 'email' => 'los@example.test']);
    $user = User::factory()->create();

    app(AccountRemovalService::class)->remove($user);

    expect(MailSubscription::query()->where('email', 'los@example.test')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionErasureTest.php`
Expected: FAIL met "Class App\Models\MailSubscription not found".

- [ ] **Step 3: Write the migration**

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
        Schema::create('mail_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            // Leeg is "geen account". Dit veld ís de segmentatie, en de cascade
            // zorgt dat accountverwijdering de inschrijving meeneemt.
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('wants_offers')->default(false);
            $t->boolean('wants_updates')->default(false);
            $t->jsonb('categories')->default('[]');
            $t->string('confirm_token')->nullable()->unique();
            $t->timestamp('confirmed_at')->nullable();
            $t->string('unsubscribe_token')->unique();
            // De letterlijke zin waarop iemand ja zei. Geen versienummer dat
            // naar een tekst wijst: verandert de formulering, dan is oud bewijs
            // anders onleesbaar.
            $t->text('consent_text');
            $t->timestamp('consent_given_at');
            $t->string('consent_source');
            $t->timestamp('offers_sent_at')->nullable();
            $t->timestamp('updates_sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_subscriptions');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MailSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén inschrijving op de mailinglijst, gesleuteld op e-mailadres.
 *
 * `user_id` mag leeg zijn: je hoeft geen account te hebben om op de lijst te
 * staan. Dat lege veld is meteen de segmentatie tussen wel en geen account.
 *
 * Bewaar hier nooit een IP-adres. Dit platform wist IP's na 24 uur en dat is
 * een architectuurbelofte; het bewijs van toestemming bestaat uit
 * `consent_text`, `consent_given_at` en de bevestigingsklik in `confirmed_at`.
 */
class MailSubscription extends Model
{
    /** @use HasFactory<MailSubscriptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'email', 'user_id', 'wants_offers', 'wants_updates', 'categories',
        'confirm_token', 'confirmed_at', 'unsubscribe_token',
        'consent_text', 'consent_given_at', 'consent_source',
        'offers_sent_at', 'updates_sent_at',
    ];

    /**
     * Larastan leest de Laravel-11-vorm niet, vandaar de expliciete shape.
     *
     * @return array{categories: 'array', wants_offers: 'boolean', wants_updates: 'boolean', confirmed_at: 'datetime', consent_given_at: 'datetime', offers_sent_at: 'datetime', updates_sent_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'wants_offers' => 'boolean',
            'wants_updates' => 'boolean',
            'confirmed_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'offers_sent_at' => 'datetime',
            'updates_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<MailSubscription> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->whereNotNull('confirmed_at');
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MailSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MailSubscription> */
class MailSubscriptionFactory extends Factory
{
    protected $model = MailSubscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'user_id' => null,
            'wants_offers' => true,
            'wants_updates' => false,
            'categories' => ['networking'],
            'confirm_token' => null,
            'confirmed_at' => now(),
            'unsubscribe_token' => Str::random(48),
            'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
            'consent_given_at' => now(),
            'consent_source' => 'formulier',
        ];
    }

    public function unconfirmed(): static
    {
        return $this->state(fn () => [
            'confirmed_at' => null,
            'confirm_token' => Str::random(48),
        ]);
    }
}
```

- [ ] **Step 6: Run the migration and the test**

Run: `docker compose exec -T php-fpm php artisan migrate` daarna `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionErasureTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 7: Run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/migrations app/Models/MailSubscription.php database/factories/MailSubscriptionFactory.php tests/Feature/Mail
git commit -m "Geef de mailinglijst een tabel die meegaat als het account weggaat"
```

---

### Task 2: Toestemming en dubbele opt-in

**Files:**
- Create: `app/Services/Mail/MailSubscriptionService.php`
- Test: `tests/Feature/Mail/MailSubscriptionServiceTest.php`

**Interfaces:**
- Consumes: `MailSubscription` uit Task 1.
- Produces:
  - `subscribe(string $email, bool $wantsOffers, bool $wantsUpdates, array $categories, string $consentText, string $source, ?User $user = null): MailSubscription`
  - `confirm(string $token): ?MailSubscription`
  - `unsubscribe(string $token, ?string $what = null): ?MailSubscription` waarbij `$what` één van `offers`, `updates` of `null` (alles) is
  - `purgeUnconfirmed(int $days = 7): int`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;

beforeEach(function () {
    $this->service = app(MailSubscriptionService::class);
});

it('leaves a form signup unconfirmed until the link is clicked', function () {
    $sub = $this->service->subscribe(
        email: 'Nieuw@Example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->confirmed_at)->toBeNull()
        ->and($sub->confirm_token)->not->toBeNull()
        ->and($sub->email)->toBe('nieuw@example.test');
});

/*
 * Een ingelogd lid met geverifieerd adres heeft al bewezen dat de mailbox van
 * hem is. Dat is precies wat e-mailverificatie doet, dus een tweede klik voegt
 * geen bewijs toe en levert alleen afhakers op.
 */
it('confirms straight away for a verified account holder', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);

    $sub = $this->service->subscribe(
        email: 'lid@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'profiel',
        user: $user,
    );

    expect($sub->confirmed_at)->not->toBeNull()
        ->and($sub->user_id)->toBe($user->id);
});

it('confirms a subscription with its token and burns the token', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $confirmed = $this->service->confirm((string) $sub->confirm_token);

    expect($confirmed?->confirmed_at)->not->toBeNull()
        ->and($confirmed?->confirm_token)->toBeNull();
});

it('returns null for a confirm token that does not exist', function () {
    expect($this->service->confirm('onzin'))->toBeNull();
});

it('unsubscribes everything with one click', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('unsubscribes from one purpose and leaves the other standing', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'offers');

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

it('records the literal sentence that was agreed to', function () {
    $sub = $this->service->subscribe(
        email: 'bewijs@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['storage'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->consent_text)->toBe('Ja, mail mij nieuw aanbod in deze categorieen.')
        ->and($sub->consent_given_at)->not->toBeNull();
});

it('throws away signups that were never confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDays(8)]);
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDay()]);
    MailSubscription::factory()->create(['created_at' => now()->subDays(30)]);

    $purged = $this->service->purgeUnconfirmed(7);

    expect($purged)->toBe(1)
        ->and(MailSubscription::query()->count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionServiceTest.php`
Expected: FAIL met "Target class [App\Services\Mail\MailSubscriptionService] does not exist".

- [ ] **Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailSubscription;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * De enige plek waar inschrijvingen ontstaan, bevestigd worden en verdwijnen.
 *
 * Toestemming moet aantoonbaar zijn (art. 7 lid 1 AVG) en dit platform bewaart
 * geen IP's, dus het bewijs bestaat uit de letterlijke zin waarop iemand ja
 * zei plus de bevestigingsklik uit zijn eigen mailbox.
 */
class MailSubscriptionService
{
    /** @param list<string> $categories */
    public function subscribe(
        string $email,
        bool $wantsOffers,
        bool $wantsUpdates,
        array $categories,
        string $consentText,
        string $source,
        ?User $user = null,
    ): MailSubscription {
        // Een geverifieerd account heeft de mailbox al bewezen; daar voegt een
        // tweede klik niets aan bewijskracht toe.
        $alreadyProven = $user !== null && $user->email_verified_at !== null;

        return MailSubscription::query()->updateOrCreate(
            ['email' => Str::lower(trim($email))],
            [
                'user_id' => $user?->id,
                'wants_offers' => $wantsOffers,
                'wants_updates' => $wantsUpdates,
                'categories' => array_values($categories),
                'consent_text' => $consentText,
                'consent_given_at' => now(),
                'consent_source' => $source,
                'confirmed_at' => $alreadyProven ? now() : null,
                'confirm_token' => $alreadyProven ? null : Str::random(48),
                'unsubscribe_token' => Str::random(48),
            ],
        );
    }

    public function confirm(string $token): ?MailSubscription
    {
        $sub = MailSubscription::query()->where('confirm_token', $token)->first();

        $sub?->forceFill(['confirmed_at' => now(), 'confirm_token' => null])->save();

        return $sub;
    }

    /** @param  'offers'|'updates'|null  $what */
    public function unsubscribe(string $token, ?string $what = null): ?MailSubscription
    {
        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();

        $sub?->forceFill([
            'wants_offers' => $what === 'updates' && $sub->wants_offers,
            'wants_updates' => $what === 'offers' && $sub->wants_updates,
        ])->save();

        return $sub;
    }

    /** Onbevestigde aanmeldingen zijn geen toestemming, dus die blijven niet staan. */
    public function purgeUnconfirmed(int $days = 7): int
    {
        return MailSubscription::query()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionServiceTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 5: Run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Services/Mail tests/Feature/Mail/MailSubscriptionServiceTest.php
git commit -m "Leg toestemming vast als tekst en klik, niet als vinkje"
```

---

### Task 3: Bevestigen en afmelden via token

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/MailSubscriptionController.php`
- Create: `resources/views/pages/mail-subscription-result.blade.php`
- Test: `tests/Feature/Mail/MailSubscriptionRoutesTest.php`

**Interfaces:**
- Consumes: `MailSubscriptionService::confirm()` en `::unsubscribe()` uit Task 2.
- Produces: routes `GET /nieuwsbrief/bevestigen/{token}` (naam `mail.confirm`) en `GET /nieuwsbrief/afmelden/{token}` (naam `mail.unsubscribe`, optionele query `?wat=offers|updates`), plus `POST /nieuwsbrief/opnieuw/{token}` (naam `mail.resubscribe`) voor de ongedaan-maken-knop.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\MailSubscription;

/*
 * Art. 11.7 lid 4 Telecommunicatiewet eist een makkelijke, gratis
 * afmeldmogelijkheid in elk bericht. Een link die eerst een login vraagt is
 * dat niet, en abonnees zonder account hebben die login sowieso niet.
 */
it('unsubscribes without any login at all', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('unsubscribes from just the offers when asked', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token.'?wat=offers')->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

it('confirms a signup through the link in the mail', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $this->get('/nieuwsbrief/bevestigen/'.$sub->confirm_token)->assertOk();

    expect($sub->fresh()->confirmed_at)->not->toBeNull();
});

it('says so politely when a token means nothing', function () {
    $this->get('/nieuwsbrief/afmelden/onzin')->assertNotFound();
    $this->get('/nieuwsbrief/bevestigen/onzin')->assertNotFound();
});

it('lets someone undo an accidental unsubscribe', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    $this->post('/nieuwsbrief/opnieuw/'.$sub->unsubscribe_token, ['wat' => 'offers'])->assertOk();

    expect($sub->fresh()->wants_offers)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionRoutesTest.php`
Expected: FAIL met 404 op elke route.

- [ ] **Step 3: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailSubscription;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bevestigen en afmelden, allebei zonder login.
 *
 * Bewust een controller en geen Livewire-component: dit moet werken als een
 * kale GET uit een mailprogramma, ook met JavaScript uit.
 */
class MailSubscriptionController extends Controller
{
    public function __construct(private MailSubscriptionService $subscriptions) {}

    public function confirm(string $token): View
    {
        $sub = $this->subscriptions->confirm($token);
        abort_if($sub === null, 404);

        return view('pages.mail-subscription-result', ['actie' => 'bevestigd', 'abonnement' => $sub]);
    }

    public function unsubscribe(Request $request, string $token): View
    {
        $wat = $request->query('wat');
        $wat = in_array($wat, ['offers', 'updates'], true) ? $wat : null;

        $sub = $this->subscriptions->unsubscribe($token, $wat);
        abort_if($sub === null, 404);

        return view('pages.mail-subscription-result', ['actie' => 'afgemeld', 'abonnement' => $sub->fresh()]);
    }

    /**
     * Nooit terugsturen naar de afmeldroute: die meldt bij elke GET opnieuw af,
     * dus een redirect daarheen draait het herstel meteen weer terug.
     */
    public function resubscribe(Request $request, string $token): View
    {
        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();
        abort_if($sub === null, 404);

        $wat = $request->input('wat');
        $sub->forceFill([
            'wants_offers' => $sub->wants_offers || $wat === 'offers',
            'wants_updates' => $sub->wants_updates || $wat === 'updates',
        ])->save();

        return view('pages.mail-subscription-result', ['actie' => 'hersteld', 'abonnement' => $sub]);
    }
}
```

- [ ] **Step 4: Add the routes**

Voeg toe aan `routes/web.php`, bij de andere publieke routes:

```php
// Bevestigen en afmelden gaan zonder login: art. 11.7 lid 4 Tw eist een
// makkelijke afmeldmogelijkheid, en abonnees zonder account hebben geen login.
Route::get('/nieuwsbrief/bevestigen/{token}', [MailSubscriptionController::class, 'confirm'])
    ->name('mail.confirm');
Route::get('/nieuwsbrief/afmelden/{token}', [MailSubscriptionController::class, 'unsubscribe'])
    ->name('mail.unsubscribe');
Route::post('/nieuwsbrief/opnieuw/{token}', [MailSubscriptionController::class, 'resubscribe'])
    ->name('mail.resubscribe');
```

Vergeet de `use App\Http\Controllers\MailSubscriptionController;` bovenaan niet.

- [ ] **Step 5: Write the result view**

`resources/views/pages/mail-subscription-result.blade.php`:

```blade
<x-layouts.marketing :title="__('Nieuwsbrief')">
    <div class="mx-auto max-w-xl space-y-4 py-12">
        @if ($actie === 'bevestigd')
            <h1 class="text-2xl font-semibold">{{ __('Je staat erop.') }}</h1>
            <p>{{ __('Vanaf nu krijg je mail op dit adres. Afmelden kan met de link onderaan elk bericht.') }}</p>
        @elseif ($actie === 'hersteld')
            <h1 class="text-2xl font-semibold">{{ __('Hersteld.') }}</h1>
            <p>{{ __('Je staat er weer op. Afmelden kan alsnog, met de link onderaan elk bericht.') }}</p>
        @else
            <h1 class="text-2xl font-semibold">{{ __('Je bent afgemeld.') }}</h1>
            <p>{{ __('Er gaat geen mail meer naar dit adres. Was dat een vergissing?') }}</p>
            <div class="flex gap-2">
                @foreach (['offers' => __('Toch nieuw aanbod'), 'updates' => __('Toch updates')] as $wat => $label)
                    <form method="POST" action="{{ route('mail.resubscribe', $abonnement->unsubscribe_token) }}">
                        @csrf
                        <input type="hidden" name="wat" value="{{ $wat }}">
                        <button class="rounded border px-3 py-2 text-sm">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.marketing>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionRoutesTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Add the English strings**

Voeg elke nieuwe `__()`-sleutel toe aan `lang/en.json`, anders faalt `TranslationParityTest`. Draai daarna `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/TranslationParityTest.php`.

- [ ] **Step 8: Run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add routes/web.php app/Http/Controllers/MailSubscriptionController.php resources/views/pages/mail-subscription-result.blade.php lang/en.json tests/Feature/Mail/MailSubscriptionRoutesTest.php
git commit -m "Laat afmelden werken zonder login, met 1 klik"
```

**Let op bij deployen:** `routes/web.php` is gewijzigd, dus `route:cache` moet mee in de sync, anders geeft de nieuwe route een 404.

---

### Task 4: Aanmeldformulier achter een vlag

**Files:**
- Modify: `config/cloudmarktplaats.php`
- Create: `app/Livewire/Mail/Subscribe.php`
- Create: `resources/views/livewire/mail/subscribe.blade.php`
- Create: `app/Mail/MailSubscriptionConfirmMail.php`
- Create: `resources/views/emails/mail-subscription-confirm.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Mail/SubscribeFormTest.php`

**Interfaces:**
- Consumes: `MailSubscriptionService::subscribe()` uit Task 2, routes uit Task 3.
- Produces: route `GET /nieuwsbrief` (naam `mail.subscribe`), vlag `features.mail_list`.

- [ ] **Step 1: Add the feature flag**

In `config/cloudmarktplaats.php`, binnen de `features`-array:

```php
// Het publieke aanmeldformulier en de verzendcommando's. Staat uit tot de
// LinkedIn-poll over gevraagd/gezocht gesloten is: de tekst naast het vinkje
// belooft een mail die dan pas bestaat.
'mail_list' => env('FEATURE_MAIL_LIST', false),
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Mail\Subscribe;
use App\Mail\MailSubscriptionConfirmMail;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
});

it('is not reachable while the flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);

    $this->get('/nieuwsbrief')->assertNotFound();
});

it('signs someone up without an account and mails a confirmation', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'zolder@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['networking'])
        ->call('save')
        ->assertHasNoErrors();

    $sub = MailSubscription::query()->where('email', 'zolder@example.test')->first();

    expect($sub)->not->toBeNull()
        ->and($sub?->confirmed_at)->toBeNull()
        ->and($sub?->user_id)->toBeNull();

    Mail::assertQueued(MailSubscriptionConfirmMail::class);
});

/* Toestemming moet een handeling zijn. Geen vinkje, geen inschrijving. */
it('refuses a signup with neither box ticked', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'niets@example.test')
        ->set('wants_offers', false)
        ->set('wants_updates', false)
        ->call('save')
        ->assertHasErrors('wants_offers');

    expect(MailSubscription::query()->count())->toBe(0);
});

it('demands at least one category when offers are wanted', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'leeg@example.test')
        ->set('wants_offers', true)
        ->set('categories', [])
        ->call('save')
        ->assertHasErrors('categories');
});

it('stores the sentence that was on screen', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'bewijs@example.test')
        ->set('wants_updates', true)
        ->call('save');

    expect(MailSubscription::query()->first()?->consent_text)->toContain('Ja,');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/SubscribeFormTest.php`
Expected: FAIL met "Class App\Livewire\Mail\Subscribe not found".

- [ ] **Step 4: Write the Livewire component**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Mail\MailSubscriptionConfirmMail;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publiek aanmeldformulier. Geen account nodig.
 *
 * De vlagcontrole staat in boot() en niet in mount(): mount() draait alleen bij
 * de eerste page load, dus een pagina die al openstond klikt anders gewoon door
 * nadat de vlag om is.
 */
#[Layout('components.layouts.marketing', ['title' => 'Nieuwsbrief — Cloudmarktplaats'])]
class Subscribe extends Component
{
    public string $email = '';

    public bool $wants_offers = false;

    public bool $wants_updates = false;

    /** @var list<string> */
    public array $categories = [];

    public bool $done = false;

    public const CONSENT_OFFERS = 'Ja, mail mij nieuw aanbod in de categorieen die ik aanvink. Ik kan me altijd weer afmelden.';

    public const CONSENT_UPDATES = 'Ja, stuur mij updates over het platform. Ik kan me altijd weer afmelden.';

    public function boot(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.mail_list'), 404);
    }

    public function save(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            // Zonder vinkje is er geen toestemming, en dan is er niets om vast
            // te leggen.
            'wants_offers' => ['required_without_all:wants_updates', 'boolean'],
            'categories' => [$this->wants_offers ? 'required' : 'nullable', 'array'],
            'categories.*' => ['string'],
        ], [
            'wants_offers.required_without_all' => __('Vink aan waar je mail over wilt krijgen.'),
            'categories.required' => __('Kies minstens 1 categorie.'),
        ]);

        $sub = app(MailSubscriptionService::class)->subscribe(
            email: $this->email,
            wantsOffers: $this->wants_offers,
            wantsUpdates: $this->wants_updates,
            categories: $this->categories,
            consentText: $this->consentText(),
            source: 'formulier',
        );

        Mail::to($sub->email)->queue(new MailSubscriptionConfirmMail($sub));

        $this->done = true;
    }

    /** De letterlijke zin die op het scherm stond, want dat is het bewijs. */
    private function consentText(): string
    {
        return trim(
            ($this->wants_offers ? self::CONSENT_OFFERS.' ' : '')
            .($this->wants_updates ? self::CONSENT_UPDATES : '')
        );
    }

    public function render(): View
    {
        return view('livewire.mail.subscribe');
    }
}
```

- [ ] **Step 5: Write the view**

`resources/views/livewire/mail/subscribe.blade.php`. Toon bij `$done` een bevestiging ("Kijk in je mail, er staat een link klaar"), anders het formulier: e-mailveld, de 2 vinkjes met de letterlijke zinnen uit `Subscribe::CONSENT_OFFERS` en `::CONSENT_UPDATES`, en bij `wants_offers` de 12 hoofdcategorieën als checkboxes gebonden aan `categories`. De categorieën haal je op met:

```php
@php($hoofdcategorieen = ['compute', 'networking', 'servers', 'storage', 'av', 'power', 'kabels', 'fabrication', 'books', 'licenses', 'meet', 'misc'])
```

Zet onder het formulier 1 regel met een link naar de privacyverklaring.

- [ ] **Step 6: Write the confirmation mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** De enige mail die naar een onbevestigd adres gaat. */
class MailSubscriptionConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MailSubscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bevestig je aanmelding',
            replyTo: ['info@cloudmarktplaats.nl'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mail-subscription-confirm');
    }
}
```

De view toont de zin waarop iemand ja zei (`$subscription->consent_text`) en de link naar `route('mail.confirm', $subscription->confirm_token)`, met de regel dat er niets gebeurt als hij hem negeert.

- [ ] **Step 7: Add the route**

```php
Route::get('/nieuwsbrief', Subscribe::class)->name('mail.subscribe');
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/SubscribeFormTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 9: Add English strings, run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add config/cloudmarktplaats.php app/Livewire/Mail app/Mail/MailSubscriptionConfirmMail.php resources/views/livewire/mail resources/views/emails/mail-subscription-confirm.blade.php routes/web.php lang/en.json tests/Feature/Mail/SubscribeFormTest.php
git commit -m "Zet het aanmeldformulier klaar achter een vlag"
```

---

### Task 5: Schakelaars in het profiel en koppeling bij registratie

**Files:**
- Create: `app/Livewire/Profile/MailPreferences.php`
- Create: `resources/views/livewire/profile/mail-preferences.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Livewire/Auth/Register.php`
- Test: `tests/Feature/Mail/MailPreferencesTest.php`

**Interfaces:**
- Consumes: `MailSubscriptionService::subscribe()` uit Task 2.
- Produces: route `GET /profile/mail` (naam `profile.mail`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Livewire\Profile\MailPreferences;
use App\Models\MailSubscription;
use App\Models\User;
use Livewire\Livewire;

it('links an existing subscription to the account that registers with that address', function () {
    MailSubscription::factory()->create(['email' => 'later@example.test', 'user_id' => null]);

    $user = User::factory()->create(['email' => 'later@example.test']);
    app(App\Services\Mail\MailSubscriptionService::class)->linkToUser($user);

    expect(MailSubscription::query()->where('email', 'later@example.test')->first()?->user_id)->toBe($user->id);
});

it('lets a member switch the mail off from their profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);
    MailSubscription::factory()->create([
        'email' => 'lid@example.test', 'user_id' => $user->id, 'wants_updates' => true,
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_updates', false)
        ->call('save');

    expect(MailSubscription::query()->where('email', 'lid@example.test')->first()?->wants_updates)->toBeFalse();
});

it('confirms straight away when a verified member ticks the box in their profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'nieuw@example.test']);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_updates', true)
        ->call('save');

    $sub = MailSubscription::query()->where('email', 'nieuw@example.test')->first();

    expect($sub?->confirmed_at)->not->toBeNull()
        ->and($sub?->consent_source)->toBe('profiel');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailPreferencesTest.php`
Expected: FAIL met "Call to undefined method ...::linkToUser()".

- [ ] **Step 3: Add `linkToUser()` to the service**

```php
    /** Koppelt een losse inschrijving aan het account dat later met dat adres komt. */
    public function linkToUser(User $user): void
    {
        MailSubscription::query()
            ->whereNull('user_id')
            ->where('email', Str::lower((string) $user->email))
            ->update(['user_id' => $user->id]);
    }
```

- [ ] **Step 4: Write the profile component**

`app/Livewire/Profile/MailPreferences.php`, gemodelleerd naar `app/Livewire/Profile/SellerType.php`: `mount()` leest de bestaande inschrijving van `auth()->user()->email` in de properties, `save()` roept `MailSubscriptionService::subscribe()` aan met `source: 'profiel'` en de ingelogde gebruiker. Zelfde 2 vinkjes en zelfde 12 categorieën als Task 4, en dezelfde `CONSENT_*`-zinnen (importeer ze uit `App\Livewire\Mail\Subscribe`, niet overtypen).

- [ ] **Step 5: Add the route**

```php
Route::get('/profile/mail', MailPreferences::class)
    ->middleware(['auth', 'verified'])
    ->name('profile.mail');
```

- [ ] **Step 6: Call `linkToUser()` at registration**

In `app/Livewire/Auth/Register.php`, direct na het aanmaken van de gebruiker binnen de bestaande `DB::transaction()`:

```php
app(MailSubscriptionService::class)->linkToUser($user);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailPreferencesTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 8: Add English strings, run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Livewire/Profile/MailPreferences.php resources/views/livewire/profile app/Livewire/Auth/Register.php app/Services/Mail routes/web.php lang/en.json tests/Feature/Mail/MailPreferencesTest.php
git commit -m "Geef leden dezelfde schakelaars in hun profiel"
```

---

### Task 6: De aanbodmail, die zwijgt als er niets is

**Files:**
- Create: `app/Console/Commands/SendOfferDigest.php`
- Create: `app/Mail/OfferDigestMail.php`
- Create: `resources/views/emails/offer-digest.blade.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Mail/OfferDigestTest.php`

**Interfaces:**
- Consumes: `MailSubscription::scopeConfirmed()` uit Task 1.
- Produces: commando `mail:offers` met `--dry-run`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Mail\OfferDigestMail;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
    $this->networking = Category::factory()->create(['path' => 'networking.switches']);
});

/* Geen nieuws is geen mail. Dat is de hele spamrem. */
it('sends nothing when there is nothing new in the categories', function () {
    MailSubscription::factory()->create(['wants_offers' => true, 'categories' => ['networking']]);

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertNothingQueued();
});

it('mails the new listings in the categories someone picked', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertQueued(OfferDigestMail::class);
    expect($sub->fresh()->offers_sent_at)->not->toBeNull();
});

it('skips a category the subscriber did not pick', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['storage'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

it('never mails an address that has not confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

it('sends nothing on a dry run and leaves the stamp alone', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers --dry-run');

    Mail::assertNothingQueued();
    expect($sub->fresh()->offers_sent_at->toDateTimeString())->toBe($sub->offers_sent_at->toDateTimeString());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/OfferDigestTest.php`
Expected: FAIL met "The command 'mail:offers' does not exist".

- [ ] **Step 3: Write the command**

Signature: `mail:offers {--dry-run : Laat zien wie er gemaild zou worden, verstuur niets}`.

Logica, in deze volgorde:

1. `abort` met exitcode 0 en een melding als `features.mail_list` uit staat.
2. Loop over `MailSubscription::query()->confirmed()->where('wants_offers', true)->get()`.
3. Per abonnee: zoek `Listing` met `state = 'published'`, `published_at > coalesce(offers_sent_at, created_at)`, en een categorie waarvan `subltree(path,0,1)` in `$sub->categories` zit. Gebruik `whereHas('category', fn ($q) => $q->whereRaw('subltree(path,0,1)::text = any(?)', [...]))`.
4. Is de uitkomst leeg, dan `continue` **zonder** de stempel bij te werken.
5. Anders `Mail::to($sub->email)->queue(new OfferDigestMail($sub, $listings))` en daarna de stempel zetten met `DB::table('mail_subscriptions')->where('id', $sub->id)->update(['offers_sent_at' => now()])`.
6. Bij `--dry-run`: alles behalve versturen en stempelen.

**Stempel via `DB::table()`, niet via het model.** Een modelupdate zet ook `updated_at`, en dat is precies de fout die op 23-08 tien vastgelopen concepten uit de dagelijkse meting liet vallen.

- [ ] **Step 4: Write the mailable and view**

`OfferDigestMail` met `public MailSubscription $subscription` en `public Collection $listings`, `replyTo` op `info@cloudmarktplaats.nl`. De view somt de advertenties op met titel, prijs en link. Sluit af met de regel die de segmentatie zichtbaar maakt: staat `user_id` leeg, dan 1 zin over wat een account extra geeft, anders niets. Dat verschil is de enige rechtvaardiging om die kolom te hebben. Onderaan **verplicht** de afmeldlink: `route('mail.unsubscribe', ['token' => $subscription->unsubscribe_token, 'wat' => 'offers'])`. Geen tracking-pixel, geen omgeleide links.

Zet in `envelope()` de `List-Unsubscribe`-header:

```php
    public function headers(): \Illuminate\Mail\Mailables\Headers
    {
        return new \Illuminate\Mail\Mailables\Headers(text: [
            'List-Unsubscribe' => '<'.route('mail.unsubscribe', $this->subscription->unsubscribe_token).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
```

- [ ] **Step 5: Schedule it**

In `routes/console.php`, wekelijks op zaterdagochtend:

```php
Schedule::command('mail:offers')->weeklyOn(6, '09:00');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/OfferDigestTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Add English strings, run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Console/Commands/SendOfferDigest.php app/Mail/OfferDigestMail.php resources/views/emails/offer-digest.blade.php routes/console.php lang/en.json tests/Feature/Mail/OfferDigestTest.php
git commit -m "Stuur de aanbodmail alleen als er echt iets nieuws is"
```

---

### Task 7: De nieuwsbrief, met de rem in het commando

**Files:**
- Create: `app/Console/Commands/SendPlatformUpdate.php`
- Create: `app/Mail/PlatformUpdateMail.php`
- Create: `resources/views/emails/platform-update.blade.php`
- Test: `tests/Feature/Mail/PlatformUpdateTest.php`

**Interfaces:**
- Consumes: `MailSubscription::scopeConfirmed()` uit Task 1, de `List-Unsubscribe`-header uit Task 6.
- Produces: commando `mail:update {bestand} {--dry-run} {--force}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Mail\PlatformUpdateMail;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Storage::disk('local')->put('update.md', "# Wat er is gebeurd\n\nFoto's kun je nu ordenen.");
    config()->set('cloudmarktplaats.features.mail_list', true);
});

it('mails the update to everyone who asked for updates', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(0);

    Mail::assertQueued(PlatformUpdateMail::class);
});

it('leaves the offers-only subscribers alone', function () {
    MailSubscription::factory()->create(['wants_updates' => false, 'wants_offers' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertNothingQueued();
});

/*
 * De rem staat in code en niet in een voornemen. Dit platform verkoopt dat elke
 * claim in code te controleren is, dus "ik ga niet spammen" hoort hier te staan
 * en niet alleen in een gesprek.
 */
it('refuses to send again within 30 days', function () {
    MailSubscription::factory()->create(['wants_updates' => true, 'updates_sent_at' => now()->subDays(10)]);

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertNothingQueued();
});

it('sends again once 30 days have passed', function () {
    MailSubscription::factory()->create(['wants_updates' => true, 'updates_sent_at' => now()->subDays(31)]);

    $this->artisan('mail:update update.md')->assertExitCode(0);

    Mail::assertQueued(PlatformUpdateMail::class);
});

it('sends nothing on a dry run', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md --dry-run')->assertExitCode(0);

    Mail::assertNothingQueued();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/PlatformUpdateTest.php`
Expected: FAIL met "The command 'mail:update' does not exist".

- [ ] **Step 3: Write the command**

Signature: `mail:update {bestand : Markdownbestand met de tekst} {--dry-run} {--force : Negeer de 30-dagenrem}`.

Logica:

1. Stop met exitcode 1 als `features.mail_list` uit staat.
2. Lees het bestand van de `local`-disk; bestaat het niet, exitcode 1 met een duidelijke melding.
3. **De rem:** bepaal `max(updates_sent_at)` over alle abonnees. Ligt die minder dan 30 dagen terug en is `--force` niet gezet, stop dan met exitcode 1 en meld hoeveel dagen er nog te gaan zijn.
4. Stuur per abonnee met `wants_updates = true` en bevestigde toestemming.
5. Stempel `updates_sent_at` via `DB::table()`.
6. Bij `--dry-run`: toon het aantal ontvangers en de eerste regels van de tekst, verstuur en stempel niets.

`--force` bestaat voor het geval er echt iets misgaat en er een correctie uit moet. Gebruik hem nooit voor gewone nieuwsbrieven; dat is precies de belofte die de rem moet waarmaken.

- [ ] **Step 4: Write the mailable and view**

Als `OfferDigestMail`, met dezelfde verplichte afmeldlink (`wat=updates`), dezelfde `List-Unsubscribe`-headers en dezelfde afwezigheid van tracking. De view rendert de markdown en zet eronder wie de afzender is, met adres en KvK-nummer.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/PlatformUpdateTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 6: Add English strings, run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add app/Console/Commands/SendPlatformUpdate.php app/Mail/PlatformUpdateMail.php resources/views/emails/platform-update.blade.php lang/en.json tests/Feature/Mail/PlatformUpdateTest.php
git commit -m "Zet de rem op de nieuwsbrief in het commando zelf"
```

---

### Task 8: De privacyverklaring en het opruimen

**Files:**
- Modify: `database/seeders/legal/privacy.nl.md`
- Modify: `database/seeders/legal/privacy.en.md`
- Modify: `database/seeders/LegalDocumentSeeder.php`
- Modify: `app/Console/Commands/DailyIntegrityCheck.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Mail/MailSubscriptionHousekeepingTest.php`

**Interfaces:**
- Consumes: `MailSubscriptionService::purgeUnconfirmed()` uit Task 2.
- Produces: een meting `nieuwsbrief_abonnees` in het dagelijkse rapport.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\MailSubscription;

it('counts the confirmed subscribers in the daily report', function () {
    MailSubscription::factory()->count(3)->create();
    MailSubscription::factory()->unconfirmed()->create();

    $this->artisan('platform:daily-check --show')
        ->expectsOutputToContain('nieuwsbrief_abonnees');
});

it('throws away signups that were never confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDays(9)]);

    $this->artisan('mail:purge-unconfirmed')->assertExitCode(0);

    expect(MailSubscription::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionHousekeepingTest.php`
Expected: FAIL, de meting ontbreekt en het commando bestaat niet.

- [ ] **Step 3: Add the count and the purge command**

Voeg aan `DailyIntegrityCheck` de meting `nieuwsbrief_abonnees` toe: `MailSubscription::query()->confirmed()->count()`. **Geen alarm**, alleen een getal. Dit is een voorraad en geen aanwas, en alarmeren op voorraad zonder afhandelmarkering gaat vanzelf staan roepen.

Maak `mail:purge-unconfirmed` dat `MailSubscriptionService::purgeUnconfirmed()` aanroept, en plan hem dagelijks in `routes/console.php`:

```php
Schedule::command('mail:purge-unconfirmed')->dailyAt('03:30');
```

- [ ] **Step 4: Update the privacy statement**

Voeg in `privacy.nl.md` aan de doelentabel toe:

```markdown
| Nieuwsbrief en berichten over nieuw aanbod | Toestemming (art. 6 lid 1 sub a AVG) |
```

En een alinea eronder:

```markdown
### Nieuwsbrief en aanbodmail

Je krijgt alleen mail als je je daar zelf voor hebt aangemeld. We bewaren als
bewijs van die toestemming het tijdstip, de bron, en de letterlijke zin waarop
je ja zei. **Geen IP-adres**: we bewaren die niet langer dan 24 uur en dat geldt
ook hier. Onderaan elk bericht staat een afmeldlink die het meteen doet, zonder
inloggen en zonder reden op te geven. Verwijder je je account, dan gaat je
inschrijving mee.
```

Doe hetzelfde in `privacy.en.md`.

- [ ] **Step 5: Bump the version, maar kijk eerst**

`LegalAcceptance` prompt op `tos` én `privacy` per versie, dus een nieuwe privacyversie zet iedereen die een advertentie plaatst opnieuw voor het acceptatiescherm.

**Controleer eerst of de openstaande ToS-tekst voor zakelijke verkopers meekan** (zie `AGENTS.md`, "Beslissingen die vastliggen"). Zo ja: bundel ze in 1 versiebump. Zo nee: leg aan Nick voor of hij de re-acceptatie 2 keer accepteert of wil wachten. Dit is een beslissing voor een mens, geen automatisme.

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Mail/MailSubscriptionHousekeepingTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 7: Run the three gates and commit**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
git add database/seeders app/Console/Commands routes/console.php tests/Feature/Mail
git commit -m "Zet het doel in de privacyverklaring voordat de code het uitvoert"
```

---

## Voor het live gaat

Deze drie horen bij de deploy, niet bij een taak:

1. **Zoek de verzendlimiet van Hostinger op** en zet de queue navenant traag. 300 adressen in 1 keer door een SMTP-account dat daar niet op ingericht is brengt je domein in de problemen. DMARC staat op `p=quarantine`, dus SPF en DKIM moeten kloppen.
2. **`FEATURE_MAIL_LIST=true` gaat pas om als de LinkedIn-poll gesloten is.** Tot dan is alles gebouwd en getest, en publiek onzichtbaar.
3. **`route:cache` moet mee in de sync**, want `routes/web.php` verandert in Task 3, 4 en 5. Zonder dat geeft elke nieuwe route een 404.
