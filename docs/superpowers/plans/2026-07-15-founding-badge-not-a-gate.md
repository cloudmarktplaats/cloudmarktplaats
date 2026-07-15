# De founding-100 als badge, niet als poort — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De founding-badge wordt een historisch feit dat niemand meer kan krijgen, en registratie gaat open — zodat de cap aanbod niet langer tegenhoudt.

**Architecture:** `FoundingCohort` scheidt twee begrippen die nu door elkaar lopen. `members()` (ledental, momentopname) blijft registratie voeden; `hasFoundingSpot()` (geschiedenis) telt voortaan gestempelde badges via `withTrashed()` en wordt daarmee monotoon. De homepage volgt diezelfde badge-toestand in plaats van het ledental, zodat de weergave nooit terugklapt naar een schaarste die niet meer bestaat. Als laatste stap gaat `FEATURE_WAITLIST=false` op productie.

**Tech Stack:** Laravel 11, Livewire 3, Pest, Postgres 16, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-07-15-founding-badge-not-a-gate-design.md`

## Global Constraints

- Alles draait in Docker; de host heeft geen PHP. Tests: `docker compose exec -T php-fpm ./vendor/bin/pest`.
- Kwaliteitspoorten moeten groen blijven: `docker compose exec -T php-fpm ./vendor/bin/pint --test` en `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G` (level 8; zonder `--memory-limit=1G` crasht hij).
- NL is de brontaal én de vertaalsleutel. Elke nieuwe door bezoekers zichtbare string krijgt een regel in `lang/en.json`, met de Nederlandse zin als sleutel.
- Kopij-regel, niet onderhandelbaar: het label bij het uitnodigingen-cijfer is **`uitnodigingen open`** en nadrukkelijk **niet** "plekken vrij" — dat laatste leest als een poort, en die halen we juist weg.
- `StatsService::FOUNDING_COHORT` blijft `100`. Niet aanpassen.
- Bestaande badges worden nooit ingetrokken. Geen migratie die `is_founding_member` wist of terugzet.
- `hasFoundingSpot()` moet `withTrashed()` gebruiken. Zonder dat reageert de teller opnieuw op vertrek en is er niets opgelost.
- Verifieer Engelse strings via `artisan tinker`, niet via curl: de locale is sessie-gebonden (`/taal/{locale}`), er is geen `/en`-URL.

---

## File Structure

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `app/Services/FoundingCohort.php` | Scheidt ledental (registratie) van badge-geschiedenis | 1 |
| `tests/Feature/FoundingCohortTest.php` | Bewijst dat vertrek/ban geen badge vrijspeelt | 1 |
| `app/Services/Gamification/StatsService.php` | Levert `invites_open` aan de homepage (60s cache) | 2 |
| `tests/Feature/StatsServiceTest.php` | Bewijst dat alleen inwisselbare codes tellen | 2 |
| `app/Livewire/LaunchStats.php` | `full` volgt de badge-toestand; geeft `invitesOpen` door | 3 |
| `resources/views/livewire/launch-stats.blade.php` | Toont leden + uitnodigingen zodra de cohort dicht is | 3 |
| `lang/en.json` | Engelse vertaling van de nieuwe kopij | 3 |
| `tests/Feature/LaunchStatsTest.php` | Bewijst dat de weergave niet terugklapt | 3 |
| productie `.env` | `FEATURE_WAITLIST=false` — de laatste stap | 4 |

**Volgorde is dwingend.** Taak 1 moet vóór Taak 4 live. Andersom staat registratie open terwijl elk vertrekkend lid nog een badge vrijspeelt voor de eerstvolgende aanmelder — precies het lek dat we dichten, op het moment dat de instroom het grootst is.

---

### Task 1: De badge telt geschiedenis, geen ledental

**Achtergrond voor de implementer:** `User` gebruikt `SoftDeletes`. `members()` draait via Eloquent, dus zachtverwijderde rijen vallen buiten de telling — maar hun `is_founding_member` blijft `true` staan. Op productie verwijderde één founder zijn account, waarna `members()` naar 99 zakte en de eerstvolgende aanmelder badge #101 kreeg. Dit is geen theorie; het is al gebeurd.

**Files:**
- Modify: `app/Services/FoundingCohort.php:10-22` (klasse-docblock), `app/Services/FoundingCohort.php:40-44` (`hasFoundingSpot()`)
- Test: `tests/Feature/FoundingCohortTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (heeft `SoftDeletes`, kolom `is_founding_member` boolean, kolom `is_banned` boolean), `StatsService::FOUNDING_COHORT` (int, 100).
- Produces: `FoundingCohort::hasFoundingSpot(): bool` — nu monotoon en `false` zodra 100 badges gestempeld zijn. `members(): int`, `spotsLeft(): int`, `isRegistrationOpen(): bool` blijven ongewijzigd van signatuur en gedrag.

- [ ] **Step 1: Repareer de test die op de oude betekenis leunt**

`tests/Feature/FoundingCohortTest.php` regel 29-36 vult de cohort met `User::factory()->count(100)->create()`. De `UserFactory` zet `is_founding_member` niet, dus die 100 users hebben géén badge. Onder de nieuwe telling staat de badge-teller dan op 0 en is er dus wél plek — de test faalt terecht. De test bedoelde "de cohort is vol"; dat betekent voortaan "100 badges gestempeld".

Vervang de test op regel 29-36 volledig door:

```php
it('does not stamp founders once the cohort is full', function () {
    // "Vol" betekent: 100 badges gestempeld. Niet: 100 leden.
    User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);

    $cohort = app(FoundingCohort::class);
    expect($cohort->hasFoundingSpot())->toBeFalse()
        ->and($cohort->isRegistrationOpen())->toBeFalse();
});
```

Raak de tests op regel 54, 68, 78 en 91 niet aan: die leunen op `isRegistrationOpen()`, dat op `members()` blijft draaien en dus ongewijzigd werkt.

- [ ] **Step 2: Schrijf de falende tests voor vertrek en ban**

Voeg onderaan `tests/Feature/FoundingCohortTest.php` toe:

```php
it('keeps the cohort closed when a founder deletes their account', function () {
    $founders = User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);

    // Dit is de exacte productie-situatie van 15-07: User gebruikt SoftDeletes,
    // dus de rij blijft bestaan mét badge, maar valt uit members().
    $founders->first()->delete();

    $cohort = app(FoundingCohort::class);
    expect($cohort->members())->toBe(Stats::FOUNDING_COHORT - 1)
        ->and($cohort->hasFoundingSpot())->toBeFalse();
});

it('keeps the cohort closed when a founder is banned', function () {
    User::factory()->count(Stats::FOUNDING_COHORT - 1)->create(['is_founding_member' => true]);
    User::factory()->create(['is_founding_member' => true, 'is_banned' => true]);

    $cohort = app(FoundingCohort::class);
    expect($cohort->members())->toBe(Stats::FOUNDING_COHORT - 1)
        ->and($cohort->hasFoundingSpot())->toBeFalse();
});
```

De `expect(members())`-regel is geen ruis: hij legt de kern van deze wijziging vast. Het ledental mág 99 zijn terwijl de cohort dicht blijft — dat is precies het onderscheid dat we invoeren.

- [ ] **Step 3: Draai de tests en zie ze falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=FoundingCohortTest`

Expected: drie tests FAIL. `does not stamp founders once the cohort is full` faalt op `hasFoundingSpot()` (`true`, want geen badges geteld door de oude ledental-logica); beide nieuwe tests falen op `hasFoundingSpot()` → `true` bij 99 leden. Dat laatste ís de bug, nu vastgelegd in een test.

- [ ] **Step 4: Laat `hasFoundingSpot()` badges tellen**

Vervang in `app/Services/FoundingCohort.php` regel 40-44:

```php
    /** Is there room in the first-100 cohort for one more founder? */
    public function hasFoundingSpot(): bool
    {
        return $this->members() < $this->size();
    }
```

door:

```php
    /**
     * Has the 100th founding badge been stamped?
     *
     * Counts badges ever issued — `withTrashed()` is the whole point, not a
     * detail. Without it a departure frees a badge slot and the next arrival
     * is stamped as a founder they never were.
     */
    public function hasFoundingSpot(): bool
    {
        return User::withTrashed()->where('is_founding_member', true)->count() < $this->size();
    }
```

- [ ] **Step 5: Werk het klasse-docblock bij**

Regel 20-21 van `app/Services/FoundingCohort.php` documenteert nu precies het gedrag dat we weghalen (*"a banned founder frees a slot for the next arrival"*). Laten staan is erger dan geen commentaar: de volgende lezer gelooft het. Vervang het docblock op regel 10-22 door:

```php
/**
 * The "first 100" beta cohort. Two concerns live here so registration,
 * the homepage counter and the badge all agree:
 *
 *  - {@see hasFoundingSpot()} — has the 100th founding badge been stamped?
 *    Drives the permanent `is_founding_member` flag set at registration.
 *  - {@see isRegistrationOpen()} — may a new account be created at all? When
 *    the waitlist feature is on and the cohort is full, registration closes
 *    and new arrivals are sent to the waitlist instead.
 *
 * The two count different things, deliberately. Registration looks at live
 * members: someone who left or was banned no longer occupies a seat, so the
 * seat is free. The badge looks at history: it counts every badge ever
 * stamped, including those of deleted and banned accounts, because "one of
 * the first 100" is a fact about the past that a departure cannot undo.
 *
 * That distinction was missing until 2026-07-15 and it cost us a badge: User
 * uses SoftDeletes, so when one founder deleted their account members() fell
 * to 99 and the next arrival — number 101 — was stamped as a founder.
 */
```

- [ ] **Step 6: Draai de tests en zie ze slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=FoundingCohortTest`

Expected: alle tests in het bestand PASS (de zeven bestaande plus de twee nieuwe).

- [ ] **Step 7: Draai de volle suite**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest`

Expected: alles groen. Faalt hier iets buiten `FoundingCohortTest`, dan leunt die test ook op de oude betekenis van `hasFoundingSpot()` — meld dat in je rapport in plaats van het stil te repareren; het is informatie over wie er nog meer op dit onderscheid leunde.

- [ ] **Step 8: Commit**

```bash
git add app/Services/FoundingCohort.php tests/Feature/FoundingCohortTest.php
git commit -m "fix: de founding-badge telt geschiedenis, niet het ledental

User gebruikt SoftDeletes, dus een vertrokken founder viel uit members()
terwijl zijn is_founding_member bleef staan. De teller zakte naar 99 en de
eerstvolgende aanmelder kreeg badge #101. Op productie al gebeurd.

hasFoundingSpot() telt nu gestempelde badges via withTrashed() en is
daarmee monotoon: honderd is honderd, vertrek wekt geen plek op.
isRegistrationOpen() blijft op members() draaien — een vertrokken lid
neemt wel degelijk geen ledenplek in."
```

---

### Task 2: De teller voor openstaande uitnodigingen

**Files:**
- Modify: `app/Services/Gamification/StatsService.php:56-70` (docblock + `homepageStats()`), plus een `use`-regel bovenin
- Test: `tests/Feature/StatsServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\InviteCode` met bestaande scope `scopeRedeemable(Builder $query): Builder` (`InviteCode.php:59`) — dekt ongebruikt (`used_at` null), niet ingetrokken (`revoked_at` null) en niet verlopen (`expires_at` null of in de toekomst).
- Produces: `StatsService::homepageStats(): array{founding_members: int, listings_live: int, rescued: int, homelabs: int, invites_open: int}` — de nieuwe sleutel is `invites_open`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/StatsServiceTest.php` aan (bestaat nog niet):

```php
<?php

declare(strict_types=1);

use App\Models\InviteCode;
use App\Models\User;
use App\Services\Gamification\StatsService;
use Illuminate\Support\Facades\Cache;

it('counts only redeemable invite codes in the homepage stats', function () {
    // homepageStats() cachet 60 seconden; zonder flush meet je een vorige test.
    Cache::flush();
    $inviter = User::factory()->create();

    InviteCode::factory()->count(2)->create(['inviter_user_id' => $inviter->id]);
    InviteCode::factory()->used()->create(['inviter_user_id' => $inviter->id]);
    InviteCode::factory()->create(['inviter_user_id' => $inviter->id, 'revoked_at' => now()]);
    InviteCode::factory()->create(['inviter_user_id' => $inviter->id, 'expires_at' => now()->subDay()]);

    expect(app(StatsService::class)->homepageStats()['invites_open'])->toBe(2);
});
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=StatsServiceTest`

Expected: FAIL — `Undefined array key "invites_open"`.

- [ ] **Step 3: Voeg de teller toe**

Voeg bovenin `app/Services/Gamification/StatsService.php` de import toe, alfabetisch tussen de bestaande `App\Models\*`-imports:

```php
use App\Models\InviteCode;
```

Vervang daarna het docblock en de methode op regel 56-70:

```php
    /**
     * Live platform-wide numbers for the public homepage. Real counts, no
     * inflation — the "founding members" figure drives the beta-cohort FOMO.
     * Cached briefly so the landing page stays cheap under load.
     *
     * @return array{founding_members: int, listings_live: int, rescued: int, homelabs: int}
     */
    public function homepageStats(): array
    {
        /** @var array{founding_members: int, listings_live: int, rescued: int, homelabs: int} */
        return Cache::remember('stats:homepage', 60, fn (): array => [
            'founding_members' => User::query()->where('is_banned', false)->count(),
            'listings_live' => Listing::query()->where('state', 'published')->count(),
            'rescued' => Listing::query()->where('state', 'sold')->count(),
            'homelabs' => HomelabPost::query()->published()->count(),
        ]);
    }
```

door:

```php
    /**
     * Live platform-wide numbers for the public homepage. Real counts, no
     * inflation. Cached briefly so the landing page stays cheap under load.
     *
     * `founding_members` is the live member count, not the badge count — the
     * two diverged the moment the cohort closed. {@see FoundingCohort} for
     * why those are different questions.
     *
     * `invites_open` leans on InviteCode's `redeemable` scope rather than
     * rebuilding the condition here; one definition of "open" is enough.
     *
     * @return array{founding_members: int, listings_live: int, rescued: int, homelabs: int, invites_open: int}
     */
    public function homepageStats(): array
    {
        /** @var array{founding_members: int, listings_live: int, rescued: int, homelabs: int, invites_open: int} */
        return Cache::remember('stats:homepage', 60, fn (): array => [
            'founding_members' => User::query()->where('is_banned', false)->count(),
            'listings_live' => Listing::query()->where('state', 'published')->count(),
            'rescued' => Listing::query()->where('state', 'sold')->count(),
            'homelabs' => HomelabPost::query()->published()->count(),
            'invites_open' => InviteCode::query()->redeemable()->count(),
        ]);
    }
```

- [ ] **Step 4: Draai de test en zie hem slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=StatsServiceTest`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gamification/StatsService.php tests/Feature/StatsServiceTest.php
git commit -m "feat: tel openstaande uitnodigingen voor de homepage

Hergebruikt InviteCode::redeemable() in plaats van de voorwaarde na te
bouwen — anders lopen twee definities van 'open' uit elkaar."
```

---

### Task 3: De homepage toont leden en uitnodigingen

**Achtergrond voor de implementer:** de sectie toont nu `{{ $members }} / 100` met een voortgangsbalk. Zodra registratie opengaat loopt het ledental over de 100 en staat er letterlijk `117 / 100`. En de zin bij een volle cohort belooft een wachtlijst die straks niet meer bestaat.

**Files:**
- Modify: `app/Livewire/LaunchStats.php:19-32` (`render()`), plus een `use`-regel
- Modify: `resources/views/livewire/launch-stats.blade.php` (regels 1-30; de `<dl>` met platformcijfers vanaf regel 32 blijft ongemoeid)
- Modify: `lang/en.json`
- Test: `tests/Feature/LaunchStatsTest.php`

**Interfaces:**
- Consumes: `StatsService::homepageStats()['invites_open']` (int, uit Taak 2), `FoundingCohort::hasFoundingSpot(): bool` (uit Taak 1).
- Produces: view-data voor `livewire.launch-stats` — `stats` (array), `cohort` (int), `members` (int), `spotsLeft` (int), `pct` (int), `full` (bool), `invitesOpen` (int).

- [ ] **Step 1: Schrijf de falende tests**

De twee bestaande tests in `tests/Feature/LaunchStatsTest.php` gebruiken 7 respectievelijk 5 users. De cohort is dan niet dicht, dus zij blijven de oude weergave zien en moeten ongewijzigd blijven slagen. Raak ze niet aan.

Voeg onderaan `tests/Feature/LaunchStatsTest.php` toe:

```php
it('drops the scarcity anchor and shows invites once the cohort is closed', function () {
    Cache::flush();
    $founders = User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);
    InviteCode::factory()->count(2)->create(['inviter_user_id' => $founders->first()->id]);

    Livewire::test(LaunchStats::class)
        ->assertViewHas('full', true)
        ->assertViewHas('invitesOpen', 2)
        ->assertSee('uitnodigingen open')
        ->assertSee('Nieuwe leden zijn nog steeds welkom')
        ->assertDontSee('plekken vrij')
        ->assertDontSee('/ 100')
        ->assertDontSee('wachtlijst');
});

it('does not flip back to scarcity when a founder leaves', function () {
    Cache::flush();
    $founders = User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);

    // 99 leden, maar 100 badges gestempeld: de cohort blijft dicht.
    $founders->first()->delete();

    Livewire::test(LaunchStats::class)
        ->assertViewHas('full', true)
        ->assertDontSee('plekken vrij')
        ->assertDontSee('/ 100');
});
```

Vul de imports bovenin het bestand aan (naast de bestaande `App\Livewire\LaunchStats`, `App\Models\Listing`, `App\Models\User`, `Illuminate\Support\Facades\Cache`, `Livewire\Livewire`):

```php
use App\Models\InviteCode;
use App\Services\Gamification\StatsService as Stats;
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=LaunchStatsTest`

Expected: de twee nieuwe tests FAIL op `assertViewHas('invitesOpen', 2)` — de view krijgt die sleutel nog niet. De tweede faalt daarnaast op `full` (`false` bij 99 leden). De twee bestaande tests PASS.

- [ ] **Step 3: Laat `full` de badge-toestand volgen en geef `invitesOpen` door**

`full` komt nu uit `$members >= $cohort` — opnieuw een momentopname van het ledental. Verwijderen zich twee leden, dan klapt de homepage terug naar "1 plek vrij" terwijl er geen badge meer te vergeven is. Vervang in `app/Livewire/LaunchStats.php` de `render()`-methode (regel 19-32):

```php
    public function render(): View
    {
        $stats = app(StatsService::class)->homepageStats();
        $cohort = StatsService::FOUNDING_COHORT;
        $members = $stats['founding_members'];

        return view('livewire.launch-stats', [
            'stats' => $stats,
            'cohort' => $cohort,
            'members' => $members,
            'spotsLeft' => max(0, $cohort - $members),
            'pct' => min(100, (int) round($members / $cohort * 100)),
            'full' => $members >= $cohort,
        ]);
    }
```

door:

```php
    public function render(): View
    {
        $stats = app(StatsService::class)->homepageStats();
        $cohort = StatsService::FOUNDING_COHORT;
        $members = $stats['founding_members'];

        return view('livewire.launch-stats', [
            'stats' => $stats,
            'cohort' => $cohort,
            'members' => $members,
            'spotsLeft' => max(0, $cohort - $members),
            'pct' => min(100, (int) round($members / $cohort * 100)),
            // Volgt de badge-toestand, niet het ledental: anders klapt de
            // weergave terug naar "plekken vrij" zodra iemand vertrekt,
            // terwijl er geen badge meer te vergeven is.
            'full' => ! app(FoundingCohort::class)->hasFoundingSpot(),
            'invitesOpen' => $stats['invites_open'],
        ]);
    }
```

Voeg de import toe bovenin het bestand, boven de bestaande `use App\Services\Gamification\StatsService;`:

```php
use App\Services\FoundingCohort;
```

- [ ] **Step 4: Herschrijf de weergave**

Vervang in `resources/views/livewire/launch-stats.blade.php` alles vanaf regel 1 tot en met regel 30 (dus tot vlak vóór de `{{-- Stats for nerds --}}`-comment) door:

```blade
<section class="rounded-sm border-2 border-cmp-ink bg-cmp-surface p-6 sm:p-8" aria-label="{{ __('Beta-statistieken') }}">

    {{-- Vol = de 100e badge is gestempeld, niet = het ledental haalde 100.
         Zodra de cohort dicht is vervalt de schaarste-ankering: een bevroren
         100/100 is een monument voor een deur die dicht zit. --}}
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        @if ($full)
            <div>
                <div class="cmp-section-label mb-2">{{ __('Beta · de community') }}</div>
                <p class="font-mono text-4xl font-bold tracking-tight sm:text-5xl">
                    <span class="text-cmp-signal">{{ number_format($members, 0, ',', '.') }}</span><span class="text-2xl text-cmp-muted sm:text-3xl"> {{ __('leden') }}</span>
                </p>
                <p class="mt-1 text-sm text-cmp-muted">
                    {{ __('De eerste 100 zijn binnen — zij vormen de cultuur. Nieuwe leden zijn nog steeds welkom.') }}
                </p>
            </div>
            <div class="text-right">
                <p class="font-mono text-2xl font-bold text-cmp-ink">{{ number_format($invitesOpen, 0, ',', '.') }}</p>
                <p class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('uitnodigingen open') }}</p>
            </div>
        @else
            <div>
                <div class="cmp-section-label mb-2">{{ __('Beta · de eerste 100') }}</div>
                <p class="font-mono text-4xl font-bold tracking-tight sm:text-5xl">
                    <span class="text-cmp-signal">{{ number_format($members, 0, ',', '.') }}</span><span class="text-cmp-muted"> / {{ $cohort }}</span>
                </p>
                <p class="mt-1 text-sm text-cmp-muted">
                    {{ __('founding members. De vroege leden vormen de cultuur.') }}
                </p>
            </div>
            <div class="text-right">
                <p class="font-mono text-2xl font-bold text-cmp-ink">{{ $spotsLeft }}</p>
                <p class="font-mono text-[11px] uppercase tracking-widest text-cmp-faint">{{ __('plekken vrij') }}</p>
            </div>
        @endif
    </div>

    {{-- Voortgang naar de 100. Betekenisloos zodra de cohort dicht is. --}}
    @unless ($full)
        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-cmp-bg2" role="progressbar"
             aria-valuenow="{{ $members }}" aria-valuemin="0" aria-valuemax="{{ $cohort }}">
            <div class="h-full rounded-full bg-cmp-signal transition-all" style="width: {{ max(2, $pct) }}%"></div>
        </div>
    @endunless

```

- [ ] **Step 5: Voeg de Engelse vertalingen toe**

Vier strings zijn nieuw en één is onwaar geworden. In `lang/en.json`:

Verwijder regel 14:

```json
    "De eerste 100 zijn binnen. Nieuwe leden komen op de wachtlijst.": "The first 100 are in. New members join the waitlist.",
```

Voeg toe, direct na de regel `"Beta · de eerste 100": "Beta · the first 100",`:

```json
    "Beta · de community": "Beta · the community",
    "leden": "members",
    "uitnodigingen open": "invites open",
    "De eerste 100 zijn binnen — zij vormen de cultuur. Nieuwe leden zijn nog steeds welkom.": "The first 100 are in — they shape the culture. New members are still welcome.",
```

Laat de overige wachtlijst-strings (`"De beta zit vol"`, `"Zet me op de wachtlijst"` en verwanten) staan: die horen bij de nu-onbereikbare tak, en de flag kan terug. Een ontbrekende vertaling zou dan stilletjes Nederlands tonen aan Engelse bezoekers.

- [ ] **Step 6: Draai de tests en zie ze slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest --filter=LaunchStatsTest`

Expected: alle vier de tests PASS.

- [ ] **Step 7: Controleer de Engelse strings**

Run:

```bash
docker compose exec -T php-fpm php artisan tinker --execute="app()->setLocale('en'); echo __('uitnodigingen open').' | '.__('leden').' | '.__('Beta · de community').' | '.__('De eerste 100 zijn binnen — zij vormen de cultuur. Nieuwe leden zijn nog steeds welkom.');"
```

Expected: `invites open | members | Beta · the community | The first 100 are in — they shape the culture. New members are still welcome.`

Krijg je de Nederlandse zin terug, dan wijkt de sleutel af van de string in de blade — meestal een verschil in het gedachtestreepje (`—`, U+2014) of een dubbele spatie. De sleutel moet teken voor teken gelijk zijn.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/LaunchStats.php resources/views/livewire/launch-stats.blade.php lang/en.json tests/Feature/LaunchStatsTest.php
git commit -m "feat: homepage toont leden en openstaande uitnodigingen

Zonder dit staat er '117 / 100' zodra registratie opengaat, en belooft de
zin een wachtlijst die niet meer bestaat.

full volgt nu de badge-toestand in plaats van het ledental: anders klapt
de weergave terug naar 'plekken vrij' zodra iemand vertrekt, terwijl er
geen badge meer te vergeven is."
```

---

### Task 4: Kwaliteitspoorten en uitrol

**Achtergrond voor de implementer:** deployen is hier een file-sync naar LXC 214, géén `git pull`. De volgorde is dwingend: eerst de code (Taken 1-3), pas daarna de flag. Andersom staat registratie open terwijl elk vertrek nog een badge vrijspeelt.

**Files:**
- Modify: productie `.env` op LXC 214 (`FEATURE_WAITLIST=false`)
- Geen wijzigingen in de repo.

**Interfaces:**
- Consumes: de vijf gewijzigde bestanden uit Taken 1-3.
- Produces: niets in code. Eindtoestand: registratie open, badge-teller permanent dicht.

- [ ] **Step 1: Draai de volle suite en de kwaliteitspoorten**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: alles groen. Pint klaagt? Draai `docker compose exec -T php-fpm ./vendor/bin/pint` en commit de opmaak apart. Ga niet verder met een rode poort — dit is de laatste keer dat je het goedkoop kunt merken.

- [ ] **Step 2: Sync de code naar productie**

Chown alleen de gesynchroniseerde bestanden, nooit een bovenliggende map: zo verloor `bootstrap/cache` eerder zijn www-data-eigenaarschap, waarna `config:cache` stilletjes faalde terwijl de site een weken oude config bleef serveren.

```bash
cd /mnt/nvme1tb/projects/cloudmarktplaats
tar czf - \
  app/Services/FoundingCohort.php \
  app/Services/Gamification/StatsService.php \
  app/Livewire/LaunchStats.php \
  resources/views/livewire/launch-stats.blade.php \
  lang/en.json \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && tar xzf - && chown 1000:1000 app/Services/FoundingCohort.php app/Services/Gamification/StatsService.php app/Livewire/LaunchStats.php resources/views/livewire/launch-stats.blade.php lang/en.json && echo synced'"
```

Expected: `synced`.

- [ ] **Step 3: Wis de gecompileerde views en de cache**

De blade is gewijzigd, dus de gecompileerde view moet weg. Draai artisan als `www-data`: als root maakt het `storage/logs` root-eigendom, waarna de web-worker 500's gooit die nergens gelogd worden.

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan cache:clear'"
```

Expected: twee bevestigingsregels, geen permission-fouten.

- [ ] **Step 4: Herstart en verifieer de badge-fix vóór de flag**

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart php-fpm && docker compose -f docker-compose.prod.yml restart nginx'"
```

Herstart nginx ná php-fpm, anders 502't de site.

Verifieer daarna dat de cohort dicht is en dicht blijft:

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan tinker --execute=\"echo (int) app(App\\Services\\FoundingCohort::class)->hasFoundingSpot();\" && curl -s -o /dev/null -w \"healthz: %{http_code}\n\" localhost:8080/healthz'"
```

Expected: `0` (geen plek meer — er staan 101 badges) en `healthz: 200`. Krijg je `1`, stop: de badge-telling telt dan nog steeds leden en de flag mag níet om.

- [ ] **Step 5: Zet de flag om**

`FEATURE_WAITLIST` staat niet in prod's `.env` en de config-default is `true`, dus hij moet expliciet toegevoegd worden.

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && printf \"\nFEATURE_WAITLIST=false\n\" >> .env && grep FEATURE_WAITLIST .env && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan config:cache && docker compose -f docker-compose.prod.yml restart php-fpm && docker compose -f docker-compose.prod.yml restart nginx'"
```

Expected: `FEATURE_WAITLIST=false`, gevolgd door `INFO Configuration cached successfully.` Faalt `config:cache` met een permission-fout, dan is `bootstrap/cache` niet van www-data — repareer dat eerst, want anders draait de site door op de oude config en lijkt alles goed te gaan.

- [ ] **Step 6: Verifieer de eindtoestand**

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan tinker --execute=\"\\\$c = app(App\\Services\\FoundingCohort::class); echo \\\"open: \\\".(int) \\\$c->isRegistrationOpen().\\\" | badge: \\\".(int) \\\$c->hasFoundingSpot().\\\" | leden: \\\".\\\$c->members();\"'"
```

Expected: `open: 1 | badge: 0 | leden: 101` — registratie open, geen badges meer te vergeven.

Haal daarna de homepage op en controleer met eigen ogen dat er `101 leden` en `uitnodigingen open` staat, en nergens `/ 100`, `plekken vrij` of `wachtlijst`:

```bash
curl -s https://cloudmarktplaats.nl/ | grep -o "uitnodigingen open\|plekken vrij\|/ 100\|wachtlijst\|leden" | sort | uniq -c
```

Expected: `uitnodigingen open` en `leden` komen voor; `plekken vrij`, `/ 100` en `wachtlijst` niet. Zie je die laatste drie wél, dan draait de site op een gecachete view of oude config — herhaal Stap 3.

- [ ] **Step 7: Registreer als echte bezoeker**

Automatische checks bewijzen niet dat een vreemde zich kan aanmelden. Open `https://cloudmarktplaats.nl/register` in een browser en controleer dat je het registratieformulier ziet, en niet het wachtlijst-formulier. Maak geen testaccount aan — het ledental is publiek en dit zijn echte cijfers.

Meld in je rapport wat je zag. Zie je het wachtlijst-formulier, dan is de config niet herladen.

---

## Rollback

Elke stap is los terug te draaien:

- **De flag:** haal de regel `FEATURE_WAITLIST=false` uit prod's `.env`, draai `config:cache`, herstart php-fpm en nginx. Registratie sluit weer bij 100 leden en de wachtlijst-code komt terug in beeld — die is nooit verwijderd.
- **De code:** `git revert` van de drie commits en opnieuw syncen. Let op: Taak 1 terugdraaien zet het lek terug (vertrek speelt weer een badge vrij), dus draai dat niet terug zonder ook de flag terug te zetten.
- **De 101e badge:** blijft staan, in elk scenario. Die trekken we niet in.
