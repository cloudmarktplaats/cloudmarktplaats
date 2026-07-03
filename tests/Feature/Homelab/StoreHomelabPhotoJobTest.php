<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use App\Services\Storage\StorageManager;

it('stores original + card variants and strips EXIF', function () {
    $post = HomelabPost::factory()->create(['photo_path' => 'pending']);
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg'))->handle();

    $post->refresh();
    expect($post->photo_path)->toBe("homelabs/{$post->ulid}/card.webp");

    $storage = app(StorageManager::class)->driver($post->photo_disk);
    expect($storage->exists("homelabs/{$post->ulid}/original.jpg"))->toBeTrue()
        ->and($storage->exists("homelabs/{$post->ulid}/card.webp"))->toBeTrue();

    // EXIF weg: het origineel mag geen GPS-tags meer bevatten.
    $original = $storage->get("homelabs/{$post->ulid}/original.jpg");
    expect(str_contains($original, 'GPS'))->toBeFalse();
});

it('rejects a mismatched mime and leaves nothing behind', function () {
    $post = HomelabPost::factory()->create(['photo_path' => 'pending']);

    expect(fn () => (new StoreHomelabPhotoJob($post->id, 'not-an-image', 'image/jpeg'))->handle())
        ->toThrow(InvalidUploadException::class);
});
