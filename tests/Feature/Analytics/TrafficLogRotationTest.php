<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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
    // Asserted through `schedule:list`, not app(Schedule::class)->events(): the
    // schedule is registered by an Artisan::starting() bootstrapper that only
    // fires once a Console\Application is constructed, so reading the Schedule
    // directly only works when this happens to be the first test in the run —
    // it passed under --filter and failed in the full suite. A test that is
    // green only in a lucky order is worse than no test.
    //
    // Output is captured via Artisan::output() rather than chained
    // expectsOutputToContain() calls: both strings live on the SAME line
    // ("0 4 * * 0  php artisan traffic:truncate-log ..."), and the first
    // expectation consumes that line, starving the second.
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)
        ->toContain('traffic:truncate-log')
        ->toContain('0 4 * * 0'); // zondag 04:00
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
