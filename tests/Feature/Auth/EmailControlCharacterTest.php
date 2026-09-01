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
 * `email`-regel. Er bestaat geen 11.x-backport, dus `composer audit` blijft
 * hem melden en hij staat als genegeerd. Nagemeten op 01-09-2026 in plaats van
 * aangenomen.
 *
 * Uitkomst in twee delen. De regels `email` en `email:rfc` **accepteren** het
 * adres `"a\r\nBcc: x@y.nl"@b.nl`, dus de kwetsbaarheid is echt. Maar
 * `Symfony\Component\Mime\Address` weigert hem alsnog met "contains control
 * characters", dus er komt nooit een geinjecteerde header naar buiten. De
 * tweede linie houdt, en dat is waarom dit geen lek is.
 *
 * Wat er wel van overbleef: zo'n adres kon in `users.email` belanden, en dan
 * mislukt elke mail aan die persoon in de worker. `email:strict` sluit dat af
 * aan de voorkant, waar het thuishoort.
 */

const GIFTIG = '"a'."\r\n".'Bcc: x@y.nl"@b.nl';

it('shows why the bare email rule is not enough', function () {
    // Dit is de kwetsbaarheid zelf, niet ons gedrag. Zakt deze test ooit, dan
    // heeft Laravel hem gerepareerd en mag `strict` hieronder heroverwogen.
    expect(Validator::make(['e' => GIFTIG], ['e' => ['email']])->passes())->toBeTrue()
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
