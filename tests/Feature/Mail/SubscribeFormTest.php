<?php

declare(strict_types=1);

use App\Livewire\Mail\Subscribe;
use App\Mail\MailSubscriptionConfirmMail;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
});

/** Een formulier dat lang genoeg op het scherm stond om de tijdklem te passeren. */
function subscribeForm(): Testable
{
    return Livewire::test(Subscribe::class)
        ->set('formLoadedAt', now()->subSeconds(10)->getTimestamp());
}

it('is not reachable while the flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);

    $this->get('/nieuwsbrief')->assertNotFound();
});

it('is reachable while the flag is on', function () {
    $this->get('/nieuwsbrief')->assertOk();
});

/*
 * De vlag is bedoeld als noodrem, niet als etalage. Een pagina die al openstond
 * toen de vlag omging, moet bij het verzenden alsnog stuklopen: mount() draait
 * daar niet meer, boot() wel. Zonder deze test blijft alles groen terwijl een
 * openstaand tabblad gewoon doorschrijft en mail laat versturen.
 */
it('refuses a save from a page that was already open when the flag went off', function () {
    $form = subscribeForm()
        ->set('email', 'openstaand@example.test')
        ->set('wants_updates', true);

    config()->set('cloudmarktplaats.features.mail_list', false);

    $form->call('save')->assertNotFound();

    expect(MailSubscription::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('signs someone up without an account and mails a confirmation', function () {
    subscribeForm()
        ->set('email', 'zolder@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['networking'])
        ->call('save')
        ->assertHasNoErrors();

    $sub = MailSubscription::query()->where('email', 'zolder@example.test')->first();

    expect($sub)->not->toBeNull()
        ->and($sub?->confirmed_at)->toBeNull()
        ->and($sub?->user_id)->toBeNull();

    Mail::assertQueued(MailSubscriptionConfirmMail::class);
});

/* Toestemming moet een handeling zijn. Geen vinkje, geen inschrijving. */
it('refuses a signup with neither box ticked', function () {
    subscribeForm()
        ->set('email', 'niets@example.test')
        ->set('wants_offers', false)
        ->set('wants_updates', false)
        ->call('save')
        ->assertHasErrors('wants_offers');

    expect(MailSubscription::query()->count())->toBe(0);
});

it('demands at least one category when offers are wanted', function () {
    subscribeForm()
        ->set('email', 'leeg@example.test')
        ->set('wants_offers', true)
        ->set('categories', [])
        ->call('save')
        ->assertHasErrors('categories');
});

/*
 * De categorie bepaalt straks wie welke mail krijgt, dus een verzonnen waarde
 * uit een request hoort er niet in te kunnen. De melding moet bovendien op het
 * scherm komen: hij landt op `categories.0` en niet op `categories`, dus een
 * view die alleen naar `categories` kijkt weigert stil.
 */
it('refuses a category that is not on the list and says so on screen', function () {
    subscribeForm()
        ->set('email', 'verzonnen@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['gratis-geld'])
        ->call('save')
        ->assertHasErrors('categories.0')
        ->assertSee('Kies alleen categorieën uit de lijst.');

    expect(MailSubscription::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

/*
 * Het bewijs van toestemming is de letterlijke zin die op het scherm stond, en
 * niets anders: een verwijzing of een versienummer bewijst niets. Daarom hier
 * letterlijk vergelijken met de constante die de view toont.
 */
it('stores the sentence that was on screen, word for word', function () {
    subscribeForm()
        ->set('email', 'bewijs@example.test')
        ->set('wants_updates', true)
        ->call('save');

    expect(MailSubscription::query()->where('email', 'bewijs@example.test')->first()?->consent_text)
        ->toBe(Subscribe::CONSENT_UPDATES);
});

it('stores the offers sentence word for word', function () {
    subscribeForm()
        ->set('email', 'aanbod@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['networking'])
        ->call('save');

    expect(MailSubscription::query()->where('email', 'aanbod@example.test')->first()?->consent_text)
        ->toBe(Subscribe::CONSENT_OFFERS);
});

it('stores both sentences word for word when both boxes are ticked', function () {
    subscribeForm()
        ->set('email', 'allebei@example.test')
        ->set('wants_offers', true)
        ->set('wants_updates', true)
        ->set('categories', ['networking'])
        ->call('save');

    expect(MailSubscription::query()->where('email', 'allebei@example.test')->first()?->consent_text)
        ->toBe(Subscribe::CONSENT_OFFERS.' '.Subscribe::CONSENT_UPDATES);
});

/*
 * Het formulier is publiek en `subscribe()` parkeert bij een al bevestigd
 * adres een wijziging plus een vers token, waarna er mail uitgaat. Zonder rem
 * kan een vreemde daarmee eindeloos mail naar andermans mailbox laten sturen;
 * de service ziet dat zelf niet, want er staat geen vervaltermijn op
 * `pending_changes`.
 */
it('stops a stranger from mailing the same address over and over', function () {
    $form = subscribeForm()
        ->set('email', 'doelwit@example.test')
        ->set('wants_updates', true);

    for ($i = 0; $i < 3; $i++) {
        $form->call('save')->assertHasNoErrors();
    }

    $form->call('save')->assertHasErrors('email');

    Mail::assertQueuedCount(3);
});

/*
 * Dezelfde twee vangnetten als ContactSeller. De rem per adres is letterlijk:
 * varieer het adres en je hebt een verse emmer. Deze twee kosten een bot niets
 * om te ontwijken als hij ze kent, maar wel moeite, en dat is precies wat
 * geautomatiseerd rondstrooien duurder maakt.
 */
it('silently drops a signup that fills the honeypot', function () {
    subscribeForm()
        ->set('email', 'bot@example.test')
        ->set('wants_updates', true)
        ->set('website', 'http://spam.example')
        ->call('save')
        ->assertSet('done', true);

    expect(MailSubscription::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('silently drops a signup submitted faster than a human could type', function () {
    Livewire::test(Subscribe::class)
        // formLoadedAt staat op "nu" na mount, dus er zit geen 2 seconden tussen.
        ->set('email', 'snel@example.test')
        ->set('wants_updates', true)
        ->call('save')
        ->assertSet('done', true);

    expect(MailSubscription::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

/*
 * Een geparkeerde wijziging op een bevestigd adres kan van iemand anders
 * komen. "Bevestig je aanmelding" is dan misleidend: de ontvanger staat er al
 * op en heeft dit misschien nooit gevraagd.
 */
it('mails a change request instead of a signup when a change is parked', function () {
    MailSubscription::factory()->create([
        'email' => 'alstop@example.test',
        'wants_offers' => true,
        'wants_updates' => false,
    ]);

    subscribeForm()
        ->set('email', 'alstop@example.test')
        ->set('wants_offers', true)
        ->set('wants_updates', true)
        ->set('categories', ['servers'])
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertHasSubject('Er is een wijziging aangevraagd');
        $mail->assertSeeInHtml('Doe je niets, dan blijft alles zoals het is.');
        // De aanvrager zag labels op het scherm, dus die horen ook in de mail.
        $mail->assertSeeInHtml('Server hardware');
        $mail->assertDontSeeInHtml('Categorieën: servers');
        // Deze gedaante bestaat juist voor iemand die zelf niets heeft aangeklikt.
        $mail->assertSeeInHtml('Dit is de zin die de aanvrager heeft aangevinkt:');

        return true;
    });
});

it('mails a plain confirmation when the address is new', function () {
    subscribeForm()
        ->set('email', 'nieuw@example.test')
        ->set('wants_updates', true)
        ->call('save');

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertHasSubject('Bevestig je aanmelding');

        return true;
    });
});

/*
 * Bij een onbevestigde rij staat het adres nog nergens op. "Dit adres staat op
 * de mailinglijst" is dan onwaar en ondergraaft de dubbele opt-in: de ontvanger
 * leest dat hij er al op staat en klikt de knop weg.
 */
it('does not claim a fresh address is already on the list', function () {
    subscribeForm()
        ->set('email', 'vers@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['servers'])
        ->call('save');

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertDontSeeInHtml('Dit adres staat op de mailinglijst');
        // En wat er is aangevinkt hoort de ontvanger ook hier te zien.
        $mail->assertSeeInHtml('Server hardware');

        return true;
    });
});

/*
 * Een adres dat zich heeft afgemeld levert geen bevestigingsmail meer op (zie
 * MailSubscriptionServiceTest). Het scherm erna moet dan wél hetzelfde blijven:
 * een afwijkende melding is een orakel waarmee een vreemde kan uitlezen of een
 * adres ooit op de lijst stond en zich afmeldde, en dat is precies het gegeven
 * dat we van hem afschermen. Hij krijgt dus hetzelfde beeld als bij een vers
 * adres, net zoals de honeypot en de tijdklem dat doen.
 */
it('shows the same screen for an address that unsubscribed as for a fresh one', function () {
    MailSubscription::factory()->create([
        'email' => 'stil@example.test',
        'confirmed_at' => now()->subWeek(),
        'confirm_token' => null,
        'wants_offers' => false,
        'wants_updates' => false,
        'unsubscribed_at' => now()->subDay(),
    ]);

    $afgemeld = subscribeForm()
        ->set('email', 'stil@example.test')
        ->set('wants_updates', true)
        ->call('save')
        ->assertHasNoErrors();

    $vers = subscribeForm()
        ->set('email', 'vers@example.test')
        ->set('wants_updates', true)
        ->call('save')
        ->assertHasNoErrors();

    // `wire:id` is per component-instantie uniek en zegt niets over wat de
    // bezoeker leest; de rest van het beeld moet letterlijk gelijk zijn.
    $zonderId = fn (string $html) => (string) preg_replace('/wire:id="[^"]+"/', '', $html);

    expect($afgemeld->get('done'))->toBeTrue()
        ->and($zonderId($afgemeld->html()))->toBe($zonderId($vers->html()));
});

/*
 * En dat gedeelde scherm mag niets beloven dat niet komt. Voor een afgemeld
 * adres wordt er geen link verstuurd, dus de tekst mag er niet 1 aankondigen
 * als vaststaand feit.
 */
it('does not promise a link it may not have sent', function () {
    $scherm = subscribeForm()
        ->set('email', 'belofte@example.test')
        ->set('wants_updates', true)
        ->call('save')
        ->html();

    expect($scherm)->not->toContain('Er staat een link klaar.');
});

/*
 * Ook de bevestigingsmail is een verzonden mail, dus ook daar hoort de afzender
 * met adres en KvK-nummer in te staan (art. 11.7 lid 4 Tw, art. 3:15d BW).
 */
it('names the sender with address and chamber of commerce number', function () {
    subscribeForm()
        ->set('email', 'afzender@example.test')
        ->set('wants_updates', true)
        ->call('save');

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertSeeInHtml('Aldewereld Consultancy');
        $mail->assertSeeInHtml('Nieuwe Hemweg 26');
        $mail->assertSeeInHtml('1013 CX Amsterdam');
        $mail->assertSeeInHtml('61862533');

        return true;
    });
});

/*
 * De minor uit de eindreview: het ontwerp zegt "elke verzonden mail" draagt de
 * List-Unsubscribe-header, zodat de afmeldknop in Gmail en Thunderbird werkt.
 * De bevestigingsmail had alleen de link in de body. RFC 8058 belooft met
 * List-Unsubscribe-Post dat de POST-route bestaat; die is er sinds taak 6.
 */
it('carries the unsubscribe headers on the confirmation mail', function () {
    subscribeForm()
        ->set('email', 'kopregel@example.test')
        ->set('wants_updates', true)
        ->call('save');

    $sub = MailSubscription::query()->where('email', 'kopregel@example.test')->firstOrFail();

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) use ($sub) {
        $headers = $mail->headers()->text;

        return $headers['List-Unsubscribe'] === '<'.route('mail.unsubscribe', $sub->unsubscribe_token).'>'
            && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

/*
 * Dezelfde spelling als de rest van de site. De aanbodmail schrijft "categorieën"
 * met trema (zie OfferDigestTest), het scherm ook; alleen de bevestigingsmail en
 * de validatiemelding deden het niet. `Subscribe::CONSENT_OFFERS` blijft
 * ongemoeid: dat is de woordelijk vastgelegde toestemmingstekst en dus
 * bewijsmateriaal, geen kopij.
 */
it('writes categorieen with the trema in the confirmation mail', function () {
    subscribeForm()
        ->set('email', 'trema@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['servers'])
        ->call('save');

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertSeeInHtml('Categorieën:');
        $mail->assertDontSeeInHtml('Categorieen:');

        return true;
    });
});

it('writes categorieen with the trema in the validation message', function () {
    subscribeForm()
        ->set('email', 'verzonnen2@example.test')
        ->set('wants_offers', true)
        ->set('categories', ['gratis-geld'])
        ->call('save')
        ->assertSee('Kies alleen categorieën uit de lijst.');
});
