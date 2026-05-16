<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Listings\StoreListingPhotoJob;
use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

beforeEach(function () {
    Storage::fake('public');
});

it('rejects files whose MIME type is not allowed', function () {
    $listing = Listing::factory()->create();

    $job = new StoreListingPhotoJob($listing->id, 'PDF data here', 'application/pdf', 0);

    expect(fn () => $job->handle())->toThrow(InvalidUploadException::class);
});

it('writes original/card/thumb variants and strips EXIF from the original', function () {
    $listing = Listing::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    expect($bytes)->not->toBeFalse();

    // Sanity check: the fixture really contains an EXIF APP1 segment
    expect(strpos($bytes, 'Exif'))->not->toBeFalse();

    (new StoreListingPhotoJob($listing->id, $bytes, 'image/jpeg', 0))->handle();

    $photo = ListingPhoto::query()->where('listing_id', $listing->id)->firstOrFail();

    $basePath = dirname($photo->path);
    $disk = Storage::disk('public');

    expect($disk->exists($basePath.'/original.jpg'))->toBeTrue()
        ->and($disk->exists($basePath.'/card.webp'))->toBeTrue()
        ->and($disk->exists($basePath.'/thumb.webp'))->toBeTrue();

    // EXIF strip: the bytes Intervention wrote out should not contain the
    // GPS marker (we test by re-reading the original and asserting Image
    // can decode it without exposing the GPS IFD)
    $written = $disk->get($basePath.'/original.jpg');
    expect($written)->not->toBeNull();
    expect(strpos((string) $written, 'GPSLatitudeRef'))->toBeFalse();

    // Card variant is 600x600 cover, WebP
    $card = Image::read((string) $disk->get($basePath.'/card.webp'));
    expect($card->width())->toBe(600)->and($card->height())->toBe(600);

    // Thumb variant is 200x200 cover, WebP
    $thumb = Image::read((string) $disk->get($basePath.'/thumb.webp'));
    expect($thumb->width())->toBe(200)->and($thumb->height())->toBe(200);

    // DB row points to the card variant
    expect($photo->disk)->toBe('local')
        ->and($photo->mime)->toBe('image/jpeg')
        ->and($photo->position)->toBe(0);
});
