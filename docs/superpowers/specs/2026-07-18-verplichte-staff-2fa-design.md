# Verplichte 2FA voor staff — ontwerp

**Datum:** 2026-07-18
**Doel:** tweefactor-authenticatie (TOTP) verplicht maken voor staff-accounts (rol `admin` en `moderator`) die de Filament-adminpanel gebruiken. Gewone gebruikers houden 2FA opt-in.

## Context

2FA bestaat al volledig en is opt-in:
- `App\Livewire\Profile\TwoFactorSetup` — inschakelen (`start`/`confirm`), uitschakelen (`disable`), recovery-codes roteren (`regenerate`). TOTP via `pragmarx/google2fa`, QR via `bacon/bacon-qr-code`, 8 recovery-codes, secret met `encrypted` cast.
- `App\Livewire\Auth\TwoFactorChallenge` — de challenge na primaire authenticatie. Rate-limited (5/gebruiker, 20/IP per 60s), accepteert TOTP (6 cijfers) of een recovery-code (verbruikt bij gebruik), wist de limiter bij succes.
- Kolommen op `users`: `two_factor_secret`, `two_factor_recovery_codes` (beide `text`, nullable, `hidden`), `two_factor_confirmed_at` (`timestamp`, nullable, `datetime`-cast). Een account "heeft 2FA" ⇔ `two_factor_confirmed_at !== null`.
- Alle drie de login-paden honoreren de challenge al: `App\Livewire\Auth\Login` (wachtwoord), `App\Http\Controllers\Auth\OAuthController` (OAuth) en `App\Http\Controllers\Auth\Web3Controller` (SIWE) checken `two_factor_confirmed_at` en sturen door naar de challenge.

Rolmodel: `users.role` enum `['user', 'moderator', 'admin']`. `User::hasRole(...)` en `User::canAccessPanel()` (= `hasRole('admin', 'moderator')`). De Filament-panel (`App\Providers\Filament\AdminPanelProvider`) gate't toegang via `authMiddleware([Authenticate::class, 'role:admin,moderator'])`.

**De feature is dus geen bouw maar een afdwinging + één beveiligingsgat dichten.**

## Scope

**In scope:**
1. **Inschrijf-gate:** staff zonder bevestigde 2FA kan de adminpanel niet gebruiken en wordt naar de 2FA-instelpagina gestuurd.
2. **Login-gat dichten:** panel-authenticatie loopt via de front-end `/login` (die de TOTP-challenge al draait), niet via Filaments losse `/admin/login`.
3. **Noodluik:** `php artisan user:reset-2fa {email}` om een lockout van de laatste admin te herstellen.

**Buiten scope (bewust):**
- Gewone gebruikers (`role = user`) — blijven opt-in, ongewijzigd.
- OAuth/SIWE opt-in-gebruikers — de provider/wallet levert al een factor; geen app-niveau-verplichting.
- SMS/telefoon als factor — telefoonnummer is PII die we niet willen (privacy by design). Alleen TOTP.
- Wijzigingen aan de challenge-/setup-componenten zelf — die bestaan en zijn getest.

## Deel 1 — Inschrijf-gate (`EnforceStaffTwoFactor`-middleware)

Nieuwe middleware `App\Http\Middleware\EnforceStaffTwoFactor`, toegevoegd achteraan de `authMiddleware`-lijst van de panel (ná `Authenticate` en `role:admin,moderator`, zodat de gebruiker gegarandeerd ingelogd én staff is).

Logica:
```
$user = $request->user();
if ($user->two_factor_confirmed_at === null) {
    return redirect()->route('profile.security.2fa')
        ->with('status', __('Als moderator of admin is tweefactor-authenticatie verplicht. Stel het in om verder te gaan.'));
}
return $next($request);
```

- De 2FA-instelpagina (`route('profile.security.2fa')`, front-end, `layouts.app`) valt buiten de panel-middleware, dus blijft bereikbaar tijdens de afdwinging. Idem uitloggen.
- Gevolg: een staffer zonder 2FA wordt naar de instelpagina gebounced, schrijft in, navigeert terug naar `/admin` en komt er nu wél in.
- Gewone gebruikers raken deze middleware nooit — ze worden al door `role:admin,moderator` met een 403 geweerd (ongewijzigd gedrag; géén 2FA-redirect voor niet-staff).

**Bootstrap-flow (belangrijk):** een staffer die nog geen 2FA heeft, logt via `/login` in zónder challenge (want `two_factor_confirmed_at` is null), krijgt op `/admin` de gate, schrijft in, en krijgt bij de vólgende login wél de challenge. Dit is de correcte, niet-blokkerende opstart.

## Deel 2 — Login-gat dichten (panel-auth via `/login`)

**Probleem:** de panel roept `->login()` aan, wat een zelfstandige loginpagina op `/admin/login` geeft. Die authenticeert direct via Filament en omzeilt de front-end-2FA-challenge. Zonder dit deel dwingt Deel 1 alleen af dát 2FA is ingesteld, niet dat de challenge bij elke login wordt gepasseerd.

**Oplossing:** verwijder `->login()` uit `AdminPanelProvider::panel()` en laat niet-geauthenticeerde panel-toegang doorverwijzen naar de bestaande `route('login')`. De front-end-login eindigt al met `redirect()->intended(...)`, dus:

```
uitgelogd → GET /admin
   → (Filament Authenticate) redirect naar /login  (intended = /admin)
   → primaire auth (wachtwoord / OAuth / wallet)
   → 2FA-challenge (voor bevestigde accounts)
   → redirect()->intended('/admin')
```

Implementatiedetail voor het plan: na het weglaten van `->login()` moet Filaments `Authenticate` naar `route('login')` verwijzen. Als de fallback niet vanzelf naar de app-login gaat, expliciet een guest-redirect zetten in `bootstrap/app.php` (`$middleware->redirectGuestsTo(fn () => route('login'))`). Verifieer dat `/admin/login` niet langer direct authenticeert (404 of redirect naar `/login`).

Dit hergebruikt de bestaande, geteste, rate-limited challenge — geen tweede challenge-implementatie om te onderhouden.

## Deel 3 — Noodluik (`user:reset-2fa`-commando)

`App\Console\Commands\ResetTwoFactor`, signature `user:reset-2fa {email}`.

- Zoek de gebruiker **case-insensitief** op e-mail (`whereRaw('lower(email) = ?', [Str::lower($email)])`) — hoofdlettergevoelige e-mail-lookups waren eerder een stille bug (login + reset dood zonder foutmelding). Normaliseer de invoer met `Str::lower(trim($email))` vóór de query.
- Niet gevonden → foutmelding + exit-code `1`, geen wijziging.
- Gevonden → `forceFill([...])->save()`:
  ```
  'two_factor_secret' => null,
  'two_factor_recovery_codes' => null,
  'two_factor_confirmed_at' => null,
  ```
  (`forceFill` want deze velden staan niet in `$fillable` — mass-assignment zou ze stil negeren.)
- Succes → bevestiging ("2FA gewist voor <email>; de gebruiker moet opnieuw inschrijven") + exit-code `0`.

Dit herstelt een lockout zonder handmatig DB-werk. Een staffer die zo gereset wordt, wordt bij de volgende `/admin`-toegang door Deel 1 weer naar de instelpagina gestuurd.

## Foutafhandeling & randgevallen

- **Staffer verliest authenticator:** gebruikt een recovery-code op de challenge (bestaand). Op zijn, of ook kwijt → een andere admin reset via de Filament-UserResource (bestaand), of het `user:reset-2fa`-commando.
- **Laatste admin volledig buitengesloten:** `user:reset-2fa` op de server. Dit is de enige rol die het commando bestaansrecht geeft.
- **Gedegradeerde staffer** (`admin`/`moderator` → `user`): raakt de gate niet meer (geen panel-toegang); hun 2FA blijft staan als persoonlijke opt-in. Geen speciale afhandeling nodig.
- **Race:** een staffer die tijdens een sessie 2FA uitschakelt via het profiel — de volgende panel-request raakt de gate en bounced hem naar setup. Correct.

## Bestanden

- **Maken:** `app/Http/Middleware/EnforceStaffTwoFactor.php`, `app/Console/Commands/ResetTwoFactor.php`
- **Wijzigen:** `app/Providers/Filament/AdminPanelProvider.php` (middleware toevoegen aan `authMiddleware`; `->login()` verwijderen), mogelijk `bootstrap/app.php` (guest-redirect naar `route('login')`)
- **Test maken:** `tests/Feature/Admin/EnforceStaffTwoFactorTest.php`, `tests/Feature/Console/ResetTwoFactorTest.php`
- **Geen migratie** (kolommen bestaan), geen wijziging aan de bestaande 2FA-componenten.

## Testplan (Pest)

**`EnforceStaffTwoFactorTest`:**
1. Admin zónder `two_factor_confirmed_at` → `GET /admin` redirect naar `route('profile.security.2fa')`.
2. Admin mét `two_factor_confirmed_at` → `GET /admin` → 200.
3. Moderator zónder 2FA → redirect (zelfde als admin).
4. Gewone gebruiker → `GET /admin` → 403 (géén 2FA-redirect; ongewijzigd rolgedrag).
5. `/admin/login` authenticeert niet meer direct (404 of redirect naar `/login`).

**`ResetTwoFactorTest`:**
6. Commando op een bestaand account wist alle drie de 2FA-velden (assert `two_factor_confirmed_at`, `two_factor_secret`, `two_factor_recovery_codes` alle `null`), exit-code 0.
7. Case-insensitieve match: `USER@EXAMPLE.COM` reset het account dat als `user@example.com` is opgeslagen.
8. Onbekend e-mailadres → exit-code 1, geen wijziging elders.

## Uitrol

Code + één middleware + één commando + panel-config. Geen migratie, geen Dockerfile-wijziging (dus file-sync, geen image-rebuild). Uitrol:

1. File-sync naar LXC 214 van de gewijzigde/nieuwe bestanden.
2. **`route:clear && route:cache`** — verplicht: het weglaten van `->login()` verwijdert de `/admin/login`-route, en de panel-routes worden opnieuw geregistreerd. Een verouderde route-cache is precies de homelab-500-valkuil.
3. `php-fpm` restart (opcache pikt de nieuwe middleware en `bootstrap/app.php`), daarna nginx restart (502-guard).

`config:cache` is niet nodig — er verandert geen `config/*.php` (`bootstrap/app.php` valt niet onder de config-cache en wordt bij de php-fpm-restart opnieuw geladen).

Verifieer op prod: uitgelogd `/admin` → redirect naar `/login`; een staff-testaccount zonder bevestigde 2FA wordt naar `/profile/security/2fa` gestuurd; een staffer mét 2FA komt normaal in het panel.
