<?php

declare(strict_types=1);

it('ships a valid webmanifest whose icons all exist', function () {
    $path = public_path('site.webmanifest');
    expect($path)->toBeFile();

    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Cloudmarktplaats')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['theme_color'])->toBe('#F5F6F6');

    // Elk icoon dat het manifest belooft, moet als bestand bestaan.
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('links the manifest and apple-touch-icon from the marketing layout head', function () {
    $html = (string) $this->get('/')->getContent();

    expect($html)->toContain('rel="manifest"')
        ->and($html)->toContain('site.webmanifest')
        ->and($html)->toContain('rel="apple-touch-icon"')
        ->and($html)->toContain('apple-touch-icon.png')
        ->and($html)->toContain('name="theme-color"');
});
