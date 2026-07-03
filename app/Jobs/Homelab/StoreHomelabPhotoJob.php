<?php

declare(strict_types=1);

namespace App\Jobs\Homelab;

use App\Exceptions\InvalidUploadException;
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
 * Foto-ingest voor homelab-posts. Kloon van StoreListingPhotoJob,
 * versimpeld tot één foto met twee varianten:
 *   - original: max 2000px lange zijde, bron-mime, EXIF gestript
 *   - card:     cover 600x600 webp (feed-grid)
 * Pad: homelabs/{post_ulid}/{variant}.{ext}. De DB-rij wijst naar card.
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
    ) {}

    public function handle(): void
    {
        $post = HomelabPost::query()->findOrFail($this->postId);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actual = (string) $finfo->buffer($this->bytes);

        if (! in_array($actual, self::ALLOWED_MIMES, true) || $actual !== $this->declaredMime) {
            throw new InvalidUploadException(
                "Unsupported or mismatched MIME type (declared {$this->declaredMime}, actual {$actual})"
            );
        }

        $image = Image::read($this->bytes);
        $w = $image->width();
        $h = $image->height();
        if ($w < self::MIN_DIM || $h < self::MIN_DIM || $w > self::MAX_DIM || $h > self::MAX_DIM) {
            throw new InvalidUploadException("Image dimensions out of bounds ({$w}x{$h})");
        }

        // Privacy: EXIF/IPTC/XMP weg vóór her-encoderen.
        $stripped = clone $image;

        $post->photo_disk = (string) config('cloudmarktplaats.storage.driver', 'local');
        $storage = app(StorageManager::class)->driver($post->photo_disk);

        $baseDir = 'homelabs/'.$post->ulid;
        $originalPath = $baseDir.'/original.'.$this->extFor($actual);
        $cardPath = $baseDir.'/card.webp';

        $written = [];
        try {
            $written[] = $this->writeOriginal($storage, $stripped, $originalPath, $actual);
            $written[] = $this->writeCard($storage, $stripped, $cardPath);

            $post->forceFill(['photo_path' => $cardPath])->save();
        } catch (Throwable $e) {
            foreach ($written as $path) {
                try {
                    $storage->delete($path);
                } catch (Throwable) {
                    // Best-effort cleanup.
                }
            }
            $post->delete();
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
