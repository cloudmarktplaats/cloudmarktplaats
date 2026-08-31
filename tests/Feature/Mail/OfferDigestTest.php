<?php

declare(strict_types=1);

use App\Mail\OfferDigestMail;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MailSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
    $this->networking = Category::factory()->create(['path' => 'networking.switches']);
});

/* Geen nieuws is geen mail. Dat is de hele spamrem. */
it('sends nothing when there is nothing new in the categories', function () {
    MailSubscription::factory()->create(['wants_offers' => true, 'categories' => ['networking']]);

    $this->artisan('mail:offers')->assertExitCode(0);

    Mail::assertNothingQueued();
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
