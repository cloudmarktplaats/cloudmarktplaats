# Verplichte 2FA voor staff — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tweefactor-authenticatie verplicht maken voor staff (rol `admin`/`moderator`) op de Filament-adminpanel, met een noodluik tegen lockout.

**Architecture:** Een nieuwe middleware achter de bestaande rol-gate van de panel dwingt af dat staff 2FA hééft ingesteld; het weghalen van Filaments eigen loginpagina laat panel-authenticatie via de front-end `/login` lopen die de TOTP-challenge al draait; een artisan-commando wist 2FA voor een uitgesloten account. Geen wijziging aan de bestaande 2FA-componenten.

**Tech Stack:** Laravel 11, Filament 3 (v3.3.54), Livewire 3, Pest, PostgreSQL.

## Global Constraints

- **Staff** = `role` is `admin` óf `moderator`. Een account "heeft 2FA" ⇔ `two_factor_confirmed_at !== null`.
- **Gewone gebruikers (`role = user`) blijven ongemoeid** — 2FA opt-in, en op `/admin` krijgen ze 403 via `role:admin,moderator` (bestaand gedrag), géén 2FA-redirect.
- **Wijzig NIET** `App\Livewire\Profile\TwoFactorSetup`, `App\Livewire\Auth\TwoFactorChallenge`, of de migraties. Die bestaan en zijn getest.
- **Geen migratie** (kolommen `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` bestaan), **geen Dockerfile-wijziging**.
- **E-mail-lookup case-insensitief** via `whereRaw('lower(email) = ?', [Str::lower(trim($email))])` — hoofdlettergevoelige e-mail was eerder een stille bug (login + reset dood zonder foutmelding).
- **2FA-velden zetten met `forceFill`**, niet via mass-assignment — ze staan niet in `$fillable` en `create()`/`update()` zou ze stil negeren.
- **Tests onder `tests/Feature/`** (alleen Feature krijgt `RefreshDatabase`+`TestCase`, zie `tests/Pest.php`). Draai in Docker: `docker compose exec -T php-fpm ./vendor/bin/pest <pad>`.
- **Filament-loginmechaniek (geverifieerd, deterministisch):** `->login()` weghalen zet `hasLogin()` op false → `Panel::getLoginUrl()` geeft `null` → `Filament\Http\Middleware\Authenticate::redirectTo()` geeft `null` → Laravels exception-handler doet `redirect()->guest(route('login'))`. Dus een uitgelogde bezoeker op `/admin` belandt op `/login`, en de route `/admin/login` bestaat niet meer (404). **Geen `bootstrap/app.php`-wijziging nodig.**
- Factory-helpers: `User::factory()->admin()` (role admin), `->moderator()` (role moderator), default role `user`. Nieuwe users hebben `two_factor_confirmed_at = null`.

---

## File Structure

- **`app/Http/Middleware/EnforceStaffTwoFactor.php`** (nieuw) — één verantwoordelijkheid: een ingelogde staffer zonder bevestigde 2FA naar de instelpagina sturen. Kent de rol-gate niet (die draait ervóór).
- **`app/Providers/Filament/AdminPanelProvider.php`** (wijzigen) — de middleware toevoegen aan `authMiddleware`; `->login()` verwijderen.
- **`app/Console/Commands/ResetTwoFactor.php`** (nieuw) — het noodluik-commando.
- **`tests/Feature/Admin/EnforceStaffTwoFactorTest.php`** (nieuw) — de gate.
- **`tests/Feature/Admin/PanelLoginBypassTest.php`** (nieuw) — het gedichte login-gat.
- **`tests/Feature/Console/ResetTwoFactorTest.php`** (nieuw) — het commando.

---

### Task 1: `EnforceStaffTwoFactor`-middleware + inhaken in de panel

**Files:**
- Create: `app/Http/Middleware/EnforceStaffTwoFactor.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (`authMiddleware`-lijst, rond regel 61-64)
- Test: `tests/Feature/Admin/EnforceStaffTwoFactorTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`two_factor_confirmed_at`, datetime-cast of null); route `profile.security.2fa` (bestaand, front-end); de panel-`authMiddleware` die al `[Authenticate::class, 'role:admin,moderator']` bevat.
- Produces: staff zonder bevestigde 2FA wordt op elke panel-request geredirect naar `route('profile.security.2fa')`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/EnforceStaffTwoFactorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('redirects a staff member without confirmed 2FA to the setup page', function () {
    $admin = User::factory()->admin()->create(); // two_factor_confirmed_at is null

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(route('profile.security.2fa'));
});

it('treats a moderator the same as an admin', function () {
    $moderator = User::factory()->moderator()->create();

    $this->actingAs($moderator)
        ->get('/admin')
        ->assertRedirect(route('profile.security.2fa'));
});

it('does not bounce a staff member who has confirmed 2FA', function () {
    $admin = User::factory()->admin()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

    $response = $this->actingAs($admin)->get('/admin');

    // Deze middleware mag hem niet naar de instelpagina sturen. Ander
    // panel-gedrag (dashboard-render) valt buiten deze taak, dus we toetsen
    // precies de contract-uitkomst van déze middleware.
    expect($response->headers->get('Location'))->not->toBe(route('profile.security.2fa'));
});

it('never redirects a non-staff user to 2FA setup — the role gate wins first', function () {
    $user = User::factory()->create(); // role: user

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden(); // 403 uit role:admin,moderator, vóór onze middleware
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/EnforceStaffTwoFactorTest.php`
Expected: de eerste twee tests FALEN (staff zonder 2FA komt nu gewoon in het panel, geen redirect); de derde en vierde SLAGEN al (geen gate aanwezig / role-gate bestaat al).

- [ ] **Step 3: Write the middleware**

Create `app/Http/Middleware/EnforceStaffTwoFactor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt bevestigde 2FA af voor staff op de Filament-adminpanel.
 *
 * Draait ná {@see \App\Http\Middleware\RoleMiddleware} (`role:admin,moderator`)
 * in de panel-`authMiddleware`, dus de gebruiker is gegarandeerd ingelogd én
 * staff. Een staffer zonder `two_factor_confirmed_at` wordt naar de
 * 2FA-instelpagina gestuurd tot hij inschrijft. De instelpagina zelf is een
 * front-end-route buiten deze middleware, dus er is geen redirect-lus.
 */
class EnforceStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->two_factor_confirmed_at === null) {
            return redirect()->route('profile.security.2fa')->with(
                'status',
                __('Als moderator of admin is tweefactor-authenticatie verplicht. Stel het in om verder te gaan.'),
            );
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Wire it into the panel**

In `app/Providers/Filament/AdminPanelProvider.php`, voeg de middleware achteraan de `authMiddleware`-lijst toe (ná de rol-gate) en importeer 'm. Van:

```php
            ->authMiddleware([
                Authenticate::class,
                'role:admin,moderator',
            ]);
```

naar:

```php
            ->authMiddleware([
                Authenticate::class,
                'role:admin,moderator',
                EnforceStaffTwoFactor::class,
            ]);
```

Voeg bovenaan het bestand de import toe bij de bestaande `use`-regels:

```php
use App\Http\Middleware\EnforceStaffTwoFactor;
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/EnforceStaffTwoFactorTest.php`
Expected: PASS — alle vier groen.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnforceStaffTwoFactor.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/Admin/EnforceStaffTwoFactorTest.php
git commit -m "Enforce confirmed 2FA for staff on the admin panel"
```

---

### Task 2: Het `/admin/login`-gat dichten

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (`->login()` verwijderen uit de `panel()`-keten, regel 33)
- Test: `tests/Feature/Admin/PanelLoginBypassTest.php`

**Interfaces:**
- Consumes: de front-end `route('login')` (bestaand, draait de TOTP-challenge voor bevestigde accounts).
- Produces: uitgelogde panel-toegang redirect naar `/login`; `/admin/login` bestaat niet meer.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/PanelLoginBypassTest.php`:

```php
<?php

declare(strict_types=1);

it('sends an unauthenticated visitor from the panel to the app login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('no longer exposes a standalone admin login route', function () {
    $this->get('/admin/login')->assertNotFound();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/PanelLoginBypassTest.php`
Expected: FAIL — zolang `->login()` er nog is, redirect `/admin` naar Filaments eigen `/admin/login` (niet naar `route('login')`), en `/admin/login` geeft 200 i.p.v. 404.

- [ ] **Step 3: Remove Filament's own login page**

In `app/Providers/Filament/AdminPanelProvider.php`, verwijder de `->login()`-aanroep uit de `panel()`-keten. Van:

```php
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
```

naar:

```php
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
```

Geen andere wijziging: `getLoginUrl()` geeft nu `null`, waardoor Laravels handler uitgelogde bezoekers naar `route('login')` stuurt (zie Global Constraints), en de `/admin/login`-route wordt niet meer geregistreerd.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/PanelLoginBypassTest.php`
Expected: PASS — beide groen.

- [ ] **Step 5: Run Task 1's tests to confirm no regression**

De gate en het login-gat delen `AdminPanelProvider`; bevestig dat Taak 1 nog groen is.

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/EnforceStaffTwoFactorTest.php`
Expected: PASS — ongewijzigd groen (de `actingAs`-tests zijn ingelogd, dus de guest-redirect raakt ze niet).

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php tests/Feature/Admin/PanelLoginBypassTest.php
git commit -m "Route admin panel auth through the app login (close /admin/login bypass)"
```

---

### Task 3: `user:reset-2fa`-noodluik-commando

**Files:**
- Create: `app/Console/Commands/ResetTwoFactor.php`
- Test: `tests/Feature/Console/ResetTwoFactorTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`email`, `two_factor_secret` [encrypted cast], `two_factor_recovery_codes` [encrypted:array cast], `two_factor_confirmed_at` [datetime cast]).
- Produces: artisan-commando `user:reset-2fa {email}` dat de drie 2FA-velden op `null` zet; exit 0 bij succes, exit 1 bij onbekend adres.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Console/ResetTwoFactorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

it('wipes all three 2FA fields for an existing user', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill([
        'two_factor_secret' => 'PLAINSECRET',
        'two_factor_recovery_codes' => ['codeA', 'codeB'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('user:reset-2fa', ['email' => 'staff@example.com'])
        ->assertExitCode(0);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('matches the email case-insensitively', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->artisan('user:reset-2fa', ['email' => 'STAFF@EXAMPLE.COM'])
        ->assertExitCode(0);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('fails with exit code 1 for an unknown email and changes nothing', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->artisan('user:reset-2fa', ['email' => 'nobody@example.com'])
        ->assertExitCode(1);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Console/ResetTwoFactorTest.php`
Expected: FAIL — `Command "user:reset-2fa" is not defined`.

- [ ] **Step 3: Write the command**

Create `app/Console/Commands/ResetTwoFactor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Noodluik: wis de 2FA van één gebruiker op e-mailadres.
 *
 * Bestaansrecht is de laatste-admin-lockout: verliest de enige admin zijn
 * authenticator én zijn recovery-codes, dan is de Filament-UserResource
 * (waar admins elkaars 2FA resetten) onbereikbaar. Dit commando is dan de
 * enige weg terug, zonder handmatig DB-werk.
 *
 * De gereset gebruiker wordt bij de volgende paneltoegang door
 * {@see \App\Http\Middleware\EnforceStaffTwoFactor} weer naar de instelpagina
 * gestuurd, dus 2FA blijft verplicht — alleen opnieuw ingeschreven.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'user:reset-2fa {email : Het e-mailadres van de gebruiker}';

    protected $description = 'Wis de 2FA van een gebruiker (noodluik tegen een uitgesloten admin).';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("Geen gebruiker gevonden voor {$email}.");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("2FA gewist voor {$user->email}; de gebruiker moet opnieuw inschrijven.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Console/ResetTwoFactorTest.php`
Expected: PASS — alle drie groen.

- [ ] **Step 5: Run the full suite once before finishing**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Admin/ tests/Feature/Console/ResetTwoFactorTest.php`
Expected: PASS — de nieuwe Admin- en Console-tests samen groen.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ResetTwoFactor.php tests/Feature/Console/ResetTwoFactorTest.php
git commit -m "Add user:reset-2fa emergency command"
```

---

## Uitrol (na merge)

Code + één middleware + één commando + panel-config. Geen migratie, geen Dockerfile (dus file-sync, geen image-rebuild).

1. File-sync naar LXC 214: `app/Http/Middleware/EnforceStaffTwoFactor.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Console/Commands/ResetTwoFactor.php` (chown 1000:1000).
2. **`route:clear && route:cache`** (als www-data) — verplicht: het weghalen van `->login()` verwijdert de `/admin/login`-route en registreert de panel-routes opnieuw; een verouderde route-cache is de homelab-500-valkuil.
3. `docker compose -f docker-compose.prod.yml restart php-fpm` (opcache pikt de nieuwe middleware), daarna `restart nginx` (502-guard).
4. Verifieer: uitgelogd `curl -sI https://cloudmarktplaats.nl/admin` → redirect naar `/login`; `curl -s -o /dev/null -w "%{http_code}" https://cloudmarktplaats.nl/admin/login` → 404. Bevestig met een staff-testaccount dat inloggen zonder 2FA naar de instelpagina leidt.

**Let op:** bestaande staff zonder 2FA worden bij de eerstvolgende paneltoegang naar de instelpagina gestuurd — communiceer dit vooraf, zodat een admin niet verrast wordt. Zorg dat minstens één admin 2FA instelt vóór de uitrol, of direct erna, om lockout-ongemak te vermijden.

---

## Self-Review

**Spec-dekking:**
- Deel 1 (inschrijf-gate) → Taak 1. ✅
- Deel 2 (login-gat dichten via front-end `/login`, `/admin/login` weg) → Taak 2. ✅
- Deel 3 (`user:reset-2fa`, case-insensitief, forceFill drie velden) → Taak 3. ✅
- Gewone users ongemoeid (403, geen redirect) → Taak 1, test 4. ✅
- Bestaande 2FA-componenten niet gewijzigd → geen enkel bestand ervan in het plan. ✅
- Geen migratie/Dockerfile → bevestigd; alleen middleware/provider/command. ✅
- Case-insensitieve e-mail + forceFill → Taak 3, Step 3 + tests. ✅

**Placeholder-scan:** geen TBD/TODO; alle code volledig. ✅

**Type-consistentie:** `EnforceStaffTwoFactor::handle(Request, Closure): Response` — gedefinieerd in Taak 1, gerefereerd in `authMiddleware` (Taak 1 Step 4) en genoemd in de command-docblock (Taak 3). `two_factor_confirmed_at`/`two_factor_secret`/`two_factor_recovery_codes` consistent met het User-model. `route('profile.security.2fa')` en `route('login')` conform bestaande routes. `self::SUCCESS`/`self::FAILURE` (0/1) conform Laravel `Command`. ✅
