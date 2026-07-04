<?php

declare(strict_types=1);

use App\Exceptions\UpvoteException;
use App\Models\HomelabPost;
use App\Models\User;
use App\Services\Gamification\KarmaService;
use App\Services\Gamification\UpvoteService;

it('toggles an upvote and moves the owner\'s karma', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();
    $svc = app(UpvoteService::class);

    expect($svc->toggle($post, $voter))->toBeTrue()
        ->and($svc->countFor($post))->toBe(1)
        ->and($svc->hasUpvoted($post, $voter))->toBeTrue()
        ->and(app(KarmaService::class)->karmaFor($owner))->toBe(1);

    // toggle off
    expect($svc->toggle($post, $voter))->toBeFalse()
        ->and($svc->countFor($post))->toBe(0)
        ->and(app(KarmaService::class)->karmaFor($owner))->toBe(0);
});

it('blocks a self-upvote (no karma farming)', function () {
    $owner = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();

    expect(fn () => app(UpvoteService::class)->toggle($post, $owner))->toThrow(UpvoteException::class);
    expect(app(KarmaService::class)->karmaFor($owner))->toBe(0);
});

it('blocks a banned voter', function () {
    $post = HomelabPost::factory()->create();
    $banned = User::factory()->create(['is_banned' => true]);

    expect(fn () => app(UpvoteService::class)->toggle($post, $banned))->toThrow(UpvoteException::class);
});

it('counts one upvote per user even across voters', function () {
    $post = HomelabPost::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $svc = app(UpvoteService::class);

    $svc->toggle($post, $a);
    $svc->toggle($post, $b);

    expect($svc->countFor($post))->toBe(2);
});
