# login_count kolom op users

**Datum:** 2026-07-26
**Status:** ontwerp

## Probleem

We bewaren alleen `last_login_at` (het *laatste* inlogmoment) — elke login overschrijft de vorige waarde. We kunnen dus niet zien *hoe vaak* iemand inlogt. Op dit moment (prod, 228 users) is de enige beschikbare metric "ooit / nooit / laatste 7d / 30d".

## Doel

Een teller die per user bijhoudt hoe vaak er is ingelogd, zodat login-frequentie meetbaar wordt.

## Ontwerp

### 1. Migratie

Voeg kolom toe aan `users`:

- `login_count` — unsigned integer, `NOT NULL`, default `0`, geplaatst na `last_login_ip`.

**Backfill:** users met `last_login_at IS NOT NULL` krijgen `login_count = 1` (ze logden minstens één keer in; het echte historische aantal is onbekend en niet reconstrueerbaar). Alle overige users blijven op `0`. De `down()` dropt de kolom.

### 2. Centraliseren van de login-registratie

Vandaag herhalen vijf plekken hetzelfde blok:

```php
$user->forceFill([
    'last_login_at' => now(),
    'last_login_ip' => request()->ip(),
])->save();
```

Call sites:
- `app/Livewire/Auth/Login.php`
- `app/Livewire/Auth/TwoFactorChallenge.php`
- `app/Livewire/Auth/SiweOnboarding.php`
- `app/Http/Controllers/Auth/OAuthController.php`
- `app/Http/Controllers/Auth/Web3Controller.php`

Vervang dat door één methode op `User`:

```php
public function recordLogin(?string $ip): void
{
    $this->increment('login_count', 1, [
        'last_login_at' => now(),
        'last_login_ip' => $ip,
    ]);
}
```

`increment()` doet dit in één atomische `UPDATE ... SET login_count = login_count + 1, ...`, dus race-safe (twee gelijktijdige logins tellen allebei). Elke call site wordt `$user->recordLogin(request()->ip())`.

**Let op de 2FA-flow:** bij wachtwoord-login met 2FA wordt `recordLogin` pas aangeroepen ná de 2FA-challenge (in `TwoFactorChallenge`), niet in `Login`. `Login` zet daar alleen de pending-sessie. Dit gedrag blijft ongewijzigd — één login = één increment, ook met 2FA.

### 3. Model

- Cast `'login_count' => 'integer'` toevoegen aan `casts()`.
- `login_count` **niet** in `$fillable` (wordt alleen via `recordLogin()`/`increment()` gezet, nooit via massa-assignment).

## Tests

- Login zonder 2FA verhoogt `login_count` van 0 → 1 → 2 bij herhaalde logins.
- 2FA-flow: increment gebeurt één keer, ná de challenge (niet dubbel).
- OAuth-login verhoogt de teller.
- `recordLogin()` zet ook `last_login_at` en `last_login_ip` (geen regressie op bestaand gedrag).

## Deploy

Standaard runbook: file-sync naar LXC 214, `artisan migrate --force` in de web-container, nginx herstarten na php-fpm. Migratie is additief + één backfill-UPDATE; geen downtime.

## Buiten scope (YAGNI)

- Geen login-historie/audit-tabel (losse events, IP's, user-agents per login). Als we ooit per-login detail willen, is dat een aparte `login_events`-tabel — dit ontwerp levert alleen de teller.
- Geen admin-UI/kolom in Filament. Voorlopig alleen via psql opvraagbaar.
