<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

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

it('schedules a weekly truncate of the nginx access log', function () {
    $events = collect(app(Schedule::class)->events());

    $truncate = $events->first(fn ($e): bool => str_contains((string) $e->description, 'traffic:truncate-log')
        || str_contains((string) $e->command, 'traffic:truncate-log'));

    expect($truncate)->not->toBeNull()
        ->and($truncate->expression)->toBe('0 4 * * 0'); // zondag 04:00
});

it('truncates the log without deleting it', function () {
    $path = $this->logPath;
    file_put_contents($path, str_repeat("{\"t\":\"x\"}\n", 100));

    expect(filesize($path))->toBeGreaterThan(0);

    $this->artisan('traffic:truncate-log')->assertSuccessful();

    // The file must still exist: nginx holds the handle open, and deleting it
    // would leave nginx writing to an unlinked inode until a USR1 signal.
    expect(file_exists($path))->toBeTrue()
        ->and(filesize($path))->toBe(0);
});
