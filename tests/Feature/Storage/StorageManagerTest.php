<?php

declare(strict_types=1);

use App\Models\ListingPhoto;
use App\Services\Storage\LocalStorage;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
use Illuminate\Support\Facades\Storage;

it('binds StorageInterface to the configured driver', function () {
    config()->set('cloudmarktplaats.storage.driver', 'local');

    expect(app(StorageInterface::class))->toBeInstanceOf(LocalStorage::class);
});

it('LocalStorage put/get/exists/delete round-trips through the public disk', function () {
    Storage::fake('public');

    /** @var LocalStorage $local */
    $local = app(StorageManager::class)->driver('local');

    $local->put('test/foo.txt', 'hello');

    expect($local->exists('test/foo.txt'))->toBeTrue()
        ->and($local->get('test/foo.txt'))->toBe('hello')
        ->and($local->url('test/foo.txt'))->toContain('test/foo.txt');

    $local->delete('test/foo.txt');

    expect($local->exists('test/foo.txt'))->toBeFalse();
});

it('StorageManager throws on unknown driver', function () {
    expect(fn () => app(StorageManager::class)->driver('s3-fake'))
        ->toThrow(InvalidArgumentException::class);
});

it('ListingPhoto urlFor uses the StorageManager for the photo disk', function () {
    Storage::fake('public');

    $photo = new ListingPhoto;
    $photo->disk = 'local';
    $photo->path = 'listings/01HXY/42/card.webp';
    $photo->mime = 'image/jpeg';

    // The URL is built from the public disk; assert it contains the variant path
    expect($photo->urlFor('thumb'))->toContain('listings/01HXY/42/thumb.webp')
        ->and($photo->urlFor('original'))->toContain('listings/01HXY/42/original.jpg');
});

it('urlFor original uses the source extension from the stored mime, not the card path', function () {
    Storage::fake('public');

    // path always points at the card variant (webp); the original keeps its
    // source mime, so the extension must come from `mime`.
    $jpeg = new ListingPhoto;
    $jpeg->disk = 'local';
    $jpeg->path = 'listings/01HXY/42/card.webp';
    $jpeg->mime = 'image/jpeg';

    $png = new ListingPhoto;
    $png->disk = 'local';
    $png->path = 'listings/01HXY/43/card.webp';
    $png->mime = 'image/png';

    expect($jpeg->urlFor('original'))->toContain('listings/01HXY/42/original.jpg')
        ->and($png->urlFor('original'))->toContain('listings/01HXY/43/original.png');
});

it('urlFor keeps webp for the derived card and thumb variants', function () {
    Storage::fake('public');

    $photo = new ListingPhoto;
    $photo->disk = 'local';
    $photo->path = 'listings/01HXY/42/card.webp';
    $photo->mime = 'image/jpeg';

    // Derived variants are always webp regardless of the source mime.
    expect($photo->urlFor('card'))->toContain('listings/01HXY/42/card.webp')
        ->and($photo->urlFor('thumb'))->toContain('listings/01HXY/42/thumb.webp');
});
