<?php

declare(strict_types=1);

use App\Services\Gamification\BadgeService;

function statsWith(array $overrides = []): array
{
    return array_merge([
        'listings_published' => 0,
        'listings_sold' => 0,
        'homelab_posts' => 0,
        'karma' => 0,
        'people_activated' => 0,
    ], $overrides);
}

it('awards no badges for an empty account', function () {
    expect(app(BadgeService::class)->earnedFor(statsWith()))->toBe([]);
});

it('derives badges from stats', function () {
    $badges = app(BadgeService::class)->earnedFor(statsWith([
        'listings_published' => 1,
        'listings_sold' => 10,
        'homelab_posts' => 1,
        'karma' => 50,
        'people_activated' => 1,
    ]));

    $keys = array_column($badges, 'key');
    expect($keys)->toContain('first_listing', 'first_sale', 'trader', 'homelab_hero', 'host', 'pillar');
    // Every badge carries a label + description.
    expect($badges[0])->toHaveKeys(['key', 'label', 'description']);
});

it('does not award trader below ten sales', function () {
    $keys = array_column(app(BadgeService::class)->earnedFor(statsWith(['listings_sold' => 9])), 'key');
    expect($keys)->toContain('first_sale')->not->toContain('trader');
});
