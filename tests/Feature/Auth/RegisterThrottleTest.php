<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Gemeten op 01-09-2026 op de lokale stack: 25 accounts achter elkaar vanaf
 * hetzelfde IP, nul blokkades. Login, wachtwoord-vergeten, contact, de
 * nieuwsbrief en de homelab-feed hebben allemaal een rem; registratie had er
 * geen, en ook geen honeypot terwijl contact en nieuwsbrief die wel hebben.
 *
 * De schade zit niet in de rommelaccounts maar in de mail: elke registratie
 * stuurt een verificatiemail via Hostinger. Een script verbrandt daarmee de
 * verzendlimiet en de domeinreputatie, en dat is precies de limiet die nog
 * niet vaststaat terwijl de mailinglijst klaarstaat om aan te gaan.
 *
 * Bewust GEEN tijdklem zoals bij `Mail\Subscribe`, waar een formulier binnen 2
 * seconden als bot geldt. De auth-velden dragen sinds vandaag
 * `autocomplete`-tokens, dus een wachtwoordmanager kan het formulier sneller
 * vullen dan een mens ooit typt. Die klem zou dan juist de nette gebruiker
 * buitensluiten. De honeypot heeft dat bezwaar niet.
 */

function registreerLid(int $n): Testable
{
    return Livewire::test(Register::class)
        ->set('email', "lid{$n}@voorbeeld.test")
        ->set('username', "lid{$n}")
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit');
}

beforeEach(function () {
    RateLimiter::clear('register:127.0.0.1');
});

it('stops the sixth signup from the same address within the hour', function () {
    foreach (range(1, 5) as $n) {
        registreerLid($n);
    }
    expect(User::count())->toBe(5);

    registreerLid(6)->assertHasErrors('email');

    expect(User::count())->toBe(5);
});

it('lets a normal signup through untouched', function () {
    registreerLid(1)->assertHasNoErrors();

    expect(User::where('email', 'lid1@voorbeeld.test')->exists())->toBeTrue();
});

/*
 * Een bot vult elk veld dat hij vindt. Het veld staat buiten beeld en heeft
 * `tabindex="-1"`, dus een mens komt er met toetsenbord noch muis. Wordt het
 * toch gevuld, dan gebeurt er niets en krijgt de bezoeker geen aanwijzing
 * waarom, precies zoals bij `ContactSeller` en `Mail\Subscribe`.
 */
it('quietly drops a signup that filled the invisible field', function () {
    Livewire::test(Register::class)
        ->set('email', 'bot@voorbeeld.test')
        ->set('username', 'botje')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->set('website', 'https://spam.example')
        ->call('submit');

    expect(User::count())->toBe(0);
});

it('does not spend an attempt on a signup that never got made', function () {
    // Een mislukte poging (te kort wachtwoord) mag de teller niet vullen,
    // anders sluit iemand met een typefout zichzelf buiten.
    foreach (range(1, 6) as $n) {
        Livewire::test(Register::class)
            ->set('email', "faal{$n}@voorbeeld.test")
            ->set('username', "faal{$n}")
            ->set('password', 'kort')
            ->set('password_confirmation', 'kort')
            ->set('accept_tos', true)
            ->call('submit')
            ->assertHasErrors('password');
    }

    registreerLid(9)->assertHasNoErrors();
    expect(User::count())->toBe(1);
});
