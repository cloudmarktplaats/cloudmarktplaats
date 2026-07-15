# Verkeer meten zonder tracker — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zien wat een seller-share en de LinkedIn-post opleveren, zonder tracker, zonder cookie, en zonder de privacyverklaring te raken.

**Architecture:** nginx logt al alles wat we willen weten (referrer + querystring met UTM's); we gooien het IP eruit en lezen wat overblijft. Eén `log_format` in JSON, één artisan-command dat het aggregeert, en housekeeping zodat logs niet ongemerkt groeien. Geen client-side JS, geen extra container, geen database-tabel.

**Tech Stack:** nginx 1.27 (`escape=json`), Laravel 11 artisan command, Pest.

**Spec:** `docs/superpowers/specs/2026-07-14-privacy-first-traffic-analytics-design.md`

## Global Constraints

- **Testen draait als www-data:** `docker compose exec -T -u www-data php-fpm php artisan test`. Artisan als root chownt `storage/` naar root en 500't daarna de web-worker.
- **Pint als uid 1000:** `docker compose exec -T -u 1000 php-fpm ./vendor/bin/pint --test`
- **PHPStan als uid 1000, met `--memory-limit=1G`** — de default limiet klapt met een misleidende "Found 2 errors": `docker compose exec -T -u 1000 -e TMPDIR=/tmp/pstan php-fpm sh -c 'mkdir -p /tmp/pstan && ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G'`
- **`declare(strict_types=1);`** in elk PHP-bestand. Code-comments Engels.
- **Console-output is Nederlands** (het is een intern rapport voor Nick), maar gebruikt géén `__()` — net als de mail-templates in deze codebase.
- **Er komt GEEN IP in de log.** Dat is de hele belofte; een test bewaakt het.
- **De privacyverklaring wordt NIET gewijzigd.** Als een taak dat nodig lijkt te maken, stop en escaleer.

## Afwijking van de spec (bewust)

De spec stelt een combined-achtig `log_format` voor dat je met een regex moet parsen. Dit plan gebruikt **`escape=json`** (nginx ≥ 1.11.8; prod draait 1.27.5). Bewezen op de lokale container:

```json
{"t":"2026-07-15T09:55:15+00:00","m":"GET","u":"/listings?utm_source=linkedin&utm_medium=social","s":200,"ref":"android-app://com.linkedin.android/","ua":"curl/8.12.1"}
```

Reden: een user-agent of referrer met een quote erin sloopt een regex-parser stil; `escape=json` regelt de escaping en de parser wordt `json_decode`. Zelfde data, minder manieren om fout te gaan.

---

### Task 1: nginx logt zonder IP naar een leesbaar bestand

**Files:**
- Create: `storage/nginx/.gitignore`
- Modify: `storage/.gitignore`
- Modify: `docker/nginx/default.conf`

**Interfaces:**
- Produces: `storage/nginx/access.log` — één JSON-object per regel met de keys `t` (ISO-8601), `m` (method), `u` (request_uri incl. querystring), `s` (status, int), `ref` (referer, `""` indien afwezig), `ua` (user agent). Task 2 leest exact deze keys.

**LET OP — dit kan de site platleggen.** Ontbreekt de logmap, dan weigert nginx te starten:

```
nginx: [emerg] open() "/app/storage/nginx/access.log" failed (2: No such file or directory)
nginx: configuration file /etc/nginx/nginx.conf test failed
```

`nginx -t` faalt en een restart brengt hem niet terug. `storage/` is een bind-mount (`./:/app`), dus de map moet op de host bestaan **voordat** de config live gaat. Vandaar dat de map in dezelfde taak zit als de config, en dat Step 5 `nginx -t` draait vóór de reload.

- [ ] **Step 1: Maak de logmap met het bestaande gitignore-patroon**

`storage/nginx/.gitignore` (identiek aan `storage/logs/.gitignore`):

```
*
!.gitignore
```

En voeg `nginx` toe aan `storage/.gitignore` (bevat nu `app`, `framework`, `logs`):

```
app
framework
logs
nginx
```

- [ ] **Step 2: Schrijf de failing test — geen IP in het formaat**

`tests/Feature/Analytics/NginxLogFormatTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * The whole privacy claim rests on this: we publish "geen trackers" and promise
 * IPs are stripped within 24h, so the access log must not contain one at all.
 * This test fails loudly if someone reintroduces $remote_addr or
 * $http_x_forwarded_for — including via nginx's `combined` default, which was
 * writing real IPs into an unrotated docker json-file for 11 days.
 */
it('logs no IP address in the nginx access log format', function () {
    $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    expect($conf)
        ->toContain('log_format cmp_privacy')
        ->not->toContain('$remote_addr')
        ->not->toContain('$http_x_forwarded_for')
        ->not->toContain('$proxy_add_x_forwarded_for')
        ->not->toContain('$binary_remote_addr');
});

it('sends the access log to a file the app can read, not to stdout', function () {
    $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    // stdout goes to docker's json-file driver, which the app cannot read and
    // which grew unrotated for 11 days.
    expect($conf)->toContain('access_log /app/storage/nginx/access.log cmp_privacy;');
});

it('keeps the access log out of storage/logs, where laravel.log lives', function () {
    $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    // nginx's master runs as root; laravel.log is written by www-data. Mixing
    // owners in one directory is exactly how web logging broke on 2026-07-03.
    expect($conf)->not->toContain('/app/storage/logs/access.log');
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=NginxLogFormat`
Expected: FAIL — `log_format cmp_privacy` bestaat nog niet.

- [ ] **Step 4: Voeg het logformaat toe**

In `docker/nginx/default.conf`, **binnen** het `server`-blok, direct ná `client_max_body_size 88m;`:

```nginx
    # Access logging without an IP.
    #
    # nginx's `combined` default logs $remote_addr — and behind our Caddy proxy
    # that resolves to the visitor's real IP via X-Forwarded-For. Those lines went
    # to stdout, i.e. docker's json-file driver, which had no max-size: 11 days of
    # real IPs sat on the box while we publish that IPs are stripped within 24h
    # (IpStripperJob does that, but only in the database).
    #
    # Dropping the IP means there is no personal data here at all, so nothing to
    # retain and nothing to strip. What stays is what we actually want to know:
    # when, what was requested (the querystring carries our utm_* params), the
    # status, where the visitor came from, and enough UA to spot bots.
    #
    # escape=json (nginx >= 1.11.8; prod runs 1.27.5) makes every line valid JSON,
    # so a quote in a user agent cannot break the parser. See TrafficReport.
    log_format cmp_privacy escape=json
        '{"t":"$time_iso8601","m":"$request_method","u":"$request_uri",'
        '"s":$status,"ref":"$http_referer","ua":"$http_user_agent"}';

    # A file, not stdout: the app has to read this back (php artisan traffic:report),
    # and docker logs are not reachable from PHP. Deliberately NOT in storage/logs/ —
    # that is www-data's laravel.log and nginx' master runs as root; mixed owners in
    # one directory is what broke web logging on 2026-07-03.
    access_log /app/storage/nginx/access.log cmp_privacy;
```

`error_log` blijft ongemoeid (gaat naar stderr), zodat `docker compose logs nginx` bruikbaar blijft. `access_log off;` in het `/build|/fonts`-blok blijft ook staan: statics zijn ruis.

- [ ] **Step 5: Verifieer de config vóór je nginx herstart**

Run:
```bash
mkdir -p storage/nginx
docker compose exec -T nginx nginx -t
```
Expected: `configuration file /etc/nginx/nginx.conf test is successful`

**Faalt dit met `[emerg] open() ... failed`, herstart nginx dan NIET** — de map ontbreekt. Maak hem eerst.

- [ ] **Step 6: Herstart en bewijs dat er een regel wordt weggeschreven zonder IP**

Run:
```bash
docker compose restart nginx && sleep 2
curl -s -o /dev/null -H "Referer: android-app://com.linkedin.android/" "localhost:8080/?utm_source=linkedin&utm_medium=social&utm_campaign=seller_share"
sleep 1
tail -1 storage/nginx/access.log
```
Expected: één JSON-regel met `"u":"/?utm_source=linkedin&..."` en `"ref":"android-app://com.linkedin.android/"`, en **geen IP-adres**.

- [ ] **Step 7: Run de tests**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=NginxLogFormat`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add storage/nginx/.gitignore storage/.gitignore docker/nginx/default.conf tests/Feature/Analytics/NginxLogFormatTest.php
git commit -m "nginx: access log zonder IP naar storage/nginx/access.log

combined logde \$remote_addr (achter Caddy = het echte IP) naar stdout, en
docker's json-file driver had geen max-size: 11 dagen aan IP's op de bak,
terwijl we publiceren dat IP's binnen 24u gestript worden. IpStripperJob doet
dat wel, maar alleen in de database.

Zonder IP is er geen persoonsgegeven, dus niets te bewaren. Wat blijft is wat
we willen weten: tijd, request (met utm_*), status, referer, UA. escape=json
zodat een quote in een UA de parser niet sloopt."
```

---

### Task 2: `php artisan traffic:report`

**Files:**
- Create: `app/Console/Commands/TrafficReport.php`
- Create: `tests/Feature/Analytics/TrafficReportTest.php`

**Interfaces:**
- Consumes: `storage/nginx/access.log` uit Task 1 — JSON per regel, keys `t`, `m`, `u`, `s`, `ref`, `ua`.
- Produces: `php artisan traffic:report [--days=7]`, exit 0. Leest het pad via `storage_path('nginx/access.log')`.

**Wat het beantwoordt:** drie tabellen — verkeer per referrer (LinkedIn vs direct), per `utm_source`/`utm_campaign` (wat levert een seller-share op), en top-pagina's.

**Normalisatie-regels (uit de spec):**
- Referrers worden teruggebracht tot hun herkomst: `android-app://com.linkedin.android/` én `https://www.linkedin.com/feed/` tellen allebei als `linkedin`.
- Onze eigen host telt als `intern` en staat apart — anders telt elke doorklik binnen de site als "bezoek".
- Lege referrer = `direct`.
- Alleen paginabezoeken tellen: `/storage/…`, `/build/…`, `/livewire/…`, `/fonts/…` en `/healthz` zijn geen pagina's. Filter op het **pad**, niet op content-type — dat staat niet in de log.
- Bots eruit op UA (`bot`, `crawler`, `spider`, `curl`, `wget`, `headless`, `python-requests`), case-insensitive.

- [ ] **Step 1: Schrijf de failing test**

`tests/Feature/Analytics/TrafficReportTest.php`:

```php
<?php

declare(strict_types=1);

beforeEach(function () {
    $this->logPath = storage_path('nginx/access.log');
    @mkdir(dirname($this->logPath), 0775, true);
    file_put_contents($this->logPath, '');
});

afterEach(function () {
    @unlink($this->logPath);
});

function logLine(array $overrides = []): string
{
    return json_encode(array_merge([
        't' => now()->toIso8601String(),
        'm' => 'GET',
        'u' => '/',
        's' => 200,
        'ref' => '',
        'ua' => 'Mozilla/5.0 (Linux; Android 10; K) Chrome/150.0.0.0 Mobile Safari/537.36',
    ], $overrides), JSON_UNESCAPED_SLASHES)."\n";
}

it('groups visits by referrer origin', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['ref' => 'android-app://com.linkedin.android/']),
        logLine(['ref' => 'https://www.linkedin.com/feed/']),
        logLine(['ref' => '']),
    ]));

    // Both LinkedIn shapes are one source; an empty referrer is direct.
    $this->artisan('traffic:report')
        ->expectsOutputToContain('linkedin')
        ->expectsOutputToContain('direct')
        ->assertSuccessful();
});

it('counts utm sources from the query string', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['u' => '/listings/01ABC-x?utm_source=linkedin&utm_medium=social&utm_campaign=seller_share']),
        logLine(['u' => '/listings/01ABC-x?utm_source=copy&utm_medium=social&utm_campaign=seller_share']),
        logLine(['u' => '/']),
    ]));

    $this->artisan('traffic:report')
        ->expectsOutputToContain('linkedin')
        ->expectsOutputToContain('seller_share')
        ->assertSuccessful();
});

it('ignores assets, livewire and healthz', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['u' => '/storage/listings/01ABC/1/card.webp']),
        logLine(['u' => '/build/assets/app-DSggz4fK.css']),
        logLine(['u' => '/livewire/update']),
        logLine(['u' => '/healthz']),
        logLine(['u' => '/listings']),
    ]));

    // Only /listings is a page view; a photo is not a visit.
    $this->artisan('traffic:report')
        ->expectsOutputToContain('1 paginabezoek')
        ->assertSuccessful();
});

it('ignores bots', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['ua' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']),
        logLine(['ua' => 'curl/8.12.1']),
        logLine(['ua' => 'Mozilla/5.0 (Linux; Android 10; K) Chrome/150.0']),
    ]));

    $this->artisan('traffic:report')
        ->expectsOutputToContain('1 paginabezoek')
        ->assertSuccessful();
});

it('honours --days', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['t' => now()->subDays(30)->toIso8601String(), 'u' => '/oud']),
        logLine(['t' => now()->subHours(2)->toIso8601String(), 'u' => '/nieuw']),
    ]));

    $this->artisan('traffic:report', ['--days' => 7])
        ->expectsOutputToContain('1 paginabezoek')
        ->assertSuccessful();
});

it('says so plainly when there is no log yet', function () {
    @unlink($this->logPath);

    $this->artisan('traffic:report')
        ->expectsOutputToContain('Geen logbestand')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=TrafficReport`
Expected: FAIL — command `traffic:report` bestaat niet.

- [ ] **Step 3: Implementeer het command**

`app/Console/Commands/TrafficReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Reads nginx's access log and answers three questions: where does traffic come
 * from, what do seller-shares bring in, and which pages get visited.
 *
 * No tracker, no cookie, no JS: the log already carries the referrer and the
 * querystring with our utm_* params. The IP is not logged at all (see
 * docker/nginx/default.conf), which is why this reports *visits*, not unique
 * visitors — that is the deliberate trade.
 */
class TrafficReport extends Command
{
    protected $signature = 'traffic:report {--days=7 : Aantal dagen terugkijken}';

    protected $description = 'Verkeer per referrer, UTM-bron en pagina (uit de nginx-log)';

    /** Paths that are not page views. */
    private const IGNORED_PREFIXES = ['/storage/', '/build/', '/fonts/', '/livewire/', '/healthz', '/up', '/favicon'];

    private const BOT_MARKERS = ['bot', 'crawler', 'spider', 'curl', 'wget', 'headless', 'python-requests', 'go-http'];

    public function handle(): int
    {
        $path = storage_path('nginx/access.log');

        if (! is_readable($path)) {
            $this->warn("Geen logbestand op {$path} — draait nginx met het cmp_privacy log_format?");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $since = CarbonImmutable::now()->subDays($days);

        $referrers = [];
        $utms = [];
        $pages = [];
        $visits = 0;

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Kan {$path} niet openen.");

            return self::FAILURE;
        }

        while (($line = fgets($handle)) !== false) {
            $row = json_decode(trim($line), true);
            if (! is_array($row) || ! isset($row['u'], $row['t'])) {
                continue;
            }
            if (CarbonImmutable::parse((string) $row['t'])->lt($since)) {
                continue;
            }
            if ($this->isBot((string) ($row['ua'] ?? ''))) {
                continue;
            }

            $uri = (string) $row['u'];
            $path_only = (string) parse_url($uri, PHP_URL_PATH);
            if ($this->isIgnored($path_only)) {
                continue;
            }

            $visits++;
            $pages[$path_only] = ($pages[$path_only] ?? 0) + 1;

            $origin = $this->referrerOrigin((string) ($row['ref'] ?? ''));
            $referrers[$origin] = ($referrers[$origin] ?? 0) + 1;

            $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
            parse_str($query, $params);
            $source = isset($params['utm_source']) ? (string) $params['utm_source'] : null;
            if ($source !== null) {
                $campaign = isset($params['utm_campaign']) ? (string) $params['utm_campaign'] : '—';
                $key = $source.' / '.$campaign;
                $utms[$key] = ($utms[$key] ?? 0) + 1;
            }
        }
        fclose($handle);

        $this->newLine();
        $this->info("{$visits} paginabezoeken in de laatste {$days} dag(en)");
        $this->line('<comment>Bezoeken, geen unieke bezoekers: we loggen geen IP.</comment>');
        $this->newLine();

        $this->table(['Referrer', 'Bezoeken'], $this->rows($referrers));
        $this->newLine();

        if ($utms === []) {
            $this->line('Geen UTM-getagd verkeer in deze periode.');
        } else {
            $this->table(['utm_source / campaign', 'Bezoeken'], $this->rows($utms));
        }
        $this->newLine();

        $this->table(['Pagina', 'Bezoeken'], $this->rows($pages, 10));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{0: string, 1: int}>
     */
    private function rows(array $counts, int $limit = 15): array
    {
        arsort($counts);

        $rows = [];
        foreach (array_slice($counts, 0, $limit, true) as $key => $n) {
            $rows[] = [$key, $n];
        }

        return $rows;
    }

    private function isBot(string $ua): bool
    {
        $ua = strtolower($ua);
        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnored(string $path): bool
    {
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collapse a referrer to its origin: the LinkedIn app and the LinkedIn web
     * feed are one source, not two. Our own host is reported separately so an
     * internal click-through doesn't read as an inbound visit.
     */
    private function referrerOrigin(string $referrer): string
    {
        if ($referrer === '' || $referrer === '-') {
            return 'direct';
        }

        $host = (string) (parse_url($referrer, PHP_URL_HOST) ?? '');

        // android-app://com.linkedin.android/ has no host, only a path.
        if ($host === '' && str_contains($referrer, 'linkedin')) {
            return 'linkedin';
        }
        if ($host === '') {
            return 'overig';
        }

        $appHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? '');
        if ($host === $appHost) {
            return 'intern';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        foreach (['linkedin', 'tweakers', 'reddit', 'google', 'maindeck'] as $known) {
            if (str_contains($host, $known)) {
                return $known;
            }
        }

        return $host;
    }
}
```

- [ ] **Step 4: Run tests**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=TrafficReport`
Expected: PASS (6 tests)

- [ ] **Step 5: Draai het tegen echte data**

Run:
```bash
curl -s -o /dev/null -H "Referer: android-app://com.linkedin.android/" "localhost:8080/?utm_source=linkedin&utm_medium=social&utm_campaign=seller_share"
curl -s -o /dev/null "localhost:8080/listings"
docker compose exec -T -u www-data php-fpm php artisan traffic:report --days=1
```
Expected: `linkedin` en `direct` in de referrer-tabel, `linkedin / seller_share` in de UTM-tabel, `/` en `/listings` bij de pagina's, en géén `/storage/...`-regels.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/TrafficReport.php tests/Feature/Analytics/TrafficReportTest.php
git commit -m "traffic:report — verkeer per referrer, UTM en pagina

Leest de nginx-log (JSON per regel) en beantwoordt: waar komt verkeer vandaan,
wat levert een seller-share op, welke pagina's worden bezocht. De UTM's uit PR
#4 werden tot nu toe nergens gelezen.

Bezoeken, geen unieke bezoekers — we loggen geen IP, en dat is de bewuste ruil.
LinkedIn-app en LinkedIn-web tellen als één bron; onze eigen host staat apart
zodat een interne doorklik geen bezoek lijkt."
```

---

### Task 3: Housekeeping — rotatie en truncate

**Files:**
- Modify: `docker-compose.prod.yml`
- Modify: `docker-compose.yml`
- Modify: `bootstrap/app.php` (`withSchedule`, bij `IpStripperJob`)
- Test: `tests/Feature/Analytics/TrafficLogRotationTest.php`

**Interfaces:**
- Consumes: `storage/nginx/access.log` uit Task 1.
- Produces: niets voor latere taken.

**Waarom truncate en geen rename:** nginx houdt de filehandle open. Na een `mv` blijft hij naar de oude inode schrijven tot een `USR1`-signaal — dan lijkt de log leeg terwijl er wel degelijk verkeer is. `> file` (truncate) laat de handle intact.

Zonder IP is er geen privacy-reden om te roteren; dit gaat puur over schijfruimte.

- [ ] **Step 1: Docker log-rotatie als vangnet**

In **beide** compose-files, bij elke service met `restart: unless-stopped` (prod) resp. elke service (dev), of als het korter kan via een YAML-anchor bovenaan:

```yaml
x-logging: &default-logging
  driver: json-file
  options:
    max-size: "10m"
    max-file: "2"
```

en per service:

```yaml
    logging: *default-logging
```

Reden (comment in de file, boven het anchor):

```yaml
# Docker's json-file driver has no size limit by default. nginx's access log
# went to stdout and sat here unrotated for 11 days — 2.9MB of real IPs, while
# we publish that IPs are stripped within 24h. Access logging now goes to a file
# without IPs (docker/nginx/default.conf); this cap is the backstop for
# error_log and for every other container.
```

- [ ] **Step 2: Schrijf de failing test voor de scheduled truncate**

`tests/Feature/Analytics/TrafficLogRotationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

it('schedules a weekly truncate of the nginx access log', function () {
    $events = collect(app(Schedule::class)->events());

    $truncate = $events->first(fn ($e): bool => str_contains((string) $e->description, 'traffic:truncate-log')
        || str_contains((string) $e->command, 'traffic:truncate-log'));

    expect($truncate)->not->toBeNull()
        ->and($truncate->expression)->toBe('0 4 * * 0'); // zondag 04:00
});

it('truncates the log without deleting it', function () {
    $path = storage_path('nginx/access.log');
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, str_repeat("{\"t\":\"x\"}\n", 100));

    expect(filesize($path))->toBeGreaterThan(0);

    $this->artisan('traffic:truncate-log')->assertSuccessful();

    // The file must still exist: nginx holds the handle open, and deleting it
    // would leave nginx writing to an unlinked inode until a USR1 signal.
    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBe(0);

    @unlink($path);
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=TrafficLogRotation`
Expected: FAIL — command bestaat niet, schedule bestaat niet.

- [ ] **Step 4: Implementeer het truncate-command**

`app/Console/Commands/TruncateTrafficLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Empties the nginx access log.
 *
 * Truncate, not rename: nginx keeps the file handle open, so after a `mv` it
 * would keep writing to the old inode until it gets a USR1 — the log would look
 * empty while traffic was still flowing. `> file` keeps the handle valid.
 *
 * There is no privacy reason to rotate (the log holds no IP — see
 * docker/nginx/default.conf); this is purely about disk.
 */
class TruncateTrafficLog extends Command
{
    protected $signature = 'traffic:truncate-log';

    protected $description = 'Leegt storage/nginx/access.log (schijfruimte; bevat geen persoonsgegevens)';

    public function handle(): int
    {
        $path = storage_path('nginx/access.log');

        if (! file_exists($path)) {
            $this->info('Geen logbestand — niets te doen.');

            return self::SUCCESS;
        }

        $before = (int) filesize($path);
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Kan {$path} niet legen.");

            return self::FAILURE;
        }
        fclose($handle);

        $this->info(sprintf('access.log geleegd (%d KB vrijgemaakt).', (int) round($before / 1024)));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Hang hem in de scheduler**

In `bootstrap/app.php`, binnen `withSchedule`, direct ná `$schedule->job(new IpStripperJob)->hourly();`:

```php
        // Weekly truncate of the nginx access log. Not a retention measure —
        // that log holds no IP (see docker/nginx/default.conf) — purely so it
        // doesn't grow unbounded. Sunday 04:00, when nobody is reading reports.
        $schedule->command('traffic:truncate-log')->weeklyOn(0, '04:00');
```

- [ ] **Step 6: Run tests**

Run: `docker compose exec -T -u www-data php-fpm php artisan test --filter=TrafficLogRotation`
Expected: PASS (2 tests)

- [ ] **Step 7: Bevestig dat de rotatie-opties aankomen**

Run:
```bash
docker compose up -d nginx php-fpm && sleep 3
docker inspect cloudmarktplaats-nginx-1 --format '{{json .HostConfig.LogConfig}}'
```
Expected: `{"Type":"json-file","Config":{"max-file":"2","max-size":"10m"}}` — niet de lege config die er nu staat.

- [ ] **Step 8: Commit**

```bash
git add docker-compose.yml docker-compose.prod.yml bootstrap/app.php app/Console/Commands/TruncateTrafficLog.php tests/Feature/Analytics/TrafficLogRotationTest.php
git commit -m "Housekeeping: docker log-rotatie + wekelijkse truncate

json-file had een lege config: geen max-size, geen max-file. Daardoor stond er
11 dagen aan nginx-logs (met IP's) op de bak. Nu 10m/2 voor elke container.

De access log zelf wordt wekelijks getruncate — truncate, geen rename: nginx
houdt de handle open en zou na een mv naar de oude inode blijven schrijven."
```

---

### Task 4: Volledige suite + statische analyse

- [ ] **Step 1: Volledige suite**

Run: `docker compose exec -T -u www-data php-fpm php artisan test`
Expected: alles groen (338 + 11 nieuwe = 349).

- [ ] **Step 2: Pint**

Run: `docker compose exec -T -u 1000 php-fpm ./vendor/bin/pint --test`
Expected: PASS. Bij failures: `./vendor/bin/pint` zonder `--test`.

- [ ] **Step 3: PHPStan**

Run: `docker compose exec -T -u 1000 -e TMPDIR=/tmp/pstan php-fpm sh -c 'mkdir -p /tmp/pstan && ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G'`
Expected: `[OK] No errors`

- [ ] **Step 4: Commit eventuele fixes**

```bash
git add -A && git commit -m "Pint/PHPStan-fixes voor traffic:report"
```

---

## Deploy-aandachtspunten

Volgt `[[prod-deploy-runbook]]`, met drie dingen die specifiek hier misgaan:

1. **`mkdir -p storage/nginx` op prod vóór de config-sync.** Ontbreekt de map, dan start nginx niet ([emerg]) en is de site onbereikbaar. Draai daarna `docker compose exec nginx nginx -t` **voordat** je herstart.
2. **De compose-wijziging vereist `up -d`, geen `restart`** — log-opties worden alleen bij container-creatie toegepast. Dit hangt php-fpm om naar een nieuwe container, dus **daarna nginx herstarten** (anders 502 op een verouderd upstream-IP).
3. **De oude json-file logs met 11 dagen aan IP's verdwijnen met de oude containers** bij `up -d --force-recreate`. Verifieer na afloop met `docker inspect` dat de rotatie-opties staan én dat `storage/nginx/access.log` volloopt.
