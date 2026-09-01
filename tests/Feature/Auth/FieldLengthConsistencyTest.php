<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

/*
 * Aanleiding: Hans Kruse merkte op LinkedIn op dat aanmeld-, inlog- en
 * wachtwoordschermen bij grote partijen verschillende regels hanteren voor
 * lengte en vorm, en dat het verderop in de keten alsnog misgaat. Zijn
 * voorbeeld was KPN, dat een e-mailadres op 52 tekens afkapt terwijl de RFC
 * ruimer is.
 *
 * Deze test houdt dat tegen de eigen code aan. Hij bewaakt twee dingen:
 *
 * 1. **Elke grens die de validatie stelt, moet de database ook aankunnen, en
 *    andersom.** Een veld dat door de validatie komt en dan op een
 *    kolomlengte stukloopt, geeft een 500 in plaats van een nette
 *    foutmelding. Dat is precies het "verderop in de keten"-geval.
 *
 * 2. **De regels mogen per scherm verschillen, maar alleen met reden.**
 *    Registratie en wachtwoordherstel zetten allebei `min:10` op het
 *    wachtwoord. Inloggen doet dat bewust níét: wie zich inschreef toen de
 *    grens lager lag, moet gewoon kunnen inloggen. Zou inloggen de nieuwe
 *    grens afdwingen, dan sluit je bestaande leden buiten zonder ze iets te
 *    kunnen aanbieden.
 */

/** RFC 5321: 254 tekens voor het hele adres is de praktische bovengrens. */
const RFC_MAX_EMAIL = 254;

it('accepts an email at the RFC limit instead of blowing up on the column', function () {
    /* Een lang adres is niet hetzelfde als één lange sliert. Het lokale deel
       mag 64 tekens, maar elk DNS-label in het domein maximaal 63, dus een
       domein van 189 tekens in één stuk is gewoon ongeldig en `email:strict`
       weigert het terecht. Vandaar drie labels. Dit was mijn eigen fout in de
       eerste versie van deze test. */
    $lokaal = str_repeat('a', 64);
    $label = str_repeat('b', 60);
    $adres = $lokaal.'@'.$label.'.'.$label.'.'.$label.'.nl';

    expect(strlen($adres))->toBeLessThanOrEqual(RFC_MAX_EMAIL)
        ->and(strlen($adres))->toBeGreaterThan(240);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('email', $adres)
        ->set('username', 'langadres')
        ->set('password', 'geheimgeheim')
        ->set('password_confirmation', 'geheimgeheim')
        ->set('accept_tos', true)
        ->call('submit');

    expect(User::query()->where('email', $adres)->exists())->toBeTrue();
});

it('rejects an email longer than the column can hold, with a message and not a crash', function () {
    $adres = str_repeat('a', 300).'@voorbeeld.nl';

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('email', $adres)
        ->set('username', 'telang')
        ->set('password', 'geheimgeheim')
        ->set('password_confirmation', 'geheimgeheim')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

/*
 * Wachtwoord. Er staat geen bovengrens op, en dat is een keuze met een reden:
 * NIST SP 800-63B schrijft voor dat je minstens 64 tekens toestaat en geen
 * samenstellingsregels oplegt. Wat er wél moet gebeuren is dat een lang
 * wachtwoord ook echt in zijn geheel meetelt. Bcrypt kapt af op 72 bytes; twee
 * wachtwoorden die de eerste 72 bytes delen zouden dan allebei werken.
 */
it('proves bcrypt itself stops looking after 72 bytes', function () {
    $basis = str_repeat('x', 72);

    /* Dit is geen bug in onze code maar in wat bcrypt nu eenmaal doet, en het
       staat hier zodat niemand de regel hieronder weghaalt met "dat valt wel
       mee". Twee verschillende wachtwoorden, dezelfde hash. */
    expect(\Illuminate\Support\Facades\Hash::check($basis.'TWEE', bcrypt($basis.'EEN')))
        ->toBeTrue();
});

it('refuses a password longer than bcrypt reads instead of silently ignoring the rest', function () {
    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('email', 'lang@voorbeeld.nl')
        ->set('username', 'langwoord')
        ->set('password', str_repeat('x', 73))
        ->set('password_confirmation', str_repeat('x', 73))
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

it('counts bytes and not characters, so accents cannot smuggle past the limit', function () {
    /* 40 keer "é" is 40 tekens maar 80 bytes. Met Laravels `max:72` zou dit
       erdoor glippen en alsnog afgekapt worden. */
    $wachtwoord = str_repeat('é', 40);
    expect(mb_strlen($wachtwoord))->toBe(40)
        ->and(strlen($wachtwoord))->toBe(80);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('email', 'accent@voorbeeld.nl')
        ->set('username', 'accenten')
        ->set('password', $wachtwoord)
        ->set('password_confirmation', $wachtwoord)
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors('password');
});

it('still accepts a long passphrase that fits', function () {
    $wachtwoord = str_repeat('a', 72);

    Livewire::test(\App\Livewire\Auth\Register::class)
        ->set('email', 'past@voorbeeld.nl')
        ->set('username', 'pastnog')
        ->set('password', $wachtwoord)
        ->set('password_confirmation', $wachtwoord)
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'past@voorbeeld.nl')->exists())->toBeTrue();
});

/*
 * De regels per scherm, expliciet vastgelegd. Verandert iemand er één, dan
 * moet hij hier langs en de reden opnieuw opschrijven.
 */
it('keeps the password rules deliberately asymmetric between signing up and signing in', function () {
    $register = file_get_contents(app_path('Livewire/Auth/Register.php'));
    $reset = file_get_contents(app_path('Livewire/Auth/ResetPassword.php'));
    $login = file_get_contents(app_path('Livewire/Auth/Login.php'));

    expect($register)->toContain("'min:10'")
        ->and($reset)->toContain("'min:10'")
        ->and($login)->not->toContain("'min:");
});
