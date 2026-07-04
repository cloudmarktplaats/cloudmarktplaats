<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Database\QueryException;

it('records an upvote and enforces one per user per post', function () {
    $post = HomelabPost::factory()->create();
    $user = User::factory()->create();

    HomelabPostUpvote::factory()->create(['user_id' => $user->id, 'homelab_post_id' => $post->id]);

    expect($post->upvotes()->count())->toBe(1);

    // Second upvote by the same user on the same post violates the unique index.
    expect(fn () => HomelabPostUpvote::factory()->create(['user_id' => $user->id, 'homelab_post_id' => $post->id]))
        ->toThrow(QueryException::class);
});
