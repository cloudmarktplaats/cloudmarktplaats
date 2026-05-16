<?php

declare(strict_types=1);

use App\Models\ListingPhoto;
use App\Services\Storage\LocalStorage;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

    // The URL is built from the public disk; assert it contains the variant path
    expect($photo->urlFor('thumb'))->toContain('listings/01HXY/42/thumb.webp')
        ->and($photo->urlFor('original'))->toContain('listings/01HXY/42/original.');
});
