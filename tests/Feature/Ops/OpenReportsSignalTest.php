<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Report;
use App\Services\Ops\IntegrityReport;

/*
 * Zolang er vooraf gemodereerd werd, kwam rommel het aanbod nooit in. Sinds
 * 22-08 wel, en dan is een melding van een gebruiker het enige wat ons nog
 * waarschuwt. Die meldingen stonden alleen in het Filament-paneel, waar je
 * voor moet inloggen — terwijl de dagelijkse mail volgens AGENTS.md de enige
 * zichtbaarheid is die dit platform heeft. "Als het een probleem wordt merken
 * we het vanzelf" is pas waar als het ergens langskomt.
 */

it('counts open reports in the daily check', function () {
    $listing = Listing::factory()->published()->create();
    Report::factory()->count(2)->create([
        'reportable_type' => Listing::class,
        'reportable_id' => $listing->id,
        'status' => 'open',
    ]);
    Report::factory()->create([
        'reportable_type' => Listing::class,
        'reportable_id' => $listing->id,
        'status' => 'resolved',
    ]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['meldingen_open'])->toBe(2);
});

it('raises a signal as soon as one report is waiting', function () {
    $listing = Listing::factory()->published()->create();
    Report::factory()->create([
        'reportable_type' => Listing::class,
        'reportable_id' => $listing->id,
        'status' => 'open',
    ]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect(implode(' ', $rapport['signalen']))->toContain('melding');
});

it('stays quiet when nothing is reported', function () {
    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['meldingen_open'])->toBe(0)
        ->and(implode(' ', $rapport['signalen']))->not->toContain('melding');
});
