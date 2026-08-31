<?php

declare(strict_types=1);

use App\Mail\DailyIntegrityMail;
use App\Models\MailSubscription;
use App\Services\Ops\IntegrityReport;

/*
 * Het aantal abonnees is een voorraad, en een voorraad hoort in deze mail een
 * getal te zijn en geen alarm. `concepten_zonder_foto` heeft laten zien wat er
 * gebeurt als dat wel gebeurt: maandenlang dezelfde zin over dezelfde tien
 * rijen, waardoor het elfde geval onzichtbaar werd.
 */
it('counts the confirmed subscribers in the daily report as a number, without alarming', function () {
    MailSubscription::factory()->count(3)->create();
    MailSubscription::factory()->unconfirmed()->create();

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['nieuwsbrief_abonnees'])->toBe(3)
        ->and($rapport['signalen'])->each->not->toContain('abonnee');
});

/*
 * De cijfertabel in de mail heeft een eigen lijst met labels: een meting die
 * daar niet in staat bestaat wel in het rapport maar komt nooit in de enige
 * mail die dit platform heeft. `meldingen_open` laat zien hoe dat afloopt --
 * dat getal is alleen zichtbaar als het ook een signaal geeft.
 */
it('puts both mailing list numbers in the mail itself', function () {
    MailSubscription::factory()->create();

    $html = (new DailyIntegrityMail(
        app(IntegrityReport::class)->build(now())['cijfers'],
        [],
        [],
        '31-08-2026',
    ))->render();

    expect($html)->toContain('Nieuwsbrief-abonnees')
        ->and($html)->toContain('Afmeldingen');
});

it('shows the count in the terminal report', function () {
    MailSubscription::factory()->create();

    $this->artisan('platform:daily-check --show')
        ->expectsOutputToContain('nieuwsbrief_abonnees')
        ->assertExitCode(0);
});

/*
 * Afmeldingen tellen als aanwas over een week, niet als totaal: het totaal
 * loopt alleen op en staat vanaf de eerste afmelding elke ochtend hetzelfde te
 * melden. Een week is bovendien precies één verzendcyclus (`mail:offers`
 * draait wekelijks), dus de reactie op een verzonden editie past er nog in.
 */
it('counts unsubscribes as a week of growth, not as a running total', function () {
    MailSubscription::factory()->create(['unsubscribed_at' => now()->subDays(2)]);
    MailSubscription::factory()->create(['unsubscribed_at' => now()->subDays(30)]);
    MailSubscription::factory()->create();

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['afmeldingen_afgelopen_week'])->toBe(1);
});

it('throws away signups that were never confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDays(9)]);

    $this->artisan('mail:purge-unconfirmed')->assertExitCode(0);

    expect(MailSubscription::query()->count())->toBe(0);
});

/*
 * De grens moet aan beide kanten kloppen. Een bevestigde inschrijving is
 * toestemming en verdwijnt nooit vanzelf, en wie zich gisteren aanmeldde heeft
 * nog dagen om de bevestigingsmail uit zijn spam te vissen.
 */
it('leaves confirmed rows and fresh signups alone', function () {
    MailSubscription::factory()->create(['created_at' => now()->subYear()]);
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDay()]);

    $this->artisan('mail:purge-unconfirmed')->assertExitCode(0);

    expect(MailSubscription::query()->count())->toBe(2);
});
