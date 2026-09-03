<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Livewire\ContactSeller;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
 * CVE-2026-48019 / GHSA-5vg9-5847-vvmq, CRLF-injectie in de standaard
 * `email`-regel. Er bestond geen 11.x-backport; op Laravel 11 stond hij als
 * genegeerd in `composer.json` (nagemeten op 01-09-2026).
 *
 * De upgrade naar Laravel 12.69.1 (03-09-2026) bevat de fix zelf: de bare
 * `email`-regel wijst `GIFTIG` nu ook al af. Vóór de fix accepteerden `email`
 * en `email:rfc` dit adres wel, maar `Symfony\Component\Mime\Address` weigerde
 * het alsnog met "contains control characters", dus er kwam nooit een
 * geïnjecteerde header naar buiten — dat is waarom dit nooit een lek was.
 *
 * Wat er wel van overbleef: zo'n adres kon in `users.email` belanden, en dan
 * mislukt elke mail aan die persoon in de worker. `email:strict` sluit dat af
 * aan de voorkant, waar het thuishoort — en blijft daarom de regel die deze
 * velden gebruiken, ook nu de framework-bug zelf gerepareerd is.
 */

const GIFTIG = '"a'."\r\n".'Bcc: x@y.nl"@b.nl';

it('shows the framework now rejects the historical CVE payload directly', function () {
    // Zakt dit eerste deel ooit weer terug naar `true`, dan is de framework-fix
    // teruggedraaid en moet dit bestand opnieuw tegen die kwetsbaarheid testen.
    expect(Validator::make(['e' => GIFTIG], ['e' => ['email']])->passes())->toBeFalse()
        ->and(Validator::make(['e' => GIFTIG], ['e' => ['email:strict']])->passes())->toBeFalse();
});

it('refuses to create an account on an address with control characters', function () {
    RateLimiter::clear('register:127.0.0.1');

    Livewire::test(Register::class)
        ->set('email', GIFTIG)
        ->set('username', 'giftig')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasErrors('email');

    expect(User::count())->toBe(0);
});

it('refuses the same address when contacting a seller', function () {
    $listing = Listing::factory()->published()->create();

    Livewire::test(ContactSeller::class, ['listing' => $listing])
        ->set('formLoadedAt', now()->subMinute()->getTimestamp())
        ->set('email', GIFTIG)
        ->set('body', str_repeat('een net bericht dat lang genoeg is. ', 3))
        ->call('send')
        ->assertHasErrors('email');
});

it('still accepts an ordinary address', function () {
    expect(Validator::make(['e' => 'nick@example.com'], ['e' => ['email:strict']])->passes())->toBeTrue()
        ->and(Validator::make(['e' => 'nick+tag@sub.example.co.uk'], ['e' => ['email:strict']])->passes())->toBeTrue();
});
