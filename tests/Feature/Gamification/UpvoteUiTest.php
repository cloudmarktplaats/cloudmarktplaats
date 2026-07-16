<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use App\Services\Gamification\UpvoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('lets a logged-in user upvote a post from the feed', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $post = HomelabPost::factory()->for($owner)->withPhoto()->create();

    Livewire::actingAs($voter)
        ->test(Feed::class)
        ->call('upvote', $post->ulid)
        ->assertHasNoErrors();

    expect(app(UpvoteService::class)->countFor($post))->toBe(1);
});

it('forbids a guest from upvoting', function () {
    $post = HomelabPost::factory()->withPhoto()->create();

    Livewire::test(Feed::class)
        ->call('upvote', $post->ulid)
        ->assertForbidden();
});

it('404s upvote on a removed post and leaves the owner karma untouched', function () {
    $owner = User::factory()->create();
    $voter = User::factory()->create();
    $removed = HomelabPost::factory()->removed()->for($owner)->create();

    // Same rationale as the deleteOwn removed-post test: the `published()`
    // scope means a removed post simply isn't found — Livewire's test
    // harness only converts HttpException/AuthorizationException into HTTP
    // responses, so the underlying ModelNotFoundException from
    // firstOrFail() surfaces directly here (it maps to a real 404 response
    // in production via Laravel's exception handler).
    expect(fn () => Livewire::actingAs($voter)
        ->test(Feed::class)
        ->call('upvote', $removed->ulid))
        ->toThrow(ModelNotFoundException::class);

    expect($owner->karma)->toBe(0);
});

it('shows the upvote count on the feed', function () {
    $post = HomelabPost::factory()->withPhoto()->create(['body' => 'stealth lab']);
    HomelabPost::factory()->withPhoto()->create(); // noise
    app(UpvoteService::class); // ensure container ok
    HomelabPostUpvote::factory()->count(3)->create(['homelab_post_id' => $post->id]);

    $this->get('/homelabs')->assertOk()->assertSee('3');
});
