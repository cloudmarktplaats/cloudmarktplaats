<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\StatsService;

it('computes a user\'s own stats from existing data', function () {
    $user = User::factory()->create();
    Listing::factory()->published()->for($user)->count(2)->create();
    Listing::factory()->sold()->for($user)->create();
    HomelabPost::factory()->for($user)->create();
    KarmaEvent::factory()->for($user)->create(['type' => 'invite_activation', 'points' => 10]);
    KarmaEvent::factory()->for($user)->create(['type' => 'invite_activation', 'points' => 10]);

    // Another user's data must NOT leak in.
    Listing::factory()->sold()->create();

    $stats = app(StatsService::class)->forUser($user);

    expect($stats['listings_published'])->toBe(2)
        ->and($stats['listings_sold'])->toBe(1)
        ->and($stats['homelab_posts'])->toBe(1)
        ->and($stats['karma'])->toBe(20)
        ->and($stats['people_activated'])->toBe(2)
        ->and($stats['member_since']->timestamp)->toBe($user->created_at->timestamp);
});

it('counts platform-wide rescued (sold) listings', function () {
    Listing::factory()->sold()->count(3)->create();
    Listing::factory()->published()->create();

    expect(app(StatsService::class)->rescuedCount())->toBe(3);
});
