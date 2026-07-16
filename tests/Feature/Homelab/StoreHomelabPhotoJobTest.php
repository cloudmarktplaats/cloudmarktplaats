<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('writes a homelab_photos row with position and source mime', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 0))->handle();

    $photo = $post->photos()->first();
    expect($photo)->not->toBeNull()
        ->and($photo->position)->toBe(0)
        ->and($photo->mime)->toBe('image/jpeg')
        ->and($photo->path)->toContain('homelabs/'.$post->ulid.'/0/card.webp')
        // LocalStorage schrijft altijd naar Laravel-disk 'public' (zie
        // App\Services\Storage\LocalStorage); 'local' in de brief was de
        // configwaarde voor de driver-keuze, niet de Laravel-disknaam — zie
        // ook Storage::disk('public') in StoreListingPhotoJobTest.
        ->and(Storage::disk('public')->exists($photo->path))->toBeTrue()
        // De original houdt zijn bron-extensie, zodat og:image te bouwen is.
        ->and(Storage::disk('public')->exists('homelabs/'.$post->ulid.'/0/original.jpg'))->toBeTrue()
        // De thumb is het gat dat deze taak dicht: de oude job schreef hem nooit
        // terwijl HomelabPhoto::urlFor('thumb') hem al adverteerde.
        ->and(Storage::disk('public')->exists('homelabs/'.$post->ulid.'/0/thumb.webp'))->toBeTrue();
});

it('places a second photo at its own position', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 0))->handle();
    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 1))->handle();

    expect($post->photos()->count())->toBe(2)
        ->and($post->photos()->pluck('position')->all())->toBe([0, 1]);
});

it('cleans up its own files and leaves the post intact when the upload is invalid', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    // declared mime wijkt af van de echte -> InvalidUploadException, ná dat er
    // al bestanden geschreven kunnen zijn.
    expect(fn () => (new StoreHomelabPhotoJob($post->id, $bytes, 'image/png', position: 0))->handle())
        ->toThrow(InvalidUploadException::class);

    // De job ruimt zijn eigen bestanden op...
    expect(Storage::disk('public')->exists('homelabs/'.$post->ulid.'/0/card.webp'))->toBeFalse()
        ->and($post->photos()->count())->toBe(0)
        // ...en laat de post staan. De caller-transactie doet de rollback, niet
        // de job (die deed vroeger $post->delete() — precies wat we weghaalden).
        ->and(HomelabPost::query()->whereKey($post->id)->exists())->toBeTrue();
});
