<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;

beforeEach(function () {
    $this->service = app(MailSubscriptionService::class);
});

it('leaves a form signup unconfirmed until the link is clicked', function () {
    $sub = $this->service->subscribe(
        email: 'Nieuw@Example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->confirmed_at)->toBeNull()
        ->and($sub->confirm_token)->not->toBeNull()
        ->and($sub->email)->toBe('nieuw@example.test');
});

/*
 * Een ingelogd lid met geverifieerd adres heeft al bewezen dat de mailbox van
 * hem is. Dat is precies wat e-mailverificatie doet, dus een tweede klik voegt
 * geen bewijs toe en levert alleen afhakers op.
 */
it('confirms straight away for a verified account holder', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);

    $sub = $this->service->subscribe(
        email: 'lid@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'profiel',
        user: $user,
    );

    expect($sub->confirmed_at)->not->toBeNull()
        ->and($sub->user_id)->toBe($user->id);
});

it('confirms a subscription with its token and burns the token', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $confirmed = $this->service->confirm((string) $sub->confirm_token);

    expect($confirmed?->confirmed_at)->not->toBeNull()
        ->and($confirmed?->confirm_token)->toBeNull();
});

it('returns null for a confirm token that does not exist', function () {
    expect($this->service->confirm('onzin'))->toBeNull();
});

it('unsubscribes everything with one click', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('unsubscribes from offers only and leaves updates standing', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'offers');

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

it('unsubscribes from updates only and leaves offers standing', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'updates');

    expect($sub->fresh()->wants_offers)->toBeTrue()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('records the literal sentence that was agreed to', function () {
    $sub = $this->service->subscribe(
        email: 'bewijs@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['storage'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->consent_text)->toBe('Ja, mail mij nieuw aanbod in deze categorieen.')
        ->and($sub->consent_given_at)->not->toBeNull();
});

it('throws away signups that were never confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDays(8)]);
    MailSubscription::factory()->unconfirmed()->create(['created_at' => now()->subDay()]);
    MailSubscription::factory()->create(['created_at' => now()->subDays(30)]);

    $purged = $this->service->purgeUnconfirmed(7);

    expect($purged)->toBe(1)
        ->and(MailSubscription::query()->count())->toBe(2);
});

/*
 * Elke mail die ooit verstuurd is (bevestiging, aanbod, update) draagt de
 * afmeldlink van dát moment. Als resubscriben (bijv. voorkeuren aanpassen op
 * hetzelfde adres) een nieuw `unsubscribe_token` trekt, breekt dat de
 * afmeldlink in elke mail die al onderweg of gelezen is — precies het
 * mechanisme dat AVG-conform "gemakkelijk afmelden" moet blijven werken.
 */
it('keeps the unsubscribe link working after subscribing again on the same address', function () {
    $first = $this->service->subscribe(
        email: 'terug@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    $second = $this->service->subscribe(
        email: 'terug@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: ['storage'],
        consentText: 'Ja, mail mij ook updates over het platform.',
        source: 'formulier',
    );

    expect($second->unsubscribe_token)->toBe($first->unsubscribe_token);
});
