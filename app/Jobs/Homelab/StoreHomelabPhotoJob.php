<?php

declare(strict_types=1);

namespace App\Jobs\Homelab;

use App\Exceptions\InvalidUploadException;
use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
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
 * Foto-ingest voor homelab-posts. Kloon van StoreListingPhotoJob, draait per
 * foto (één job per position) en schrijft drie varianten:
 *   - original: max 2000px lange zijde, bron-mime, EXIF gestript
 *   - card:     cover 600x600 webp (feed-grid)
 *   - thumb:    cover 300x300 webp
 * Pad: homelabs/{post_ulid}/{position}/{variant}.{ext}. De homelab_photos-rij
 * wijst naar card en bewaart de position; de post zelf wordt niet bijgewerkt
 * en bij een fout wordt alleen déze foto opgeruimd — de aanroeper beheert de
 * transactie over alle foto's van de post samen.
 */
class StoreHomelabPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MIN_DIM = 200;

    private const MAX_DIM = 8000;

    private const ORIGINAL_MAX_LONG_EDGE = 2000;

    public function __construct(
        public int $postId,
        public string $bytes,
        public string $declaredMime,
        public int $position,
    ) {}

    public function handle(): void
    {
        $post = HomelabPost::query()->findOrFail($this->postId);

        $disk = (string) config('cloudmarktplaats.storage.driver', 'local');
        $storage = app(StorageManager::class)->driver($disk);

        $written = [];
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $actual = (string) $finfo->buffer($this->bytes);

            if (! in_array($actual, self::ALLOWED_MIMES, true) || $actual !== $this->declaredMime) {
                throw new InvalidUploadException(
                    "Unsupported or mismatched MIME type (declared {$this->declaredMime}, actual {$actual})"
                );
            }

            $info = getimagesizefromstring($this->bytes);
            if ($info === false) {
                throw new InvalidUploadException('Not a readable image');
            }
            [$w, $h] = $info;
            if ($w < self::MIN_DIM || $h < self::MIN_DIM || $w > self::MAX_DIM || $h > self::MAX_DIM) {
                throw new InvalidUploadException("Image dimensions out of bounds ({$w}x{$h})");
            }

            $image = Image::read($this->bytes);
            $stripped = clone $image;

            // De positie in het pad houdt de foto's van één post uit elkaar.
            $baseDir = 'homelabs/'.$post->ulid.'/'.$this->position;
            $originalPath = $baseDir.'/original.'.$this->extFor($actual);
            $cardPath = $baseDir.'/card.webp';
            $thumbPath = $baseDir.'/thumb.webp';

            $written[] = $this->writeOriginal($storage, $stripped, $originalPath, $actual);
            $written[] = $this->writeCard($storage, $stripped, $cardPath);
            $written[] = $this->writeThumb($storage, $stripped, $thumbPath);

            HomelabPhoto::query()->create([
                'homelab_post_id' => $post->id,
                'disk' => $disk,
                'path' => $cardPath,
                'width' => $w,
                'height' => $h,
                'mime' => $actual,
                'byte_size' => strlen($this->bytes),
                'position' => $this->position,
            ]);
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $storage->delete($path);
                } catch (Throwable) {
                    // Best-effort opruimen.
                }
            }
            throw $e;
        }
    }

    private function writeOriginal(StorageInterface $storage, object $image, string $path, string $mime): string
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

        return $path;
    }

    private function writeCard(StorageInterface $storage, object $image, string $path): string
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->cover(600, 600);
        $storage->put($path, (string) $copy->toWebp(quality: 82));

        return $path;
    }

    private function writeThumb(StorageInterface $storage, object $image, string $path): string
    {
        /** @var ImageInterface $image */
        $copy = clone $image;
        $copy->cover(300, 300);
        $storage->put($path, (string) $copy->toWebp(quality: 78));

        return $path;
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
