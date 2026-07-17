<?php

declare(strict_types=1);

use App\Exceptions\InvalidUploadException;
use App\Jobs\Listings\StoreListingPhotoJob;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
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

it('auto-orients the original from the EXIF Orientation tag before stripping EXIF', function () {
    $listing = Listing::factory()->create();
    $bytes = file_get_contents(base_path('tests/Fixtures/photo-with-orientation.jpg'));

    expect($bytes)->not->toBeFalse();

    // Sanity check: the fixture is 300x200 with Orientation=6 (rotate 90° CW).
    $sourceInfo = getimagesizefromstring($bytes);
    expect($sourceInfo)->toMatchArray([0 => 300, 1 => 200]);
    expect(strpos($bytes, 'Exif'))->not->toBeFalse();

    (new StoreListingPhotoJob($listing->id, $bytes, 'image/jpeg', 0))->handle();

    $photo = ListingPhoto::query()->where('listing_id', $listing->id)->firstOrFail();
    $basePath = dirname($photo->path);
    $disk = Storage::disk('public');

    // Width/height are swapped versus the source: proof the pixels were
    // rotated before the GD re-encode dropped the Orientation tag for good.
    $original = Image::read((string) $disk->get($basePath.'/original.jpg'));
    expect($original->width())->toBe(200)->and($original->height())->toBe(300);

    // The Orientation tag itself is gone from the written bytes (same EXIF
    // strip as the GPS case), it just no longer needs to be there.
    $written = (string) $disk->get($basePath.'/original.jpg');
    expect(strpos($written, 'Exif'))->toBeFalse();
});

it('processes a 12MP phone photo without exhausting the memory limit', function () {
    $listing = Listing::factory()->create();

    // 4000x3000 — what any phone produces. Only 255KB on disk, but GD holds an
    // image uncompressed at w*h*4 bytes, so it decodes to ~48MB. The job used
    // to read it (48MB) and immediately clone it (another 48MB), blowing the
    // 128M limit before writing a single variant: in production every real
    // photo upload died here and only small screenshots survived.
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-12mp.jpg'));

    expect(getimagesizefromstring($bytes))
        ->toMatchArray([0 => 4000, 1 => 3000]);

    (new StoreListingPhotoJob($listing->id, $bytes, 'image/jpeg', 0))->handle();

    $photo = ListingPhoto::query()->where('listing_id', $listing->id)->firstOrFail();
    $disk = Storage::disk('public');
    $basePath = dirname($photo->path);

    expect($disk->exists($basePath.'/original.jpg'))->toBeTrue()
        ->and($disk->exists($basePath.'/card.webp'))->toBeTrue()
        ->and($disk->exists($basePath.'/thumb.webp'))->toBeTrue();

    // The stored original is capped at the long edge, so 4000x3000 -> 2000x1500.
    $original = Image::read((string) $disk->get($basePath.'/original.jpg'));
    expect($original->width())->toBe(2000)->and($original->height())->toBe(1500);

    // The row records the source dimensions, not the stored ones.
    expect($photo->width)->toBe(4000)->and($photo->height)->toBe(3000);
});

it('deletes any partial blobs and the photo row if a variant write fails', function () {
    $listing = Listing::factory()->create();
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));

    // Storage that fails on the second `put` call (i.e. after the original
    // has been written but before the card variant lands). This simulates
    // a transient disk failure mid-pipeline.
    $flaky = new class implements StorageInterface
    {
        /** @var list<string> */
        public array $written = [];

        public int $putCalls = 0;

        public bool $exploded = false;

        public function put(string $path, string $contents, array $options = []): string
        {
            $this->putCalls++;
            if ($this->putCalls === 2) {
                $this->exploded = true;
                throw new RuntimeException('simulated disk failure');
            }
            $this->written[] = $path;

            return $path;
        }

        public function get(string $path): string
        {
            return '';
        }

        public function url(string $path): string
        {
            return $path;
        }

        public function delete(string $path): bool
        {
            $this->written = array_values(array_filter($this->written, fn ($p) => $p !== $path));

            return true;
        }

        public function exists(string $path): bool
        {
            return in_array($path, $this->written, true);
        }
    };

    $manager = $this->mock(StorageManager::class);
    $manager->shouldReceive('driver')->andReturn($flaky);

    expect(fn () => (new StoreListingPhotoJob($listing->id, $bytes, 'image/jpeg', 0))->handle())
        ->toThrow(RuntimeException::class);

    expect($flaky->exploded)->toBeTrue();
    // The original blob was written but should have been cleaned up.
    expect($flaky->written)->toBeEmpty();
    // The photo row must not survive a failed pipeline.
    expect(ListingPhoto::query()->where('listing_id', $listing->id)->count())->toBe(0);
});
