<?php

declare(strict_types=1);

/**
 * Hand-crafts a landscape (300x200) JPEG that contains an EXIF APP1
 * segment with a single `Orientation` tag set to 6 (rotate 90° CW —
 * the common "phone held in portrait" case). Used by
 * tests/Feature/Listings/StoreListingPhotoJobTest.php to verify that
 * StoreListingPhotoJob auto-orients before it strips EXIF.
 *
 * Bytes are built by hand rather than via ext-exif (not installed in
 * every environment this fixture is generated in) or exiftool. The
 * resulting file is small and deterministic — re-running this script
 * always produces the same fixture.
 *
 * Run from project root: `php tests/Fixtures/build-photo-with-orientation.php`.
 */
$im = imagecreatetruecolor(300, 200);
$bg = imagecolorallocate($im, 255, 220, 200);
imagefill($im, 0, 0, $bg);
$tx = imagecolorallocate($im, 0, 0, 0);
imagestring($im, 5, 20, 80, 'orientation fixture', $tx);

ob_start();
imagejpeg($im, null, 88);
$jpeg = ob_get_clean();
imagedestroy($im);

// Strip GD's default APP0/JFIF (sits right after SOI/FFD8) and replace with
// our APP1/Exif segment. SOI is 2 bytes (FF D8); next marker is FF E0/APP0.
$soi = substr($jpeg, 0, 2);
$cursor = 2;
if (substr($jpeg, $cursor, 2) === "\xFF\xE0") {
    $app0Len = unpack('n', substr($jpeg, $cursor + 2, 2))[1];
    $cursor += 2 + $app0Len;
}
$rest = substr($jpeg, $cursor);

// Minimal EXIF IFD0 with one entry: Orientation (0x0112), type SHORT (3),
// count 1, value 6. A SHORT value fits in the 4-byte value/offset field
// left-justified, so pack('V', 6) (06 00 00 00 little-endian) is correct.
$tiffHeader = "II\x2A\x00\x08\x00\x00\x00"; // little-endian, IFD0 at offset 8
$ifd0Entries = 1;
$ifd0 = pack('v', $ifd0Entries);
$ifd0 .= pack('vvVV', 0x0112, 3, 1, 6);
$ifd0 .= pack('V', 0); // next IFD = 0

$tiff = $tiffHeader.$ifd0;
$app1Payload = "Exif\x00\x00".$tiff;
$app1 = "\xFF\xE1".pack('n', strlen($app1Payload) + 2).$app1Payload;

$out = $soi.$app1.$rest;

$dest = __DIR__.'/photo-with-orientation.jpg';
file_put_contents($dest, $out);

echo "Wrote {$dest} (".filesize($dest)." bytes)\n";
