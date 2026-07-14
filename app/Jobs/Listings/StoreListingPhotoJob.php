<?php

declare(strict_types=1);

namespace App\Jobs\Listings;

use App\Exceptions\InvalidUploadException;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\Storage\StorageInterface;
use App\Services\Storage\StorageManager;
use finfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Photo-ingest pipeline for the listing wizard.
 *
 * Runs synchronously today (queue worker can flip to async later) and
 * is responsible for:
 *
 *   1. MIME validation (jpeg/png/webp only — the wizard never accepts
 *      anything else, but we re-check via finfo against the raw bytes
 *      so a forged Content-Type can't slip past).
 *   2. Dimension sanity (200x200 .. 8000x8000) — anything smaller is
 *      not useful as a listing image; anything larger is almost
 *      certainly a malicious payload.
 *   3. EXIF strip — privacy default. We do NOT want phones to leak
 *      GPS coordinates of the seller's home in listing photos.
 *   4. Three variants:
 *        - `original`: scaled to max 2000px long edge, source mime
 *        - `card`:     cover-cropped 600x600 webp (browse grid)
 *        - `thumb`:    cover-cropped 200x200 webp (thumbnails)
 *      Path convention: `listings/{listing_ulid}/{photo_id}/{variant}.{ext}`.
 *
 * The DB row records the `card` variant's path; {@see ListingPhoto::urlFor()}
 * composes sibling variant URLs at render time so the wizard does not
 * need to know about the storage layout.
 */
class StoreListingPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MIN_DIM = 200;

    private const MAX_DIM = 8000;

    private const ORIGINAL_MAX_LONG_EDGE = 2000;

    public function __construct(
        public int $listingId,
        public string $bytes,
        public string $declaredMime,
        public int $position,
    ) {}

    public function handle(): void
    {
        $listing = Listing::query()->findOrFail($this->listingId);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actual = (string) $finfo->buffer($this->bytes);

        if (! in_array($actual, self::ALLOWED_MIMES, true) || $actual !== $this->declaredMime) {
            throw new InvalidUploadException(
                "Unsupported or mismatched MIME type (declared {$this->declaredMime}, actual {$actual})"
            );
        }

        // Cheap header-only dimension check before the expensive decode: a
        // hostile upload that passes MIME sniffing but is a decompression
        // bomb (e.g. a tiny PNG that expands to gigapixels) should never
        // reach Image::read().
        $info = getimagesizefromstring($this->bytes);
        if ($info === false) {
            throw new InvalidUploadException('Not a readable image');
        }
        [$w, $h] = $info;
        if ($w < self::MIN_DIM || $h < self::MIN_DIM || $w > self::MAX_DIM || $h > self::MAX_DIM) {
            throw new InvalidUploadException("Image dimensions out of bounds ({$w}x{$h})");
        }

        $image = Image::read($this->bytes);

        // Privacy: drop EXIF / IPTC / XMP metadata before re-encoding.
        $stripped = clone $image;

        // Allocate the photo row first to mint an id we can use in the path.
        $photo = ListingPhoto::query()->create([
            'listing_id' => $listing->id,
            'disk' => (string) config('cloudmarktplaats.storage.driver', 'local'),
            'path' => 'pending',
            'width' => $w,
            'height' => $h,
            'mime' => $actual,
            'byte_size' => strlen($this->bytes),
            'position' => $this->position,
        ]);

        $baseDir = sprintf('listings/%s/%d', $listing->ulid, $photo->id);
        $storage = app(StorageManager::class)->driver($photo->disk);

        $originalExt = $this->extFor($actual);
        $originalPath = $baseDir.'/original.'.$originalExt;
        $cardPath = $baseDir.'/card.webp';
        $thumbPath = $baseDir.'/thumb.webp';

        // Variant writes + the final `path` update form one logical unit:
        // either all three variants land AND the DB row points at them, or
        // nothing persists. We track every blob we successfully write so a
        // mid-pipeline failure (disk full, transient S3 error, codec
        // explosion in a later variant) can roll back the partial state
        // before re-throwing.
        $written = [];
        try {
            $this->writeOriginal($storage, $stripped, $originalPath, $actual);
            $written[] = $originalPath;
            $this->writeCover($storage, $stripped, $cardPath, 600);
            $written[] = $cardPath;
            $this->writeCover($storage, $stripped, $thumbPath, 200);
            $written[] = $thumbPath;

            $photo->forceFill(['path' => $cardPath])->save();
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $storage->delete($path);
                } catch (Throwable) {
                    // Best-effort cleanup; swallow secondary failures so
                    // the original exception surfaces to the caller.
                }
            }
            $photo->delete();
            throw $e;
        }
    }

    private function writeOriginal(StorageInterface $storage, object $image, string $path, string $mime): void
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->scaleDown(self::ORIGINAL_MAX_LONG_EDGE, self::ORIGINAL_MAX_LONG_EDGE);
        $encoded = match ($mime) {
            'image/jpeg' => $copy->toJpeg(quality: 88),
            'image/png' => $copy->toPng(),
            'image/webp' => $copy->toWebp(quality: 88),
            default => $copy->toJpeg(quality: 88),
        };

        $storage->put($path, (string) $encoded);
    }

    private function writeCover(StorageInterface $storage, object $image, string $path, int $size): void
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->cover($size, $size);
        $encoded = $copy->toWebp(quality: 82);

        $storage->put($path, (string) $encoded);
    }

    private function extFor(string $mime): string
    {
        return ListingPhoto::extForMime($mime);
    }
}
