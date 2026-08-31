<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Support\Str;

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

/*
 * `email_verified_at` bewijst één mailbox: die van het account zelf. Zonder de
 * vergelijking met `$user->email` kan elk ingelogd lid een willekeurig adres
 * meteen op bevestigd zetten en dat adres daarmee zonder klik van de eigenaar
 * op de lijst zetten. Dat is precies de toestemming die niet gegeven is.
 */
it('does not confirm an address the logged in member cannot prove', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);

    $sub = $this->service->subscribe(
        email: 'iemand-anders@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
        user: $user,
    );

    expect($sub->confirmed_at)->toBeNull()
        ->and($sub->confirm_token)->not->toBeNull()
        ->and($sub->user_id)->toBeNull();
});

/*
 * Adressen worden genormaliseerd opgeslagen, dus de vergelijking met het adres
 * van het account moet dat ook doen. Anders zakt een lid dat zijn eigen adres
 * met hoofdletters intypt onnodig terug naar de dubbele opt-in.
 */
it('recognises the members own address regardless of case and spacing', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);

    $sub = $this->service->subscribe(
        email: '  LID@Example.test ',
        wantsOffers: true,
        wantsUpdates: false,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'profiel',
        user: $user,
    );

    expect($sub->confirmed_at)->not->toBeNull()
        ->and($sub->user_id)->toBe($user->id);
});

/*
 * Het scenario dat de review aantoonde: een vreemde vult op het publieke
 * formulier het adres van een ander in. Zou die aanmelding de bestaande rij
 * terugzetten naar onbevestigd, dan is de rij ouder dan zeven dagen én
 * onbevestigd, en gooit de opruiming van diezelfde nacht een bewezen
 * inschrijving definitief weg.
 */
it('keeps a confirmed subscription alive when a stranger enters the same address', function () {
    MailSubscription::factory()->create([
        'email' => 'bewezen@example.test',
        'created_at' => now()->subDays(30),
        'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
    ]);

    $this->service->subscribe(
        email: 'bewezen@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'formulier',
    );

    $this->service->purgeUnconfirmed(7);

    $sub = MailSubscription::query()->where('email', 'bewezen@example.test')->first();

    // Zonder deze twee blijft de test groen als de hele parkeertak wordt
    // uitgeschakeld: `confirmed_at` staat dan toevallig nog, maar de
    // vreemde heeft dan gewoon het bewijs overschreven. `consent_text` moet
    // het origineel blijven en de gevraagde wijziging moet echt geparkeerd
    // staan, anders toetst deze test het mechanisme niet.
    expect($sub)->not->toBeNull()
        ->and($sub?->confirmed_at)->not->toBeNull()
        ->and($sub?->consent_text)->toBe('Ja, mail mij nieuw aanbod in deze categorieen.')
        ->and($sub?->pending_changes)->not->toBeNull();
});

/*
 * Een bevestigde rij is bewijs van toestemming. Een aanroeper die niet kan
 * aantonen dat het adres van hem is, mag daar niets aan veranderen: niet de
 * voorkeuren, niet het toestemmingsbewijs en niet de koppeling met het account
 * (die koppeling draagt de wisverplichting uit taak 1).
 */
it('parks a strangers changes as pending instead of applying them', function () {
    $user = User::factory()->create(['email' => 'eigenaar@example.test']);
    $sub = MailSubscription::factory()->create([
        'email' => 'eigenaar@example.test',
        'user_id' => $user->id,
        'wants_offers' => true,
        'wants_updates' => false,
        'categories' => ['networking'],
        'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
        'consent_source' => 'profiel',
    ]);

    $this->service->subscribe(
        email: 'eigenaar@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: ['storage'],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'formulier',
    );

    $fresh = $sub->fresh();

    expect($fresh?->wants_offers)->toBeTrue()
        ->and($fresh?->wants_updates)->toBeFalse()
        ->and($fresh?->categories)->toBe(['networking'])
        ->and($fresh?->consent_text)->toBe('Ja, mail mij nieuw aanbod in deze categorieen.')
        ->and($fresh?->consent_source)->toBe('profiel')
        ->and($fresh?->user_id)->toBe($user->id)
        ->and($fresh?->confirmed_at)->not->toBeNull()
        ->and($fresh?->confirm_token)->not->toBeNull()
        ->and($fresh?->pending_changes)->not->toBeNull();
});

/*
 * De geparkeerde wijziging is pas een wijziging als de eigenaar van de mailbox
 * op de link klikt. Daarna moet het parkeervak leeg zijn, anders zou een
 * volgende bevestiging hem een tweede keer toepassen.
 */
it('applies the pending changes only when the link is clicked', function () {
    MailSubscription::factory()->create([
        'email' => 'eigenaar@example.test',
        'wants_offers' => true,
        'wants_updates' => false,
        'categories' => ['networking'],
    ]);

    $parked = $this->service->subscribe(
        email: 'eigenaar@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: ['storage'],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'formulier',
    );

    // Vóór de klik staat de rij er nog precies zo bij als daarvoor.
    expect($parked->fresh()?->wants_updates)->toBeFalse();

    $confirmed = $this->service->confirm((string) $parked->confirm_token);

    expect($confirmed?->wants_offers)->toBeFalse()
        ->and($confirmed?->wants_updates)->toBeTrue()
        ->and($confirmed?->categories)->toBe(['storage'])
        ->and($confirmed?->consent_text)->toBe('Ja, stuur mij updates over het platform.')
        ->and($confirmed?->consent_source)->toBe('formulier')
        ->and($confirmed?->confirm_token)->toBeNull()
        ->and($confirmed?->pending_changes)->toBeNull()
        ->and($confirmed?->fresh()?->wants_updates)->toBeTrue()
        // Het parkeervak is jsonb, dus de datum reist als tekst; na de klik moet
        // het weer een datum zijn en niet een string die stilletjes op 1970 valt.
        ->and($confirmed?->fresh()?->consent_given_at?->toDateString())->toBe(now()->toDateString());
});

/*
 * Zolang een rij nog niet bevestigd is, is er geen bewijs om te beschermen: een
 * tweede poging op hetzelfde adres mag die rij gewoon overschrijven en blijft
 * onbevestigd.
 */
it('overwrites an unconfirmed signup in place', function () {
    MailSubscription::factory()->unconfirmed()->create([
        'email' => 'nog-niet@example.test',
        'wants_updates' => false,
    ]);

    $sub = $this->service->subscribe(
        email: 'nog-niet@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: ['storage'],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'formulier',
    );

    expect($sub->wants_updates)->toBeTrue()
        ->and($sub->confirmed_at)->toBeNull()
        ->and($sub->pending_changes)->toBeNull();
});

/*
 * De eigenaar zelf hoeft niet door het parkeervak: hij heeft de mailbox al
 * bewezen, dus zijn wijziging geldt meteen.
 */
it('lets the verified owner change a confirmed subscription straight away', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);
    MailSubscription::factory()->create([
        'email' => 'lid@example.test',
        'wants_offers' => true,
        'wants_updates' => false,
    ]);

    $sub = $this->service->subscribe(
        email: 'lid@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'profiel',
        user: $user,
    );

    expect($sub->wants_offers)->toBeFalse()
        ->and($sub->wants_updates)->toBeTrue()
        ->and($sub->user_id)->toBe($user->id)
        ->and($sub->pending_changes)->toBeNull()
        ->and($sub->confirm_token)->toBeNull();
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

/*
 * Vandaag vult alleen de service `pending_changes`, en alleen met de zes
 * velden uit `$wanted`. Maar `confirm()` doet `forceFill(array_merge($pending,
 * ...))` zonder de sleutels te filteren, dus zodra `pending_changes` ooit een
 * ander veld bevat, is dit mass-assignment op alles wat in het vak staat. Deze
 * test zet dat scenario rechtstreeks in de database neer (buiten de service
 * om) om te bewijzen dat het filter er echt is, niet dat de service toevallig
 * nette invoer geeft.
 */
it('only applies the allowed fields from a pending change, even if the column holds more', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'origineel@example.test',
        'user_id' => null,
        'wants_offers' => true,
    ]);
    $sub->forceFill([
        'confirm_token' => 'token-voor-mass-assignment-test',
        'pending_changes' => [
            'wants_offers' => false,
            'email' => 'gekaapt@example.test',
            'user_id' => 999999,
            'confirmed_at' => '1970-01-01',
        ],
    ])->save();

    $confirmed = $this->service->confirm('token-voor-mass-assignment-test');

    expect($confirmed?->email)->toBe('origineel@example.test')
        ->and($confirmed?->user_id)->toBeNull()
        ->and($confirmed?->wants_offers)->toBeFalse()
        ->and($confirmed?->confirmed_at?->toDateString())->toBe(now()->toDateString());
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

/*
 * Afmelden mag nooit iets áánzetten. Zonder de `&& $sub->wants_offers`-bewaking
 * krijgt iemand die aanbod al had uitgezet dat aanbod terug zodra hij zich van
 * updates afmeldt — mail sturen na een afmelding is precies wat niet mag.
 */
it('leaves an already switched off offers preference off when unsubscribing from updates', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'updates');

    expect($sub->fresh()?->wants_offers)->toBeFalse()
        ->and($sub->fresh()?->wants_updates)->toBeFalse();
});

/** Spiegelbeeld van hierboven, voor de bewaking op `wants_updates`. */
it('leaves an already switched off updates preference off when unsubscribing from offers', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => false]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'offers');

    expect($sub->fresh()?->wants_offers)->toBeFalse()
        ->and($sub->fresh()?->wants_updates)->toBeFalse();
});

/*
 * Een onbekende `$what` is een fout in de afmeldlink die wij zelf versturen,
 * geen keuze van de bezoeker. Stil alles afmelden zou die fout verbergen én
 * meer afmelden dan gevraagd.
 */
it('refuses an unsubscribe target it does not know', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    expect(fn () => $this->service->unsubscribe((string) $sub->unsubscribe_token, 'rommel'))
        ->toThrow(InvalidArgumentException::class);

    expect($sub->fresh()?->wants_offers)->toBeTrue()
        ->and($sub->fresh()?->wants_updates)->toBeTrue();
});

it('returns null for an unsubscribe token that does not exist', function () {
    expect($this->service->unsubscribe('onzin'))->toBeNull();
});

/*
 * Afmelden is het intrekken van toestemming voor dít adres, en dat moet ook
 * gelden voor wat er nog in de wachtrij staat. Een vreemde kan via het publieke
 * formulier een wijziging parkeren (geval 4 in `write()`) met een vers
 * `confirm_token`. Blijft dat vak staan, dan zet één klik op die oude
 * bevestigingslink de zojuist afgemelde voorkeuren weer aan. Een ingetrokken
 * toestemming mag nooit vanzelf terugkomen.
 */
it('throws away a parked change and its token when a confirmed address unsubscribes', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);
    $sub->forceFill([
        'pending_changes' => ['wants_offers' => true, 'wants_updates' => true],
        'confirm_token' => 'geparkeerd-door-een-vreemde',
    ])->save();

    $this->service->unsubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()?->pending_changes)->toBeNull()
        ->and($sub->fresh()?->confirm_token)->toBeNull();
});

/*
 * Spiegelbeeld: bij een nog onbevestigde rij is `confirm_token` geen sleutel
 * naar een geparkeerde wijziging maar de gewone dubbele opt-in. Dat token ook
 * wissen zou de bevestigingslink uit de aanmeldmail slopen, en dan kan niemand
 * zijn aanmelding meer afmaken. De wachtrij mag wél leeg.
 */
it('keeps the double opt in token when an unconfirmed row unsubscribes', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create(['wants_offers' => true]);
    $token = $sub->confirm_token;

    $this->service->unsubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()?->confirm_token)->toBe($token)
        ->and($sub->fresh()?->pending_changes)->toBeNull();
});

/*
 * Herstel na een afmelding is geen voortzetting van de oude toestemming: die
 * was ingetrokken. Blijven `consent_text` en `consent_given_at` naar dat oude
 * moment wijzen, dan bewijst het vak precies het verkeerde, namelijk een
 * toestemming die intussen was opgezegd. Er hoort dus een nieuw moment te
 * staan, met de zin die op de knop stond.
 */
it('records a fresh consent moment when a preference is restored', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => false,
        'wants_updates' => false,
        'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
        'consent_given_at' => now()->subMonths(2),
        'consent_source' => 'formulier',
    ]);

    $hersteld = $this->service->resubscribe((string) $sub->unsubscribe_token, 'offers');

    expect($hersteld?->wants_offers)->toBeTrue()
        ->and($hersteld?->wants_updates)->toBeFalse()
        ->and($hersteld?->consent_text)->toContain('hersteld na een afmelding')
        ->and($hersteld?->consent_given_at?->toDateString())->toBe(now()->toDateString())
        ->and($hersteld?->consent_source)->toBe('herstelknop');
});

/** Zonder doel is het spiegelbeeld van `unsubscribe()` zonder doel: alles. */
it('restores both preferences when no target is given', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    $this->service->resubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()?->wants_offers)->toBeTrue()
        ->and($sub->fresh()?->wants_updates)->toBeTrue();
});

/** Symmetrisch met `unsubscribe()`: onzin is onzin, niet "en dan maar alles". */
it('refuses a resubscribe target it does not know', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    expect(fn () => $this->service->resubscribe((string) $sub->unsubscribe_token, 'rommel'))
        ->toThrow(InvalidArgumentException::class);

    expect($sub->fresh()?->wants_offers)->toBeFalse()
        ->and($sub->fresh()?->wants_updates)->toBeFalse();
});

it('returns null for a resubscribe token that does not exist', function () {
    expect($this->service->resubscribe('onzin'))->toBeNull();
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
 * Het scenario dat de reviewer bewees: iemand meldt zich aan, de mail
 * verdwijnt in spam, en hij meldt zich dertig dagen later opnieuw aan op
 * hetzelfde adres. `purgeUnconfirmed(7)` kijkt naar `created_at`; blijft dat
 * op de eerste aanmelding staan, dan is de rij diezelfde nacht ouder dan het
 * venster en verdwijnt de zojuist verstuurde, nog levende bevestigingslink.
 * Het venster hoort te lopen vanaf de laatste aanmelding.
 */
it('refreshes the purge window when someone signs up again before confirming', function () {
    MailSubscription::factory()->unconfirmed()->create([
        'email' => 'spam-map@example.test',
        'created_at' => now()->subDays(30),
    ]);

    $this->service->subscribe(
        email: 'spam-map@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    $this->service->purgeUnconfirmed(7);

    expect(MailSubscription::query()->where('email', 'spam-map@example.test')->exists())->toBeTrue();
});

/*
 * Geval 4 (parkeren) verandert de toestemming van de eigenaar juist niet, dus
 * `created_at` van de bevestigde rij hoort ook niet mee te bewegen met een
 * vreemde die het adres invult. Zou dat wel gebeuren, dan verbergt dat straks
 * een reëel probleem: een oude, bevestigde inschrijving lijkt dan vers.
 */
it('leaves created_at untouched when a stranger only parks a change', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'bewezen@example.test',
        'created_at' => now()->subDays(30),
    ]);
    $originalCreatedAt = $sub->created_at;

    $this->service->subscribe(
        email: 'bewezen@example.test',
        wantsOffers: false,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, stuur mij updates over het platform.',
        source: 'formulier',
    );

    expect($sub->fresh()?->created_at?->equalTo($originalCreatedAt))->toBeTrue();
});

/*
 * De reviewer wees erop dat de `user_id`-bewaking geen enkele test had: de
 * regel `$owner?->id ?? $sub->user_id` kan teruggedraaid worden naar
 * `$owner?->id` zonder dat een van de bestaande tests rood wordt. Een
 * onbevestigde rij die al aan een account hangt, verliest dan bij een
 * anonieme `subscribe()` zijn koppeling, en de wiscascade uit taak 1 grijpt
 * dan niet meer bij accountverwijdering.
 */
it('keeps an existing account link when someone else subscribes anonymously to the same address', function () {
    $user = User::factory()->create(['email' => 'lid@example.test']);
    MailSubscription::factory()->unconfirmed()->create([
        'email' => 'lid@example.test',
        'user_id' => $user->id,
    ]);

    $sub = $this->service->subscribe(
        email: 'lid@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->user_id)->toBe($user->id);
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

/*
 * Na een afmelding wijzen `consent_text`, `consent_given_at` en
 * `consent_source` nog naar de toestemming die zojuist is ingetrokken. Zonder
 * een moment van intrekking ernaast bewijst de rij dus een toestemming die niet
 * meer bestaat, en dat is onder art. 7 lid 1 AVG erger dan geen bewijs. Dat is
 * dezelfde redenering die `resubscribe()` al volgt.
 */
it('records the moment a consent was withdrawn', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()?->unsubscribed_at?->toDateString())->toBe(now()->toDateString());
});

/* Ook een halve afmelding is het intrekken van een toestemming. */
it('records the moment even when only one kind of mail is switched off', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->service->unsubscribe((string) $sub->unsubscribe_token, 'offers');

    expect($sub->fresh()?->unsubscribed_at)->not->toBeNull();
});

/* Nieuwe toestemming, dus de intrekking ervoor is geschiedenis en geen stand. */
it('clears the withdrawal moment when the herstelknop gives a fresh consent', function () {
    $sub = MailSubscription::factory()->create([
        'wants_offers' => false,
        'wants_updates' => false,
        'unsubscribed_at' => now()->subDay(),
    ]);

    $this->service->resubscribe((string) $sub->unsubscribe_token);

    expect($sub->fresh()?->unsubscribed_at)->toBeNull();
});

it('clears the withdrawal moment when the same address signs up again', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'terug@example.test',
        'confirmed_at' => null,
        'unsubscribed_at' => now()->subDay(),
    ]);

    $this->service->subscribe(
        email: 'terug@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->fresh()?->unsubscribed_at)->toBeNull();
});

/*
 * Een geparkeerde wijziging van een vreemde is geen toestemming van de
 * eigenaar, dus die mag een vastgelegde intrekking niet wegpoetsen.
 */
it('keeps the withdrawal moment when a stranger only parks a change', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'afgemeld@example.test',
        'confirmed_at' => now(),
        'unsubscribed_at' => now()->subDay(),
    ]);

    $this->service->subscribe(
        email: 'afgemeld@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->fresh()?->unsubscribed_at)->not->toBeNull();
});

/*
 * Wordt een geparkeerde wijziging alsnog bevestigd met de klik uit de eigen
 * mailbox, dan is dat wel een nieuwe toestemming en vervalt de intrekking.
 *
 * De stand wordt hier met de hand neergezet en niet meer via `subscribe()`
 * opgebouwd: op een afgemelde rij parkeert `subscribe()` sinds de eindfix
 * niets meer. Deze regel in `confirm()` blijft staan als vangnet voor elk pad
 * dat later toch een wijziging naast een intrekking zet.
 */
it('clears the withdrawal moment once a parked change is confirmed from the mailbox', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'weerterug@example.test',
        'confirmed_at' => now(),
        'unsubscribed_at' => now()->subDay(),
    ]);

    $sub->forceFill([
        'confirm_token' => Str::random(48),
        'pending_changes' => [
            'wants_offers' => true,
            'wants_updates' => false,
            'categories' => ['networking'],
            'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
            'consent_given_at' => now(),
            'consent_source' => 'formulier',
        ],
    ])->save();

    $this->service->confirm((string) $sub->fresh()?->confirm_token);

    expect($sub->fresh()?->unsubscribed_at)->toBeNull();
});

/*
 * De kern van de eindfix. `unsubscribed_at` legt vast dat dit adres nee zei.
 * Geval 4 zou er een vers `confirm_token` op zetten, en dat token is precies
 * de reden dat er een bevestigingsmail naar díé mailbox gaat: een vreemde die
 * het adres op het publieke formulier intikt, laat ons dan mail sturen naar
 * iemand van wie we hebben vastgelegd dat hij zich afmeldde. De enige rem was
 * de ratelimiet. De privacyverklaring belooft letterlijk "meld je je af, dan
 * stopt de mail meteen", en die belofte hoort in de code te staan.
 */
it('parks nothing and sets no token on an address that unsubscribed', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'nee@example.test',
        'confirmed_at' => now()->subWeek(),
        'confirm_token' => null,
        'wants_offers' => false,
        'wants_updates' => false,
        'categories' => [],
        'unsubscribed_at' => now()->subDay(),
    ]);

    $this->service->subscribe(
        email: 'nee@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    $vers = $sub->fresh();

    expect($vers?->confirm_token)->toBeNull()
        ->and($vers?->pending_changes)->toBeNull()
        ->and($vers?->wants_offers)->toBeFalse()
        ->and($vers?->wants_updates)->toBeFalse()
        ->and($vers?->unsubscribed_at)->not->toBeNull();
});

/*
 * Tegenproef, zodat de bewaking hierboven niet stiekem geval 4 als geheel
 * uitzet: op een bevestigde rij die niet is afgemeld hoort een vreemde nog
 * steeds gewoon te parkeren, want dat is de weg waarlangs de eigenaar zelf een
 * wijziging kan doorvoeren.
 */
it('still parks a strangers change on a confirmed address that never unsubscribed', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'gewoon@example.test',
        'confirmed_at' => now()->subWeek(),
        'confirm_token' => null,
        'unsubscribed_at' => null,
    ]);

    $this->service->subscribe(
        email: 'gewoon@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect($sub->fresh()?->confirm_token)->not->toBeNull();
});

/*
 * De eigenaar zelf blijft wél terug kunnen komen: hij bewijst de mailbox met
 * `email_verified_at`, dus zijn aanmelding is geval 3 en geen geparkeerde
 * wijziging. Zonder deze test is "afgemeld blijft afgemeld" niet te
 * onderscheiden van "afgemeld is voorgoed op slot".
 */
it('lets the proven owner sign up again after unsubscribing', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'terugkomer@example.test']);
    MailSubscription::factory()->create([
        'email' => 'terugkomer@example.test',
        'user_id' => $user->id,
        'confirmed_at' => now()->subWeek(),
        'wants_offers' => false,
        'wants_updates' => false,
        'unsubscribed_at' => now()->subDay(),
    ]);

    $sub = $this->service->subscribe(
        email: 'terugkomer@example.test',
        wantsOffers: true,
        wantsUpdates: false,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'profiel',
        user: $user,
    );

    expect($sub->wants_offers)->toBeTrue()
        ->and($sub->unsubscribed_at)->toBeNull();
});
