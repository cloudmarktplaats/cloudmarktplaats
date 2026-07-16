<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use Illuminate\Support\Facades\Schema;

it('adds the page columns to homelab_posts', function () {
    expect(Schema::hasColumns('homelab_posts', ['title', 'feedback_prompt', 'comments_open']))->toBeTrue();
});

it('creates a homelab_photos table mirroring listing_photos', function () {
    expect(Schema::hasColumns('homelab_photos', [
        'homelab_post_id', 'disk', 'path', 'width', 'height', 'mime', 'byte_size', 'position',
    ]))->toBeTrue();
});

it('leaves title nullable so titleless posts keep working', function () {
    $post = HomelabPost::factory()->create(['title' => null]);
    expect($post->fresh()->title)->toBeNull();
});

it('exposes a homelab photo count separate from listings', function () {
    expect(config('cloudmarktplaats.photos.homelab_max_count'))->toBe(4)
        ->and(config('cloudmarktplaats.photos.max_count'))->toBe(10);
});
