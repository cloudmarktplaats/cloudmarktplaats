<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Homelab\StoreHomelabPhotoJob;
use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

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

it('auto-orients the original from de EXIF Orientation tag vóór het EXIF-strippen', function () {
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-orientation.jpg'));

    // Fixture is 300x200 met Orientation=6 (90° CW draaien).
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 300, 1 => 200]);

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', position: 0))->handle();

    $photo = $post->photos()->firstOrFail();
    $disk = Storage::disk('public');
    $base = dirname($photo->path);

    // Breedte/hoogte omgewisseld t.o.v. de bron: bewijs dat de pixels al
    // gedraaid waren vóórdat de GD-her-encode de Orientation-tag wegstripte.
    $original = Image::read((string) $disk->get($base.'/original.jpg'));
    expect($original->width())->toBe(200)->and($original->height())->toBe(300);
});

it('processes a 12MP phone photo without exhausting the memory limit', function () {
    // Dezelfde bug die StoreListingPhotoJob had, en die deze job aanvankelijk
    // niet meekreeg: op productie sneuvelde elke echte camerafoto op een homelab
    // en alleen kleine plaatjes overleefden. Een 4000x3000-foto is 260KB op
    // schijf maar decodeert naar ~48MB; lezen + clonen blies de 128M-limiet op
    // vóór er één variant geschreven was.
    $post = HomelabPost::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-12mp.jpg'));

    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 4000, 1 => 3000]);

    (new StoreHomelabPhotoJob($post->id, $bytes, 'image/jpeg', 0))->handle();

    $photo = $post->photos()->firstOrFail();
    $disk = Storage::disk('public');
    $base = dirname($photo->path);

    expect($disk->exists($base.'/original.jpg'))->toBeTrue()
        ->and($disk->exists($base.'/card.webp'))->toBeTrue()
        ->and($disk->exists($base.'/thumb.webp'))->toBeTrue();

    // De bewaarde original is op de lange zijde gekapt: 4000x3000 -> 2000x1500.
    $original = Image::read((string) $disk->get($base.'/original.jpg'));
    expect($original->width())->toBe(2000)->and($original->height())->toBe(1500);

    // De rij bewaart de bron-afmetingen, niet de geschaalde.
    expect($photo->width)->toBe(4000)->and($photo->height)->toBe(3000);
});
