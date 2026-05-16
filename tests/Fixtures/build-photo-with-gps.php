<?php

declare(strict_types=1);

/**
 * Hand-crafts a JPEG that contains an EXIF APP1 segment with GPS data
 * (latitude + longitude). Used as the canonical "EXIF-bearing photo"
 * fixture by tests/Feature/Listings/StoreListingPhotoJobTest.php.
 *
 * We build the bytes by hand rather than depending on ext-exif (which
 * is not installed in CI) or exiftool (extra system dependency). The
 * resulting file is small (~2.5KB) and deterministic — re-running
 * this script always produces the same fixture.
 *
 * Run from project root: `php tests/Fixtures/build-photo-with-gps.php`.
 */
$im = imagecreatetruecolor(300, 200);
$bg = imagecolorallocate($im, 200, 220, 255);
imagefill($im, 0, 0, $bg);
$tx = imagecolorallocate($im, 0, 0, 0);
imagestring($im, 5, 20, 80, 'cmplts fixture', $tx);

ob_start();
imagejpeg($im, null, 88);
$jpeg = ob_get_clean();
imagedestroy($im);

// Strip GD's default APP0/JFIF (it sits right after SOI/FFD8) and replace
// with our APP1/Exif segment. SOI is 2 bytes (FF D8). The next marker
// is FF E0 (APP0/JFIF). Find its length and skip it.
$soi = substr($jpeg, 0, 2);
$cursor = 2;
if (substr($jpeg, $cursor, 2) === "\xFF\xE0") {
    $app0Len = unpack('n', substr($jpeg, $cursor + 2, 2))[1];
    $cursor += 2 + $app0Len; // jump past APP0
}
$rest = substr($jpeg, $cursor);

// Build a minimal EXIF IFD with one entry: a pointer to the GPS IFD.
// The GPS IFD contains GPSLatitudeRef (N), GPSLatitude (52.0 deg),
// GPSLongitudeRef (E), GPSLongitude (4.9 deg). Numbers are stored as
// rationals (deg/1, min/1, sec/100).
$tiffHeader = "II\x2A\x00\x08\x00\x00\x00"; // little-endian, IFD0 at offset 8

// IFD0: 1 entry (GPS IFD pointer @ 0x8825, type LONG, count 1)
$ifd0Entries = 1;
$ifd0 = pack('v', $ifd0Entries);
// Offset to GPS IFD: TIFF header (8) + IFD0 size = 8 + 2 + (1*12) + 4 = 26
$gpsIfdOffset = 26;
$ifd0 .= pack('vvVV', 0x8825, 4, 1, $gpsIfdOffset);
$ifd0 .= pack('V', 0); // next IFD = 0 (no IFD1)

// GPS IFD: 4 entries
$gpsEntries = 4;
$gpsIfd = pack('v', $gpsEntries);
// We need data area for the two rational arrays (3 rationals = 24 bytes each)
$dataAreaOffset = $gpsIfdOffset + 2 + (4 * 12) + 4; // 26 + 2 + 48 + 4 = 80
$latOffset = $dataAreaOffset;
$lonOffset = $latOffset + 24;

// GPSLatitudeRef (0x0001), ASCII, count 2 ("N\0")
$gpsIfd .= pack('vvV', 0x0001, 2, 2)."N\x00\x00\x00";
// GPSLatitude (0x0002), RATIONAL, count 3 → offset to data
$gpsIfd .= pack('vvVV', 0x0002, 5, 3, $latOffset);
// GPSLongitudeRef (0x0003), ASCII, count 2 ("E\0")
$gpsIfd .= pack('vvV', 0x0003, 2, 2)."E\x00\x00\x00";
// GPSLongitude (0x0004), RATIONAL, count 3 → offset to data
$gpsIfd .= pack('vvVV', 0x0004, 5, 3, $lonOffset);
$gpsIfd .= pack('V', 0); // next IFD

// Latitude: 52° 0' 0.00" = 52/1, 0/1, 0/100
$latData = pack('VVVVVV', 52, 1, 0, 1, 0, 100);
// Longitude: 4° 54' 0.00" = 4/1, 54/1, 0/100
$lonData = pack('VVVVVV', 4, 1, 54, 1, 0, 100);

$tiff = $tiffHeader.$ifd0.$gpsIfd.$latData.$lonData;
$app1Payload = "Exif\x00\x00".$tiff;
$app1 = "\xFF\xE1".pack('n', strlen($app1Payload) + 2).$app1Payload;

$out = $soi.$app1.$rest;

$dest = __DIR__.'/photo-with-gps.jpg';
file_put_contents($dest, $out);

echo "Wrote {$dest} (".filesize($dest)." bytes)\n";
