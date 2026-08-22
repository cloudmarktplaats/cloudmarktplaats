<?php

declare(strict_types=1);

use App\Services\Ops\SecurityAdvisories;
use Illuminate\Support\Facades\Process;

/*
 * `composer audit` stond vanaf 6 augustus rood op twaalf adviezen en dat viel
 * ruim twee weken niemand op: de poort in CI deed zijn werk, maar een rood
 * tabblad is geen signaal dat iemand ontvángt. Dezelfde les als bij de
 * meldingen — het moet naar de dagelijkse mail, want dat is de enige plek waar
 * hier daadwerkelijk gekeken wordt.
 */

it('reports the number of advisories composer found', function () {
    Process::fake([
        '*' => Process::result(json_encode([
            'advisories' => [
                'guzzlehttp/guzzle' => [['title' => 'Iets ergs'], ['title' => 'Nog iets']],
                'league/commonmark' => [['title' => 'Derde ding']],
            ],
        ])),
    ]);

    expect(app(SecurityAdvisories::class)->count())->toBe(3);
});

it('reports zero when composer finds nothing', function () {
    Process::fake(['*' => Process::result(json_encode(['advisories' => []]))]);

    expect(app(SecurityAdvisories::class)->count())->toBe(0);
});

// Packagist onbereikbaar, container zonder netwerk, composer weg: dan weten we
// het niet. Null, geen 0 — anders meldt de mail "alles veilig" op het moment
// dat de controle juist stuk is, en dat is het gevaarlijkste antwoord.
it('returns null when it cannot tell, rather than claiming all clear', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'network is unreachable', exitCode: 1)]);

    expect(app(SecurityAdvisories::class)->count())->toBeNull();
});

it('returns null on unparseable output', function () {
    Process::fake(['*' => Process::result('dit is geen json')]);

    expect(app(SecurityAdvisories::class)->count())->toBeNull();
});
