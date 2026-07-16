<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 */
function insertHomelabPhoto(int $postId, array $overrides = []): void
{
    DB::table('homelab_photos')->insert(array_merge([
        'homelab_post_id' => $postId,
        'disk' => 'local',
        'path' => 'homelabs/x/card.webp',
        'width' => 600,
        'height' => 600,
        'mime' => 'image/webp',
        'byte_size' => 1234,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

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

it('enforces one photo per position per post', function () {
    $post = HomelabPost::factory()->create();

    insertHomelabPhoto($post->id, ['position' => 0]);

    // Tweede foto op dezelfde positie moet de unique-index raken. Kale insert
    // (geen model/factory) zodat de test niet op Taak 2 vooruitloopt.
    expect(fn () => insertHomelabPhoto($post->id, ['position' => 0]))
        ->toThrow(QueryException::class);
});

it('cascade-deletes photos when the post is deleted', function () {
    $post = HomelabPost::factory()->create();
    insertHomelabPhoto($post->id, ['position' => 0]);

    DB::table('homelab_posts')->where('id', $post->id)->delete();

    expect(DB::table('homelab_photos')->count())->toBe(0);
});

it('actually accepts a null title via insert, not just schema metadata', function () {
    // hasColumns zegt niets over NOT NULL; een echte insert wel.
    $id = DB::table('homelab_posts')->insertGetId([
        'ulid' => strtolower((string) Str::ulid()),
        'user_id' => User::factory()->create()->id,
        'title' => null,
        'body' => 'x',
        'photo_disk' => 'local',
        'photo_path' => 'homelabs/x/card.webp',
        'status' => 'published',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('homelab_posts')->where('id', $id)->value('title'))->toBeNull();
});
