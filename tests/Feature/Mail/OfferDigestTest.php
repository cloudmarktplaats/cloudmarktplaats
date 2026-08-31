<?php

declare(strict_types=1);

use App\Livewire\Profile\MailPreferences;
use App\Mail\OfferDigestMail;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
    $this->networking = Category::factory()->create(['path' => 'networking.switches']);
});

/*
 * Geen nieuws is geen mail. Dat is de hele spamrem.
 *
 * En een lege ronde stempelt niet: schuift `offers_sent_at` toch op, dan valt
 * alles wat er die week bij kwam de week erna buiten het venster en verdwijnt
 * stil. Meten gaat via `DB::table()`, want een model dat zijn eigen waarde uit
 * de cache teruggeeft bewijst niets over wat er in de rij staat.
 */
it('sends nothing when there is nothing new in the categories', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    $voor = DB::table('mail_subscriptions')->where('id', $sub->id)->value('offers_sent_at');

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertNothingQueued();
    expect(DB::table('mail_subscriptions')->where('id', $sub->id)->value('offers_sent_at'))->toBe($voor);
});

it('mails the new listings in the categories someone picked', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertQueued(OfferDigestMail::class);
    expect($sub->fresh()->offers_sent_at)->not->toBeNull();
});

/*
 * Stempelen mag de klok van de rij niet vooruitzetten. `updated_at` zegt hier
 * wanneer de vóórkeuren wijzigden, en een mail versturen is geen wijziging van
 * de voorkeuren. Op 23-08 vielen tien vastgelopen concepten uit de meting
 * `concepten_zonder_foto` omdat een modelupdate hun `updated_at` vooruitzette;
 * dezelfde fout hier maakt "sinds wanneer koos jij dit" onbruikbaar.
 *
 * Beide metingen gaan langs `DB::table()`: Eloquent zou zijn eigen zojuist
 * geschreven waarde teruggeven en dat is precies de fout die we zoeken.
 */
it('stamps the send without touching updated_at', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    // Een week terug, anders valt een modelupdate binnen dezelfde seconde en
    // ziet de vergelijking het verschil niet.
    DB::table('mail_subscriptions')->where('id', $sub->id)->update(['updated_at' => now()->subWeek()]);
    $voor = DB::table('mail_subscriptions')->where('id', $sub->id)->value('updated_at');
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    $na = DB::table('mail_subscriptions')->where('id', $sub->id)->first();
    expect($na?->updated_at)->toBe($voor)
        ->and($na?->offers_sent_at)->toBeGreaterThan($voor);
});

it('skips a category the subscriber did not pick', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['storage'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

it('never mails an address that has not confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

it('sends nothing on a dry run and leaves the stamp alone', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers --dry-run');

    Mail::assertNothingQueued();
    expect($sub->fresh()->offers_sent_at->toDateTimeString())->toBe($sub->offers_sent_at->toDateTimeString());
});

/* De vlag is de noodrem: staat hij uit, dan verstuurt dit commando niets. */
it('sends nothing while the feature flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertNothingQueued();
});

/*
 * Bevestigd zijn is niet hetzelfde als mail willen. Wie zich voor het aanbod
 * afmeldde houdt `confirmed_at`, want de rij blijft bestaan voor de updates.
 */
it('never mails someone who withdrew consent for offers', function () {
    MailSubscription::factory()->create([
        'wants_offers' => false, 'wants_updates' => true, 'unsubscribed_at' => now(),
        'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

/*
 * Een geparkeerde wijziging laat een levend `confirm_token` op een bevestigde
 * rij staan. Dat is geen "nog niet bevestigd" en mag de mail niet tegenhouden.
 */
it('still mails a confirmed row that carries a pending change', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    $sub->forceFill([
        'confirm_token' => Str::random(48),
        'pending_changes' => ['wants_offers' => false],
    ])->save();
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class);
});

it('leaves out what was already in the previous digest', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subWeeks(2),
    ]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

/*
 * De eerste mail van een verse abonnee: nieuw is nieuw sinds zijn aanmelding,
 * niet de hele voorraad. Wie zich vandaag aanmeldt heeft het aanbod van vandaag
 * net zelf gezien; dat terugsturen is een catalogus, geen nieuwsbericht.
 */
it('gives a fresh subscriber only what arrived after signing up', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => null,
    ]);
    // De aanmelding van 3 dagen terug is het ijkpunt zolang er nog nooit een
    // aanbodmail uitging; `created_at` is niet fillable, dus hier rechtstreeks.
    DB::table('mail_subscriptions')->where('id', $sub->id)->update(['created_at' => now()->subDays(3)]);
    $oud = Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subDays(10),
    ]);
    $nieuw = Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subDay(),
    ]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) use ($oud, $nieuw) {
        return $mail->listings->pluck('id')->all() === [$nieuw->id]
            && ! $mail->listings->contains('id', $oud->id);
    });
});

it('ignores anything that is not published', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->create(['category_id' => $this->networking->id]);
    Listing::factory()->sold()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

/* Art. 11.7 lid 4 Tw: elke mail draagt een werkende afmeldweg. */
it('carries the unsubscribe link and the one-click headers', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) use ($sub) {
        $mail->assertSeeInHtml(route('mail.unsubscribe', ['token' => $sub->unsubscribe_token, 'wat' => 'offers']), false);
        $headers = $mail->headers()->text;

        return $headers['List-Unsubscribe'] === '<'.route('mail.unsubscribe', $sub->unsubscribe_token).'>'
            && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

/*
 * De openingszin noemt alleen de categorieën waar echt iets in staat. Wie
 * Networking en Storage aanvinkte en 1 switch krijgt, leest anders "in de
 * categorieën die je hebt aangevinkt: Networking, Storage" met niets uit
 * Storage eronder. Dat leest als een gemiste advertentie.
 */
it('names only the categories that actually carry something', function () {
    Category::factory()->create(['path' => 'storage.disks']);
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking', 'storage'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) {
        $mail->assertSeeInHtml('Networking');
        $mail->assertDontSeeInHtml('Storage');

        return true;
    });
});

/* Dezelfde spelling als de rest van de site: `faq` en `scope` schrijven trema. */
it('writes categorieen with the trema, like the rest of the site', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) {
        $mail->assertSeeInHtml('categorieën');
        $mail->assertDontSeeInHtml('categorieen');

        return true;
    });
});

/*
 * Een echte ronde draait in de scheduler en die uitvoer landt in een logbestand.
 * E-mailadressen horen daar niet in: dat is een lijst persoonsgegevens die
 * niemand daar zocht en die buiten de bewaartermijn van de tabel valt. Bij
 * `--dry-run` kijkt er een mens mee die juist wil zien wie er aan de beurt is.
 */
it('keeps addresses out of the output of a real run', function () {
    MailSubscription::factory()->create([
        'email' => 'abonnee@example.test',
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    // De proefdraai eerst: die stempelt niet, dus daarna is er nog steeds iets
    // nieuws te melden. Andersom zou de tweede ronde leeg zijn en niets meten.
    $this->artisan('mail:offers --dry-run')->expectsOutputToContain('abonnee@example.test');
    $this->artisan('mail:offers')->doesntExpectOutputToContain('abonnee@example.test');
});

/*
 * Een gepubliceerde advertentie zonder `published_at` valt buiten `published_at
 * > ijkpunt` en komt dus nooit in een aanbodmail. Op productie bestaat zo'n rij
 * niet (0 van 52 op 31-08) en de state machine stempelt bij elke overgang naar
 * `published`, maar op de ontwikkeldatabase staan er 3 uit juli. De uitkomst
 * blijft dus de veilige kant (liever missen dan oude voorraad als nieuw
 * versturen), maar hij hoort geteld te worden in plaats van stil te blijven.
 */
it('reports a published listing that has no publication date', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->create([
        'category_id' => $this->networking->id, 'state' => 'published', 'published_at' => null,
    ]);

    $this->artisan('mail:offers')
        ->expectsOutputToContain('zonder publicatiedatum')
        ->assertExitCode(0);

    Mail::assertNothingQueued();
});

it('shows the title, the price and a link to each listing', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    $listing = Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'title' => 'Cisco C2960X', 'price_cents' => 12_550,
    ]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) use ($listing) {
        $mail->assertSeeInHtml('Cisco C2960X');
        $mail->assertSeeInHtml('125,50');
        $mail->assertSeeInHtml(route('listings.detail', ['ulid' => $listing->ulid, 'slug' => $listing->slug]), false);

        return true;
    });
});

/* De enige reden dat `user_id` bestaat: het verschil moet in de tekst staan. */
it('tells a reader without an account what an account adds', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) {
        $mail->assertSeeInHtml('Zelf iets plaatsen kan alleen met een account');

        return true;
    });
});

it('stays silent about accounts when the reader already has one', function () {
    MailSubscription::factory()->for(User::factory())->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) {
        $mail->assertDontSeeInHtml('Zelf iets plaatsen kan alleen met een account');

        return true;
    });
});

/*
 * Reviewer-scenario 1: de herstelknop. `offers_sent_at` bleef staan op de
 * laatste verzending vóór de afmelding, en dit commando rekent vanaf dat
 * moment. Wie na twee maanden terugkomt kreeg daardoor in 1 mail alles wat er
 * in die twee maanden bij kwam — de reviewer mat er 40. Precies de catalogus
 * die dit commando in zijn eigen docblock zegt niet te sturen. Een verse
 * toestemming hoort het venster opnieuw te laten beginnen.
 */
it('does not mail the backlog after the herstelknop', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => false,
        'categories' => ['networking'],
        'offers_sent_at' => now()->subMonths(2),
        'unsubscribed_at' => now()->subMonth(),
    ]);
    Listing::factory()->count(3)->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subWeek(),
    ]);

    app(MailSubscriptionService::class)->resubscribe((string) $sub->unsubscribe_token, 'offers');

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

/* Tegenproef: wat er ná het herstel bij komt, gaat gewoon mee. */
it('still mails what arrives after the herstelknop', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => false,
        'categories' => ['networking'],
        'offers_sent_at' => now()->subMonths(2),
        'unsubscribed_at' => now()->subMonth(),
    ]);
    Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subWeek(),
    ]);

    app(MailSubscriptionService::class)->resubscribe((string) $sub->unsubscribe_token, 'offers');

    $nieuw = Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->addMinute(),
    ]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, fn (OfferDigestMail $mail) => $mail->listings->pluck('id')->all() === [$nieuw->id]);
});

/*
 * Reviewer-scenario 2: hetzelfde langs het profiel. Een lid zette beide vinkjes
 * uit (dat is een afmelding) en vinkt het aanbod later weer aan. Dat loopt langs
 * geval 3 in `write()` en niet langs de herstelknop, dus het venster moet daar
 * net zo goed opnieuw beginnen. De reviewer mat hier 20 advertenties in 1 mail.
 */
it('does not mail the backlog after a member ticks offers again in their profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);
    MailSubscription::factory()->create([
        'email' => 'lid@example.test',
        'user_id' => $user->id,
        'wants_offers' => false,
        'wants_updates' => true,
        'categories' => [],
        'offers_sent_at' => now()->subMonths(2),
    ]);
    Listing::factory()->count(3)->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subWeek(),
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_offers', true)
        ->set('categories', ['networking'])
        ->call('save')
        ->assertHasNoErrors();

    $this->artisan('mail:offers');

    Mail::assertNothingQueued();
});

/*
 * De grens van die verversing. Wie het aanbod al aan had staan en alleen zijn
 * categorieën bijstelt, geeft geen nieuwe toestemming voor aanbod: het venster
 * blijft staan. Zou het toch opschuiven, dan verdwijnt alles van deze week stil
 * — dezelfde fout als een lege ronde die toch stempelt, maar dan bij elke
 * profielwijziging.
 */
it('leaves the window alone when the offers box was already ticked', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'blijft@example.test']);
    $sub = MailSubscription::factory()->create([
        'email' => 'blijft@example.test',
        'user_id' => $user->id,
        'wants_offers' => true,
        'categories' => ['networking'],
        'offers_sent_at' => now()->subWeek(),
    ]);
    $vanDezeWeek = Listing::factory()->published()->create([
        'category_id' => $this->networking->id, 'published_at' => now()->subDays(3),
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('categories', ['networking', 'storage'])
        ->call('save')
        ->assertHasNoErrors();

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, fn (OfferDigestMail $mail) => $mail->listings->contains('id', $vanDezeWeek->id));
});

/*
 * Art. 11.7 lid 4 Telecommunicatiewet en art. 3:15d BW: de afzender moet in de
 * mail zelf herkenbaar zijn, met adres en KvK-nummer. Dat stond alleen in de
 * platformupdate. Dezelfde gegevens als in de privacyverklaring
 * (database/seeders/legal/privacy.nl.md); wijken ze af, dan klopt er 1 van de
 * twee niet.
 */
it('names the sender with address and chamber of commerce number', function () {
    MailSubscription::factory()->create([
        'wants_offers' => true, 'categories' => ['networking'], 'offers_sent_at' => now()->subWeek(),
    ]);
    Listing::factory()->published()->create(['category_id' => $this->networking->id]);

    $this->artisan('mail:offers');

    Mail::assertQueued(OfferDigestMail::class, function (OfferDigestMail $mail) {
        $mail->assertSeeInHtml('Aldewereld Consultancy');
        $mail->assertSeeInHtml('Nieuwe Hemweg 26');
        $mail->assertSeeInHtml('1013 CX Amsterdam');
        $mail->assertSeeInHtml('61862533');

        return true;
    });
});
