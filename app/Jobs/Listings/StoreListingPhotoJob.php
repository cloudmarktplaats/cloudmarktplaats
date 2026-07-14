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

    /**
     * Ceiling for this job only. Must cover decoding a MAX_DIM image
     * (8000x8000 = 244MB in GD) plus the shrunk copy; measured peak 318MB.
     */
    private const DECODE_MEMORY_LIMIT = '512M';

    public function __construct(
        public int $listingId,
        public string $bytes,
        public string $declaredMime,
        public int $position,
    ) {}

    public function handle(): void
    {
        // Decoding costs w*h*4 bytes of RAM regardless of how small the file is
        // on disk — a 255KB gradient can still be 48MB decoded. PHP's default
        // 128M ceiling is far below what real photos need, which is why every
        // phone-sized upload failed in production while small screenshots got
        // through. Raise it for this job only: a limit set here can't be spent
        // by any other request, so one hostile upload still can't eat the box.
        //
        // Measured peaks after the shrink-first fix below (5 fpm workers x 512M
        // = 2.5GB worst case, on a 4GB host):
        //   12MP (4000x3000, the common case) -> 106MB
        //   48MP (8000x6000, high-end phone)  -> 248MB
        //   64MP (8000x8000, MAX_DIM ceiling) -> 318MB
        ini_set('memory_limit', self::DECODE_MEMORY_LIMIT);

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

        // Shrink to the largest size we actually keep, BEFORE deriving variants.
        //
        // GD holds an image uncompressed at w*h*4 bytes, and every `clone` is a
        // full copy of that buffer. A 12MP phone photo is 48MB, so reading it
        // and cloning it once already exceeds a 128M limit — which is exactly
        // what happened in production: every real photo upload died here and
        // only small screenshots survived. Nothing is lost by shrinking first:
        // the stored original is capped at ORIGINAL_MAX_LONG_EDGE anyway, and
        // card/thumb are cover-crops that a 2000px source serves identically.
        //
        // Privacy note: EXIF/GPS is dropped by the GD re-encode below, not by
        // any copy — GD cannot write EXIF at all. (The `photo-with-gps.jpg`
        // fixture test pins this.)
        $image->scaleDown(self::ORIGINAL_MAX_LONG_EDGE, self::ORIGINAL_MAX_LONG_EDGE);

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
            $this->writeOriginal($storage, $image, $originalPath, $actual);
            $written[] = $originalPath;
            $this->writeCover($storage, $image, $cardPath, 600);
            $written[] = $cardPath;
            $this->writeCover($storage, $image, $thumbPath, 200);
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

    /**
     * The caller has already scaled the image to ORIGINAL_MAX_LONG_EDGE, and
     * encoding does not mutate it — so no copy is needed here. The re-encode is
     * also what drops EXIF/GPS: GD cannot write it back out.
     */
    private function writeOriginal(StorageInterface $storage, object $image, string $path, string $mime): void
    {
        /** @var ImageInterface $image */
        $encoded = match ($mime) {
            'image/jpeg' => $image->toJpeg(quality: 88),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: 88),
            default => $image->toJpeg(quality: 88),
        };

        $storage->put($path, (string) $encoded);
    }

    /**
     * Unlike encoding, `cover()` mutates — and the caller reuses the image for
     * the next variant, so this one does need its own copy. It is a copy of the
     * already-shrunk image (~12MB for a 2000px source), not of the raw upload.
     */
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
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
