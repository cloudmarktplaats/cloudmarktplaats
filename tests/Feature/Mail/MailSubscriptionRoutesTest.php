<?php

declare(strict_types=1);

use App\Models\MailSubscription;

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

    $this->get('/nieuwsbrief/bevestigen/'.$sub->confirm_token)->assertOk();

    expect($sub->fresh()->confirmed_at)->not->toBeNull();
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
