<?php

declare(strict_types=1);

use App\Livewire\Mail\Subscribe;
use App\Mail\MailSubscriptionConfirmMail;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('cloudmarktplaats.features.mail_list', true);
});

it('is not reachable while the flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);

    $this->get('/nieuwsbrief')->assertNotFound();
});

it('is reachable while the flag is on', function () {
    $this->get('/nieuwsbrief')->assertOk();
});

it('signs someone up without an account and mails a confirmation', function () {
    Livewire::test(Subscribe::class)
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
    Livewire::test(Subscribe::class)
        ->set('email', 'niets@example.test')
        ->set('wants_offers', false)
        ->set('wants_updates', false)
        ->call('save')
        ->assertHasErrors('wants_offers');

    expect(MailSubscription::query()->count())->toBe(0);
});

it('demands at least one category when offers are wanted', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'leeg@example.test')
        ->set('wants_offers', true)
        ->set('categories', [])
        ->call('save')
        ->assertHasErrors('categories');
});

it('stores the sentence that was on screen', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'bewijs@example.test')
        ->set('wants_updates', true)
        ->call('save');

    expect(MailSubscription::query()->first()?->consent_text)->toContain('Ja,');
});

/*
 * Het formulier is publiek en `subscribe()` parkeert bij een al bevestigd
 * adres een wijziging plus een vers token, waarna er mail uitgaat. Zonder rem
 * kan een vreemde daarmee eindeloos mail naar andermans mailbox laten sturen;
 * de service ziet dat zelf niet, want er staat geen vervaltermijn op
 * `pending_changes`.
 */
it('stops a stranger from mailing the same address over and over', function () {
    $form = Livewire::test(Subscribe::class)
        ->set('email', 'doelwit@example.test')
        ->set('wants_updates', true);

    for ($i = 0; $i < 3; $i++) {
        $form->call('save')->assertHasNoErrors();
    }

    $form->call('save')->assertHasErrors('email');

    Mail::assertQueuedCount(3);
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

    Livewire::test(Subscribe::class)
        ->set('email', 'alstop@example.test')
        ->set('wants_updates', true)
        ->call('save')
        ->assertHasNoErrors();

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertHasSubject('Er is een wijziging aangevraagd');
        $mail->assertSeeInHtml('Doe je niets, dan blijft alles zoals het is.');

        return true;
    });
});

it('mails a plain confirmation when the address is new', function () {
    Livewire::test(Subscribe::class)
        ->set('email', 'nieuw@example.test')
        ->set('wants_updates', true)
        ->call('save');

    Mail::assertQueued(MailSubscriptionConfirmMail::class, function (MailSubscriptionConfirmMail $mail) {
        $mail->assertHasSubject('Bevestig je aanmelding');

        return true;
    });
});
