<?php

declare(strict_types=1);

use App\Mail\DailyIntegrityMail;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\Ops\IntegrityReport;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->logPath = storage_path('framework/testing/integrity-test.log');
    @mkdir(dirname($this->logPath), 0777, true);
    @unlink($this->logPath);
    config([
        'cloudmarktplaats.ops.digest_to' => 'nick@example.test',
        'cloudmarktplaats.ops.log_path' => $this->logPath,
        'cloudmarktplaats.ops.silence_days' => 7,
    ]);
});

afterEach(function () {
    @unlink($this->logPath);
});

/*
 * Dit is de check die de panoramabug had gevangen: zes dagen lang geen enkele
 * geslaagde upload, terwijl niemand een klacht instuurde.
 */
it('treats a week without a single photo as a signal, not as a quiet week', function () {
    Listing::factory()->published()->create(['published_at' => now()->subHours(2)]);

    $this->artisan('platform:daily-check')->assertSuccessful();

    Mail::assertSent(DailyIntegrityMail::class, function (DailyIntegrityMail $mail) {
        return collect($mail->signalen)->contains(fn (string $s) => str_contains($s, 'Geen enkele foto'));
    });
});

it('stays quiet when photos and listings keep coming', function () {
    $listing = Listing::factory()->published()->create(['published_at' => now()->subHour()]);
    ListingPhoto::factory()->for($listing)->create(['created_at' => now()->subHour()]);

    $this->artisan('platform:daily-check')->assertSuccessful();

    Mail::assertSent(DailyIntegrityMail::class, fn (DailyIntegrityMail $mail) => $mail->signalen === []);
});

it('picks up error lines from the log and groups them', function () {
    $listing = Listing::factory()->published()->create(['published_at' => now()->subHour()]);
    ListingPhoto::factory()->for($listing)->create(['created_at' => now()->subHour()]);

    $stamp = now()->subHours(3)->format('Y-m-d H:i:s');
    file_put_contents($this->logPath, implode("\n", [
        "[{$stamp}] production.ERROR: Image dimensions out of bounds (8160x3768) {\"userId\":296}",
        "[{$stamp}] production.ERROR: Image dimensions out of bounds (8160x3768) {\"userId\":296}",
        '[2020-01-01 00:00:00] production.ERROR: Iets van heel lang geleden',
        "[{$stamp}] production.INFO: dit is geen fout",
    ])."\n");

    $this->artisan('platform:daily-check')->assertSuccessful();

    Mail::assertSent(DailyIntegrityMail::class, function (DailyIntegrityMail $mail) {
        $regels = collect($mail->fouten);

        return $regels->count() === 1
            && $regels->first()['aantal'] === 2
            && str_contains($regels->first()['regel'], 'dimensions out of bounds')
            // De context-JSON hoort eraf, en oude regels tellen niet mee.
            && ! str_contains($regels->first()['regel'], 'userId');
    });
});

it('flags drafts that hang without a photo', function () {
    $listing = Listing::factory()->published()->create(['published_at' => now()->subHour()]);
    ListingPhoto::factory()->for($listing)->create(['created_at' => now()->subHour()]);

    $draft = Listing::factory()->create(['state' => 'draft']);
    Listing::query()->whereKey($draft->id)->update(['updated_at' => now()->subDays(3)]);

    $this->artisan('platform:daily-check')->assertSuccessful();

    Mail::assertSent(DailyIntegrityMail::class, function (DailyIntegrityMail $mail) {
        return $mail->cijfers['concepten_zonder_foto'] === 1
            && collect($mail->signalen)->contains(fn (string $s) => str_contains($s, 'blijven hangen zonder foto'));
    });
});

it('sends nothing when no recipient is configured', function () {
    config(['cloudmarktplaats.ops.digest_to' => '']);

    $this->artisan('platform:daily-check')->assertSuccessful();

    Mail::assertNothingSent();
});

/*
 * Het stempelen van een verstuurde mail zette óók `updated_at`, want
 * `Listing::query()->update()` doet dat via Eloquent altijd. Gevolg: op 23-08
 * gingen tien vastgelopen concepten door één mailronde uit de meting
 * `concepten_zonder_foto`, die alleen concepten ouder dan 24 uur telt. De
 * dagelijkse mail meldde daarna "Geen signalen" terwijl er niets was opgelost.
 *
 * Een mail versturen ís geen activiteit van de verkoper, dus het mag de klok
 * van zijn concept niet vooruitzetten.
 */
it('does not reset a draft\'s age by mailing its owner about it', function () {
    $listing = Listing::factory()->create([
        'state' => 'draft',
        'updated_at' => now()->subDays(5),
    ]);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    $listing->refresh();

    expect($listing->photo_bug_notified_at)->not->toBeNull()
        ->and($listing->updated_at->isBefore(now()->subDays(4)))->toBeTrue();
});

it('keeps a stale draft visible in the daily check after it has been mailed', function () {
    Listing::factory()->create([
        'state' => 'draft',
        'updated_at' => now()->subDays(5),
    ]);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['concepten_zonder_foto'])->toBe(1);
});

/*
 * `concepten_zonder_foto` telt een voorraad, geen aanwas: die tien concepten
 * van juli en augustus dalen alleen als de verkoper zelf terugkomt, en vijf
 * ervan zijn toetsenbordgeklets dat nooit afkomt. Alarmeren op het totaal zet
 * dus dezelfde zin elke ochtend in de enige mail die dit platform heeft, en
 * dan verdwijnt een elfde geval in het ruis: "10 concept(en)" wordt "11" en
 * niemand ziet het verschil.
 *
 * Het alarm hoort daarom op wat er nog te dóen is: een concept waarover de
 * eigenaar nog niet gemaild is. Het getal blijft staan (de test hierboven),
 * alleen het signaal zwijgt zodra de bal bij de verkoper ligt.
 */
it('stops shouting about stuck drafts whose owner has already been mailed', function () {
    Listing::factory()->create(['state' => 'draft', 'updated_at' => now()->subDays(5)]);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['concepten_zonder_foto'])->toBe(1)
        ->and($rapport['signalen'])->each->not->toContain('blijven hangen zonder foto');
});

it('still shouts when a stuck draft has had no mail at all', function () {
    Listing::factory()->create(['state' => 'draft', 'updated_at' => now()->subDays(5)]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect(collect($rapport['signalen'])->contains(fn (string $s) => str_contains($s, 'blijven hangen zonder foto')))->toBeTrue();
});

/*
 * De reden dat het signaal überhaupt bestaat: een nieuw geval moet opvallen
 * naast een berg oude. Telt het signaal het totaal, dan gaat 10 naar 11 en
 * leest dat als dezelfde ochtend als gisteren.
 */
it('counts only the untouched drafts in the signal, not the whole backlog', function () {
    Listing::factory()->count(3)->create(['state' => 'draft', 'updated_at' => now()->subDays(5)]);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    Listing::factory()->create(['state' => 'draft', 'updated_at' => now()->subDays(2)]);

    $rapport = app(IntegrityReport::class)->build(now());

    expect($rapport['cijfers']['concepten_zonder_foto'])->toBe(4)
        ->and(collect($rapport['signalen'])->first(fn (string $s) => str_contains($s, 'blijven hangen zonder foto')))
        ->toContain('1 concept');
});
