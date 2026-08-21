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

/**
 * The path-redaction map ($cmp_path) is a denylist, not an allowlist: only the
 * path segments listed here get redacted, everything else passes through
 * verbatim. /deal/{token} carries a live, 30-day claim token — reading, not
 * just knowing, the token lets anyone confirm or decline someone else's deal.
 * Shipping that route without an entry here would write it straight into a
 * 644 file kept for a week. Same shape as /reset-password/ and
 * /email/verify/, so it belongs in the same map.
 */
it('redacts the claim token out of /deal/ before it reaches the access log', function () {
    $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    expect($conf)->toContain('~^/deal/')
        ->and($conf)->toContain('/deal/[redacted]');
});

/**
 * En dit is de reden dat /deal/ er zes maanden lang níet in had gestaan: de
 * map is een denylist, dus een nieuwe route met een geheim in het pad lekt
 * standaard tot iemand eraan denkt. Niemand denkt eraan.
 *
 * Deze test koppelt de twee bestanden aan elkaar. Registreer je een route met
 * een parameter die een geheim draagt, dan faalt hij tot er een redactieregel
 * bij staat — dat is goedkoper dan onthouden. Een echte allowlist zou het
 * omgekeerde probleem geven: dan valt de analytics stil bij elke nieuwe
 * gewone pagina, en dat gebeurt veel vaker.
 */
it('redacts every route whose path carries a secret', function () {
    $conf = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    $leaking = collect(app('router')->getRoutes())
        ->filter(fn ($route) => preg_match('/\{(token|hash|secret|code)\??\}/', $route->uri()) === 1)
        // Het eerste vaste segment is waar de map op matcht.
        ->map(fn ($route) => explode('/', $route->uri())[0])
        ->unique()
        ->reject(fn (string $segment) => str_contains($conf, "~^/{$segment}/"))
        ->values()
        ->all();

    expect($leaking)->toBe([], 'Deze paden dragen een geheim maar worden niet geredigeerd in docker/nginx/default.conf: '.implode(', ', $leaking));
});
