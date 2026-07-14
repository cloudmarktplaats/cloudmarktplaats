<?php

declare(strict_types=1);

/**
 * Builds a 4000x3000 (12MP) JPEG — the size any phone camera produces.
 *
 * Used by tests/Feature/Listings/StoreListingPhotoJobTest.php to pin the
 * memory regression: GD holds an image uncompressed at w*h*4 bytes, so this
 * file decodes to ~48MB no matter how small the file on disk is. The job used
 * to read it and immediately clone it, exceeding the 128M limit before writing
 * a single variant — in production every real photo upload died there and only
 * small screenshots survived.
 *
 * The image is a smooth gradient, not noise: it decodes to the same 48MB but
 * compresses to a few hundred KB, so the fixture stays cheap to store in git.
 * Building it at test time is NOT an option — imagecreatetruecolor() would
 * itself allocate 48MB and leave PHP's heap too fragmented for the job to run
 * inside the same 128M budget.
 *
 * Re-generate with:
 *   docker compose exec -T -u www-data php-fpm php tests/Fixtures/build-photo-12mp.php
 */
$w = 4000;
$h = 3000;

$gd = imagecreatetruecolor($w, $h);
if ($gd === false) {
    fwrite(STDERR, "imagecreatetruecolor failed\n");
    exit(1);
}

// Vertical gradient: enough real pixel data to be a legitimate photo-sized
// decode, smooth enough that JPEG squeezes it down to a few hundred KB.
for ($y = 0; $y < $h; $y++) {
    $shade = (int) round(255 * ($y / $h));
    $colour = imagecolorallocate($gd, $shade, (int) ($shade * 0.6), 255 - $shade);
    if ($colour === false) {
        continue;
    }
    imagefilledrectangle($gd, 0, $y, $w - 1, $y, $colour);
}

$out = __DIR__.'/photo-12mp.jpg';
imagejpeg($gd, $out, 85);
imagedestroy($gd);

printf(
    "wrote %s: %dx%d (%.1fMP), %d KB on disk, ~%d MB decoded in GD\n",
    $out,
    $w,
    $h,
    $w * $h / 1e6,
    (int) round((int) filesize($out) / 1024),
    (int) round($w * $h * 4 / 1048576),
);
