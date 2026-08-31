<?php

declare(strict_types=1);

use App\Services\Ops\IntegrityReport;
use App\Services\Ops\UnansweredIssues;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
 * Rob Turks melding bleef 29 dagen liggen en hij zegde er zijn account om op.
 * Meldingen op het platform komen sinds 22-08 in de dagelijkse mail terecht,
 * maar een GitHub-issue nog steeds niet, en dat is precies het gat waar hij in
 * verdween. Zonder dit signaal is "we merken het vanzelf" een lege bewering.
 */

beforeEach(function () {
    config()->set('cloudmarktplaats.ops.issue_check', true);
    config()->set('cloudmarktplaats.ops.issue_repo', 'cloudmarktplaats/cloudmarktplaats');
    config()->set('cloudmarktplaats.ops.issue_maintainer', 'NickAldewereld');
    config()->set('cloudmarktplaats.ops.issue_days', 3);
});

/** Bouwt de vorm die de GitHub-API teruggeeft voor 1 issue. */
function issuePayload(array $overrides = []): array
{
    return array_merge([
        'number' => 36,
        'title' => 'Item "Controller" mist',
        'user' => ['login' => 'ramonfincken'],
        'created_at' => now()->subDays(5)->toIso8601String(),
        'comments' => 0,
    ], $overrides);
}

it('reports an issue that nobody answered', function () {
    Http::fake(['api.github.com/*' => Http::response([issuePayload()])]);

    $open = app(UnansweredIssues::class)->find();

    expect($open)->toHaveCount(1)
        ->and($open[0]['number'])->toBe(36)
        ->and($open[0]['title'])->toBe('Item "Controller" mist')
        ->and($open[0]['days'])->toBe(5);
});

/*
 * Het /issues-endpoint geeft ook pull requests terug. Op 31-08 stonden er 2
 * issues open en 2 Dependabot-PR's, en de API leverde er vier. Zonder dit
 * filter alarmeert de check elke week op Dependabot.
 */
it('ignores pull requests, which the issues endpoint also returns', function () {
    Http::fake(['api.github.com/*' => Http::response([
        issuePayload(['number' => 38, 'pull_request' => ['url' => 'https://api.github.com/…']]),
    ])]);

    expect(app(UnansweredIssues::class)->find())->toBe([]);
});

it('ignores an issue the maintainer already replied to', function () {
    Http::fake([
        'api.github.com/repos/*/issues?*' => Http::response([issuePayload(['comments' => 2])]),
        'api.github.com/repos/*/issues/36/comments*' => Http::response([
            ['user' => ['login' => 'iemand-anders']],
            ['user' => ['login' => 'NickAldewereld']],
        ]),
    ]);

    expect(app(UnansweredIssues::class)->find())->toBe([]);
});

it('still reports an issue where only other people commented', function () {
    Http::fake([
        'api.github.com/repos/*/issues?*' => Http::response([issuePayload(['comments' => 1])]),
        'api.github.com/repos/*/issues/36/comments*' => Http::response([
            ['user' => ['login' => 'iemand-anders']],
        ]),
    ]);

    expect(app(UnansweredIssues::class)->find())->toHaveCount(1);
});

/*
 * Aankondigingen die de beheerder zelf opent zijn geen onbeantwoorde melding.
 * Zonder deze uitzondering staat elk mededelingsissue elke ochtend te roepen.
 */
it('ignores an issue the maintainer opened himself', function () {
    Http::fake(['api.github.com/*' => Http::response([
        issuePayload(['user' => ['login' => 'NickAldewereld']]),
    ])]);

    expect(app(UnansweredIssues::class)->find())->toBe([]);
});

it('leaves a fresh issue alone until the threshold passes', function () {
    Http::fake(['api.github.com/*' => Http::response([
        issuePayload(['created_at' => now()->subDay()->toIso8601String()]),
    ])]);

    expect(app(UnansweredIssues::class)->find())->toBe([]);
});

/*
 * Zelfde regel als bij `composer audit`: nul teruggeven terwijl de controle
 * stuk is, is het gevaarlijkste antwoord dat deze mail kan geven.
 */
it('returns null when GitHub cannot be reached, never an empty list', function () {
    Http::fake(['api.github.com/*' => Http::response(status: 503)]);

    expect(app(UnansweredIssues::class)->find())->toBeNull();
});

it('returns null when the connection itself fails', function () {
    Http::fake(fn () => throw new ConnectionException('geen netwerk'));

    expect(app(UnansweredIssues::class)->find())->toBeNull();
});

it('says nothing at all when the check is switched off', function () {
    config()->set('cloudmarktplaats.ops.issue_check', false);
    Http::fake();

    expect(app(UnansweredIssues::class)->find())->toBe([]);
    Http::assertNothingSent();
});

/*
 * Het signaal moet ook echt in de dagelijkse mail belanden. De cijfertabel in
 * `daily-integrity.blade.php` heeft een hardgecodeerde sleutellijst, dus een
 * meting die daar niet in staat bereikt de lezer nooit; daarom is dit een
 * signaal en geen cijfer.
 */
it('puts an unanswered issue in the daily report', function () {
    config()->set('cloudmarktplaats.ops.issue_check', true);
    Http::fake(['api.github.com/*' => Http::response([issuePayload()])]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect(implode("\n", $rapport['signalen']))
        ->toContain('#36')
        ->toContain('5 dagen');
});

it('says it could not tell rather than staying silent when GitHub is down', function () {
    config()->set('cloudmarktplaats.ops.issue_check', true);
    Http::fake(['api.github.com/*' => Http::response(status: 503)]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect(implode("\n", $rapport['signalen']))->toContain('GitHub');
});
