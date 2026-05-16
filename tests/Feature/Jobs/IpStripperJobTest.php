<?php

declare(strict_types=1);

use App\Jobs\IpStripperJob;
use App\Models\User;

it('clears last_login_ip for users whose last login was more than 24 hours ago', function () {
    $stale = User::factory()->create([
        'last_login_at' => now()->subHours(25),
        'last_login_ip' => '203.0.113.7',
    ]);
    $fresh = User::factory()->create([
        'last_login_at' => now()->subHours(1),
        'last_login_ip' => '203.0.113.42',
    ]);

    (new IpStripperJob)->handle();

    expect($stale->refresh()->last_login_ip)->toBeNull()
        // The fresh user is within the 24h window — must keep the IP.
        ->and($fresh->refresh()->last_login_ip)->toBe('203.0.113.42')
        // last_login_at is intentionally left untouched (used for the
        // public "last seen" cue) — the job only redacts the IP.
        ->and($stale->refresh()->last_login_at)->not->toBeNull();
});

it('is a no-op when there are no eligible rows', function () {
    User::factory()->create([
        'last_login_at' => now()->subMinutes(5),
        'last_login_ip' => '198.51.100.1',
    ]);

    (new IpStripperJob)->handle();

    expect(User::query()->whereNotNull('last_login_ip')->count())->toBe(1);
});
