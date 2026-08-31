<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Services\Mail\MailSubscriptionService;

/*
 * Art. 11.7 lid 4 Telecommunicatiewet eist een makkelijke, gratis
 * afmeldmogelijkheid in elk bericht. Een link die eerst een login vraagt is
 * dat niet, en abonnees zonder account hebben die login sowieso niet.
 */
it('unsubscribes without any login at all', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('unsubscribes from just the offers when asked', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token.'?wat=offers')->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

it('confirms a signup through the link in the mail', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $this->post('/nieuwsbrief/bevestigen/'.$sub->confirm_token)->assertOk();

    expect($sub->fresh()->confirmed_at)->not->toBeNull();
});

/*
 * `confirmed_at` is het bewijsstuk onder art. 7 lid 1 AVG: het zegt dat iemand
 * zelf op de link in zijn eigen mailbox heeft geklikt. Een linkscanner van een
 * spamfilter of een prefetch van een mailclient doet precies zo'n GET, zonder
 * dat er een mens bij is. Ontstaat het bewijs daardoor, dan bewijst het niets
 * meer. De GET toont dus alleen wat er gaat gebeuren; pas de POST voert uit.
 */
it('does not confirm anything on a bare GET of the confirmation link', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $this->get('/nieuwsbrief/bevestigen/'.$sub->confirm_token)->assertOk();

    expect($sub->fresh()->confirmed_at)->toBeNull()
        ->and($sub->fresh()->confirm_token)->not->toBeNull();
});

/** Een HEAD is wat een scanner het liefst doet, en Laravel routeert hem als GET. */
it('does not confirm anything on a HEAD of the confirmation link', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create();

    $this->head('/nieuwsbrief/bevestigen/'.$sub->confirm_token)->assertOk();

    expect($sub->fresh()->confirmed_at)->toBeNull();
});

/*
 * Erger dan een voorbarig bewijsstuk: een geparkeerde wijziging is door een
 * vreemde ingediend, dus een prefetch zou de voorkeuren van iemand anders
 * doorvoeren zonder dat de eigenaar de mail ook maar geopend heeft.
 */
it('does not apply a parked change on a bare GET of the confirmation link', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);
    $sub->forceFill([
        'pending_changes' => ['wants_offers' => true, 'wants_updates' => true],
        'confirm_token' => 'geparkeerdDoorEenVreemde',
    ])->save();

    $this->get('/nieuwsbrief/bevestigen/geparkeerdDoorEenVreemde')->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse()
        ->and($sub->fresh()->pending_changes)->not->toBeNull();
});

/*
 * Bij `?wat=offers` blijft `wants_updates` terecht aanstaan. Beloofde de pagina
 * dan toch dat er niets meer komt, dan liegt hij over wat er zojuist is
 * vastgelegd, en dat is precies het soort belofte zonder code eronder dat dit
 * platform niet wil doen. De herstelknop voor updates zou bovendien iets
 * aanbieden dat nooit uit is gegaan.
 */
it('tells the truth on the page after a partial unsubscribe', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token.'?wat=offers')
        ->assertOk()
        ->assertDontSee('Er gaat geen mail meer naar dit adres')
        ->assertSee('Updates blijven wel komen')
        ->assertSee('Toch nieuw aanbod')
        ->assertDontSee('Toch updates');
});

it('offers both restore buttons after a full unsubscribe', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)
        ->assertOk()
        ->assertSee('Er gaat geen mail meer naar dit adres')
        ->assertSee('Toch nieuw aanbod')
        ->assertSee('Toch updates');
});

/*
 * De layout valt zonder expliciete canonical terug op `url()->current()`, en die
 * URL draagt hier een levend token. Dat zet het token in `<link rel="canonical">`
 * en `og:url`: precies de twee velden die bedoeld zijn om door te geven aan de
 * rest van de wereld. Eén doorgestuurde of gedeelde link volstaat dan om een
 * werkende afmeldlink van iemand anders in een index te krijgen.
 */
it('keeps the live token out of the indexable metadata', function () {
    $sub = MailSubscription::factory()->create();

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/nieuwsbrief').'">', false)
        ->assertSee('<meta property="og:url" content="'.url('/nieuwsbrief').'">', false)
        ->assertDontSee('canonical" href="'.url('/nieuwsbrief/afmelden'), false);
});

/** Tweede weg naar hetzelfde doel, voor de link die iemand zelf ergens plakt. */
it('disallows crawling of the newsletter token links', function () {
    expect((string) file_get_contents(public_path('robots.txt')))->toContain('Disallow: /nieuwsbrief/');
});

it('says so politely when a token means nothing', function () {
    $this->get('/nieuwsbrief/afmelden/onzin')->assertNotFound();
    $this->get('/nieuwsbrief/bevestigen/onzin')->assertNotFound();
});

it('lets someone undo an accidental unsubscribe', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    $this->post('/nieuwsbrief/opnieuw/'.$sub->unsubscribe_token, ['wat' => 'offers'])->assertOk();

    expect($sub->fresh()->wants_offers)->toBeTrue();
});

/*
 * Spiegelbeeld van de afmeldroute hieronder: die geeft een 400 op een onbekend
 * doel, dus mag de herstelroute niet stilzwijgend "Hersteld" melden terwijl er
 * niets is hersteld. Dezelfde link, dezelfde verminking, hetzelfde antwoord.
 */
it('rejects a nonsense resubscribe target instead of claiming it restored something', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    $this->post('/nieuwsbrief/opnieuw/'.$sub->unsubscribe_token, ['wat' => 'rommel'])->assertStatus(400);

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

it('rejects an array as resubscribe target', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => false, 'wants_updates' => false]);

    $this->post('/nieuwsbrief/opnieuw/'.$sub->unsubscribe_token, ['wat' => ['offers']])->assertStatus(400);

    expect($sub->fresh()->wants_offers)->toBeFalse();
});

/*
 * Taak 2 liet `MailSubscriptionService::unsubscribe()` een `InvalidArgumentException`
 * gooien op een onbekend afmelddoel, juist om te voorkomen dat zo'n waarde stil
 * naar "meld alles af" valt. Een controller die de query-parameter zelf eerst
 * plat filtert naar `null` zou die bewaking omzeilen. Deze test bewijst dat een
 * verminkte `?wat=`-waarde hard misgaat in plaats van in stilte alles af te melden.
 */
it('rejects a nonsense unsubscribe target instead of silently unsubscribing everything', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token.'?wat=rommel')->assertStatus(400);

    expect($sub->fresh()->wants_offers)->toBeTrue()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

/*
 * `?wat[]=offers` maakt van de query-parameter een array in plaats van een
 * string. Zonder de expliciete `is_string`-controle in de controller gaat die
 * array de service in, wat een `TypeError` zou geven in plaats van een nette
 * 400 — een verminkte link mag nooit een 500 opleveren.
 */
it('rejects an array as unsubscribe target', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token.'?wat[]=offers')->assertStatus(400);

    expect($sub->fresh()->wants_offers)->toBeTrue()
        ->and($sub->fresh()->wants_updates)->toBeTrue();
});

/*
 * Het hele scenario achter elkaar, want los van elkaar klopt elke stap: een
 * bevestigde rij, een vreemde die via het publieke formulier een wijziging
 * parkeert (dat mag, hij wordt immers niet toegepast), de eigenaar die zich
 * afmeldt, en dan een klik op de bevestigingslink uit een oudere mail. Zonder
 * de opruiming in `unsubscribe()` staat die persoon daarna weer op de lijst,
 * zonder dat hij zelf iets heeft aangevraagd.
 */
it('does not let an old confirmation link resurrect an unsubscribe', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'lid@example.test',
        'wants_offers' => true,
        'wants_updates' => true,
    ]);

    app(MailSubscriptionService::class)->subscribe(
        email: 'lid@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: ['networking'],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    $geparkeerd = (string) $sub->fresh()?->confirm_token;
    expect($geparkeerd)->not->toBe('');

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)->assertOk();
    $this->post('/nieuwsbrief/bevestigen/'.$geparkeerd)->assertNotFound();

    expect($sub->fresh()?->wants_offers)->toBeFalse()
        ->and($sub->fresh()?->wants_updates)->toBeFalse();
});
