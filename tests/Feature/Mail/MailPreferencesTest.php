<?php

declare(strict_types=1);

use App\Livewire\Profile\MailPreferences;
use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('cloudmarktplaats.features.mail_list', true);
});

it('links an existing subscription to the account that registers with that address', function () {
    MailSubscription::factory()->create(['email' => 'later@example.test', 'user_id' => null]);

    $user = User::factory()->create(['email' => 'later@example.test']);
    app(MailSubscriptionService::class)->linkToUser($user);

    expect(MailSubscription::query()->where('email', 'later@example.test')->first()?->user_id)->toBe($user->id);
});

it('prefills the profile form from an existing subscription', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'ingevuld@example.test']);
    MailSubscription::factory()->create([
        'email' => 'ingevuld@example.test',
        'user_id' => $user->id,
        'wants_offers' => true,
        'wants_updates' => true,
        'categories' => ['storage', 'compute'],
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->assertSet('wants_offers', true)
        ->assertSet('wants_updates', true)
        ->assertSet('categories', ['storage', 'compute']);
});

it('lets a member switch the mail off from their profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'lid@example.test']);
    MailSubscription::factory()->create([
        'email' => 'lid@example.test', 'user_id' => $user->id, 'wants_updates' => true,
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_updates', false)
        ->call('save');

    expect(MailSubscription::query()->where('email', 'lid@example.test')->first()?->wants_updates)->toBeFalse();
});

it('confirms straight away when a verified member ticks the box in their profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'nieuw@example.test']);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_updates', true)
        ->call('save');

    $sub = MailSubscription::query()->where('email', 'nieuw@example.test')->first();

    expect($sub?->confirmed_at)->not->toBeNull()
        ->and($sub?->consent_source)->toBe('profiel');
});

/*
 * Twee lege vinkjes zijn geen ongeldige invoer maar een echte afmelding.
 * subscribe() zou hier een lege consent_text wegschrijven en daarmee het
 * bestaande bewijs van toestemming overschrijven met bewijs van niets; dat mag
 * niet, dus dit pad hoort via unsubscribe() te lopen. Het bewijs hier is dat
 * consent_text blijft staan zoals hij was: unsubscribe() raakt dat veld niet
 * aan, subscribe() zou hem hebben leeggemaakt.
 */
it('really unsubscribes when both boxes end up unchecked', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'weg@example.test']);
    $sub = MailSubscription::factory()->create([
        'email' => 'weg@example.test',
        'user_id' => $user->id,
        'wants_offers' => true,
        'wants_updates' => true,
        'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
    ]);

    Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_offers', false)
        ->set('wants_updates', false)
        ->call('save');

    $fresh = $sub->fresh();

    expect($fresh?->wants_offers)->toBeFalse()
        ->and($fresh?->wants_updates)->toBeFalse()
        ->and($fresh?->consent_text)->toBe('Ja, mail mij nieuw aanbod in deze categorieen.');
});

/* Niets om af te melden, dus er hoort ook niets te ontstaan. */
it('does not create a subscription for a member who never had one', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'nooit@example.test']);

    Livewire::actingAs($user)->test(MailPreferences::class)->call('save');

    expect(MailSubscription::query()->where('email', 'nooit@example.test')->exists())->toBeFalse();
});

it('is not reachable while the flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/profile/mail')->assertNotFound();
});

it('is reachable while the flag is on', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/profile/mail')->assertOk();
});

/*
 * Zelfde val als bij het publieke aanmeldformulier: mount() draait niet meer
 * op een tabblad dat al openstond toen de vlag omging, dus de controle moet in
 * boot() staan. Zonder die controle daar levert deze test een 200 op in
 * plaats van een 404, en blijft een lid gewoon aan zijn voorkeuren kunnen
 * draaien terwijl de noodrem erop staat.
 */
it('refuses a save from a profile page that was already open when the flag went off', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'email' => 'openstaand@example.test']);

    $form = Livewire::actingAs($user)
        ->test(MailPreferences::class)
        ->set('wants_updates', true);

    config()->set('cloudmarktplaats.features.mail_list', false);

    $form->call('save')->assertNotFound();

    expect(MailSubscription::query()->count())->toBe(0);
});
