<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Throwable;

/**
 * Removes the blobs behind a stored photo row.
 *
 * Listings and homelab posts both write their variants — `original.{ext}`,
 * `card.webp`, `thumb.webp` — into one directory per photo, and the row only
 * records the path to the `card` variant. So we erase by *directory*, not by
 * composing filenames: the original's extension comes from the `mime` column,
 * and on the oldest homelab rows that column does not match what was written
 * (a row saying `image/webp` next to an `original.jpg` on disk). Composing the
 * name left a member's photo behind after their account was erased — the row
 * was gone from every screen while the image was still being served.
 *
 * Erasing is best-effort: a directory that is already gone must not stop the
 * row it belongs to from being deleted.
 */
class PhotoFileEraser
{
    public function __construct(private StorageManager $storage) {}

    public function erase(?string $disk, string $cardPath): void
    {
        try {
            $this->storage->driver($disk)->deleteDirectory(dirname($cardPath));
        } catch (Throwable) {
            // Best-effort; the row is the part that has to go.
        }
    }
}
