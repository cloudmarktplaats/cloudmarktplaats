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
