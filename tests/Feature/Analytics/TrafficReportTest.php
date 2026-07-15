<?php

declare(strict_types=1);

beforeEach(function () {
    // A temp file, not storage/nginx/access.log: that one is written by the
    // real nginx master (root), and a test must never fight the live process
    // for it.
    $this->logPath = tempnam(sys_get_temp_dir(), 'traffic-test-').'.log';
    config(['cloudmarktplaats.traffic.access_log' => $this->logPath]);
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
        ->expectsOutputToContain('1 paginabezoeken in de laatste')
        ->assertSuccessful();
});

it('ignores bots', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['ua' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']),
        logLine(['ua' => 'curl/8.12.1']),
        logLine(['ua' => 'Mozilla/5.0 (Linux; Android 10; K) Chrome/150.0']),
    ]));

    $this->artisan('traffic:report')
        ->expectsOutputToContain('1 paginabezoeken in de laatste')
        ->assertSuccessful();
});

it('honours --days', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['t' => now()->subDays(30)->toIso8601String(), 'u' => '/oud']),
        logLine(['t' => now()->subHours(2)->toIso8601String(), 'u' => '/nieuw']),
    ]));

    $this->artisan('traffic:report', ['--days' => 7])
        ->expectsOutputToContain('1 paginabezoeken in de laatste')
        ->assertSuccessful();
});

it('says so plainly when there is no log yet', function () {
    @unlink($this->logPath);

    $this->artisan('traffic:report')
        ->expectsOutputToContain('Geen logbestand')
        ->assertSuccessful();
});

it('skips a line with an unparseable timestamp instead of crashing', function () {
    file_put_contents($this->logPath, implode('', [
        '{"t":"not-a-date","m":"GET","u":"/kapot","s":200,"ref":"","ua":"Mozilla/5.0"}'."\n",
        logLine(['u' => '/goed']),
    ]));

    // One malformed line (a truncated write, say) must not take the report down.
    $this->artisan('traffic:report')
        ->expectsOutputToContain('1 paginabezoeken in de laatste')
        ->assertSuccessful();
});

it('does not mistake a host that merely contains a known name', function () {
    file_put_contents($this->logPath, implode('', [
        logLine(['ref' => 'https://notlinkedin.example.com/x']),
        logLine(['ref' => 'https://www.linkedin.com/feed/']),
    ]));

    // "notlinkedin.example.com" contains "linkedin" but is not LinkedIn;
    // misattributing traffic defeats the point of the report.
    //
    // Assertion order matters here: Laravel's expectsOutputToContain() mocks
    // doWrite() per line and lets the first-registered matching expectation
    // claim a line, so the superstring assertion must come first — otherwise
    // "linkedin" claims the "notlinkedin.example.com" row and the second
    // assertion is starved even though the output is correct.
    $this->artisan('traffic:report')
        ->expectsOutputToContain('notlinkedin.example.com')
        ->expectsOutputToContain('linkedin')
        ->assertSuccessful();
});
