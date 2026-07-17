<?php

declare(strict_types=1);

/*
 * De iconen zijn statische assets waar de <head> en het manifest naar wijzen.
 * Een verwijzing zonder bestand faalt stil — alleen een telefoon ziet het
 * kapotte thuisscherm-icoon. Dit pint dat elk bestand bestaat én het juiste
 * formaat heeft, zodat een leeg render-resultaat of een verkeerde maat opvalt.
 */
it('ships app icons at the declared sizes', function (string $file, int $size) {
    $path = public_path($file);

    expect($path)->toBeFile();

    [$width, $height] = (array) getimagesize($path);
    expect($width)->toBe($size)->and($height)->toBe($size);
})->with([
    ['apple-touch-icon.png', 180],
    ['icon-192.png', 192],
    ['icon-512.png', 512],
    ['icon-512-maskable.png', 512],
]);
