<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;

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
    config()->set('cloudmarktplaats.features.mail_list', true);
    $sub = MailSubscription::factory()->create();

    $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/nieuwsbrief').'">', false)
        ->assertSee('<meta property="og:url" content="'.url('/nieuwsbrief').'">', false)
        ->assertDontSee('canonical" href="'.url('/nieuwsbrief/afmelden'), false);
});

/*
 * Afmelden werkt ook als `features.mail_list` uit staat, en dat moet: art. 11.7
 * lid 4 Tw geldt net zo goed voor mail die al verstuurd is toen de noodrem nog
 * open stond. Het aanmeldformulier op /nieuwsbrief bestaat dan echter niet, dus
 * een vaste canonical daarheen wijst naar een 404. Een canonical is een
 * verwijzing naar het origineel van deze pagina; wijst hij naar niets, dan is
 * hij erger dan geen canonical, en weghalen kan niet omdat de layout dan
 * terugvalt op de huidige URL mét token.
 */
it('never points the canonical at a page that does not exist', function () {
    $sub = MailSubscription::factory()->create();

    foreach ([true, false] as $vlag) {
        config()->set('cloudmarktplaats.features.mail_list', $vlag);

        $html = (string) $this->get('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)
            ->assertOk()
            ->getContent();

        expect(preg_match('/<link rel="canonical" href="([^"]+)">/', $html, $treffer))->toBe(1);
        expect($treffer[1])->not->toContain((string) $sub->unsubscribe_token);

        $this->get($treffer[1])->assertOk();
    }
});

/** Tweede weg naar hetzelfde doel, voor de link die iemand zelf ergens plakt. */
it('disallows crawling of the newsletter token links', function () {
    expect((string) file_get_contents(public_path('robots.txt')))->toContain('Disallow: /nieuwsbrief/');
});

/*
 * De schuine streep is het hele verschil: de tokenpaden eronder horen uit elke
 * index, het aanmeldformulier op /nieuwsbrief zelf hoort er juist in. Zonder
 * deze test is "Disallow: /nieuwsbrief" een aannemelijke opruimactie die de
 * pagina onvindbaar maakt.
 */
it('leaves the signup page itself crawlable', function () {
    $regels = array_map('trim', explode("\n", (string) file_get_contents(public_path('robots.txt'))));

    expect($regels)->not->toContain('Disallow: /nieuwsbrief');
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

/*
 * RFC 8058: staat `List-Unsubscribe-Post: List-Unsubscribe=One-Click` in de
 * mail, dan doet de mailclient een POST op de URL uit `List-Unsubscribe`.
 * Bestond alleen de GET, dan gaf die POST een 405 en mislukte het afmelden in
 * Gmail en Yahoo stil, en bij bulkmail is dat ook een afleverprobleem: die twee
 * meten of hun eigen afmeldknop werkt.
 */
it('unsubscribes on the one-click POST a mail client sends', function () {
    $sub = MailSubscription::factory()->create(['wants_offers' => true, 'wants_updates' => true]);

    $this->post('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token)->assertOk();

    expect($sub->fresh()->wants_offers)->toBeFalse()
        ->and($sub->fresh()->wants_updates)->toBeFalse();
});

/*
 * De mailclient heeft geen sessie en dus geen CSRF-token; RFC 8058 schrijft
 * daarom voor dat dit pad buiten de tokencontrole valt. Laravel zet die
 * controle in de testomgeving zelf al uit (`runningUnitTests()`), dus een POST
 * in een test bewijst hier niets. Deze test kijkt daarom naar de uitzondering
 * zelf, want anders staat de vrijstelling alleen in productie op de proef.
 */
it('leaves the one-click unsubscribe out of the CSRF check', function () {
    $sub = MailSubscription::factory()->create();
    $middleware = app(ValidateCsrfToken::class);
    $inExceptArray = new ReflectionMethod($middleware, 'inExceptArray');

    expect($inExceptArray->invoke($middleware, Request::create('/nieuwsbrief/afmelden/'.$sub->unsubscribe_token, 'POST')))->toBeTrue()
        ->and($inExceptArray->invoke($middleware, Request::create('/nieuwsbrief/bevestigen/'.$sub->unsubscribe_token, 'POST')))->toBeFalse();
});
