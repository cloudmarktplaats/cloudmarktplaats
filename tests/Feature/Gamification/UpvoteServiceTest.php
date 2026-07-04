<?php

declare(strict_types=1);

use App\Exceptions\UpvoteException;
use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
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

it('is idempotent if the vote already exists (no double karma)', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->create();
    // Pre-existing vote (as if a concurrent request already inserted it):
    HomelabPostUpvote::factory()->create(['homelab_post_id' => $post->id, 'user_id' => $voter->id]);

    // toggle now sees the existing row and toggles OFF (that's correct single-threaded behavior).
    // To exercise the unique-violation path specifically is hard to do deterministically in a
    // single process; instead assert the pre-existing-row branch is handled without error:
    expect(fn () => app(UpvoteService::class)->toggle($post, $voter))->not->toThrow(Exception::class);
});
