<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\User;

it('mints a ulid and defaults to published', function () {
    $post = HomelabPost::factory()->create();

    expect($post->ulid)->toBeString()->toHaveLength(26)
        ->and($post->status)->toBe('published');
});

it('scopes to published only', function () {
    HomelabPost::factory()->create();
    HomelabPost::factory()->removed()->create();

    expect(HomelabPost::query()->published()->count())->toBe(1);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $post = HomelabPost::factory()->for($user)->create();

    expect($post->user->is($user))->toBeTrue();
});
