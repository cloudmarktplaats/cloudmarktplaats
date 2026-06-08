<?php

declare(strict_types=1);

use App\Models\Listing;

it('maps every condition to a Dutch label', function (string $condition, string $label) {
    $listing = Listing::factory()->make(['condition' => $condition]);

    expect($listing->conditionLabel())->toBe($label);
})->with([
    ['new', 'Nieuw'],
    ['used', 'Gebruikt'],
    ['defective', 'Defect'],
    ['for_parts', 'Voor onderdelen'],
]);

it('maps every condition to a house-style badge token', function (string $condition, string $token) {
    $listing = Listing::factory()->make(['condition' => $condition]);

    expect($listing->conditionColor())->toBe($token);
})->with([
    ['new', 'cmp-blue'],
    ['used', 'cmp-signal'],
    ['defective', 'cmp-amber'],
    ['for_parts', 'cmp-muted'],
]);
