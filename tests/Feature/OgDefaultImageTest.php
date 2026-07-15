<?php

declare(strict_types=1);

/**
 * The og:image fallback must be a file that actually exists.
 *
 * `marketing.blade.php` has pointed at `og-default.png` since launch, but the
 * file was never committed — so every share of a page without its own image
 * rendered a blank card, and nothing failed: no error, no log, just a 404 that
 * only a social crawler ever saw. Found by grepping the nginx access log
 * (29 × 404) weeks later.
 *
 * This asserts the reference resolves, so a rename or a missed asset breaks a
 * test instead of quietly degrading every share.
 */
it('points og:image at a file that exists', function () {
    $html = (string) $this->get('/')->getContent();

    preg_match('/<meta property="og:image" content="([^"]+)">/', $html, $m);

    expect($m[1] ?? null)->not->toBeNull('de homepage rendert geen og:image');

    $path = public_path(ltrim((string) parse_url($m[1], PHP_URL_PATH), '/'));

    expect($path)->toBeFile("og:image verwijst naar {$m[1]}, maar dat bestand bestaat niet");
});

it('ships an og:image that social crawlers accept', function () {
    $file = public_path('og-default.png');

    expect($file)->toBeFile();

    [$width, $height] = (array) getimagesize($file);

    // LinkedIn/X/Facebook all want 1.91:1; below 600px wide they fall back to
    // a small square card, which is the blank-ish look we're fixing.
    expect($width)->toBe(1200)
        ->and($height)->toBe(630)
        ->and(filesize($file))->toBeLessThan(5 * 1024 * 1024);
});
