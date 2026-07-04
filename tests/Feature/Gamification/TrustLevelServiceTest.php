<?php

declare(strict_types=1);

use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\TrustLevelService;

it('is new when email is unverified', function () {
    $u = User::factory()->create(['email_verified_at' => null]);
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('new');
});

it('is member when verified but new', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('member');
});

it('is trusted at 14 days + 2 sales', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(20)]);
    Listing::factory()->sold()->for($u)->count(2)->create();
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('trusted');
});

it('is veteran at 30 days + 5 sales and may skip moderation when enabled', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($u)->count(5)->create();

    $svc = app(TrustLevelService::class);
    expect($svc->forUser($u)['key'])->toBe('veteran')
        ->and($svc->canSkipModeration($u))->toBeTrue();
});

it('never skips moderation on karma alone (anti-sockpuppet)', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', true);
    // Old, verified, high karma — but ZERO sales.
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(90)]);
    KarmaEvent::factory()->for($u)->count(50)->create(['points' => 10]);

    $svc = app(TrustLevelService::class);
    expect($svc->forUser($u)['key'])->not->toBe('veteran')
        ->and($svc->canSkipModeration($u))->toBeFalse();
});

it('never skips moderation when the flag is off', function () {
    config()->set('cloudmarktplaats.features.trust_autopublish', false);
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($u)->count(5)->create();

    expect(app(TrustLevelService::class)->canSkipModeration($u))->toBeFalse();
});

it('a banned user is always new', function () {
    $u = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(90), 'is_banned' => true]);
    Listing::factory()->sold()->for($u)->count(10)->create();
    expect(app(TrustLevelService::class)->forUser($u)['key'])->toBe('new');
});
