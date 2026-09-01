<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\SiweOnboarding;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Profile\TwoFactorSetup;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/*
 * Op de inlog-, registratie- en herstelvelden stond geen enkel
 * `autocomplete`-attribuut, terwijl de 2FA-velden wel netjes `one-time-code`
 * dragen. Zonder die tokens moet een wachtwoordmanager raden welk veld wat is,
 * en weet hij bij een wachtwoordwijziging niet aan welk account hij het nieuwe
 * wachtwoord moet hangen. Dat is dezelfde fout als plakken blokkeren, alleen
 * passief: in beide gevallen moet de manager gokken.
 *
 * Deze test kijkt naar de opgeleverde HTML en niet naar gedrag, want het gedrag
 * zit in de browser. Dat is de grens van dit bewijs: dit bewaakt dat de
 * attributen blijven staan, niet dat een specifieke manager ze respecteert.
 *
 * Wat hier bewust NIET is afgedekt, gemeten in Chrome op 01-09-2026. Livewire
 * 3.8.6 luistert bij tekstvelden alleen naar het `input`-event
 * (`dist/livewire.esm.js:3445`) en kent geen autofill-afhandeling. Zet een
 * vuller de waarde wel in de DOM maar vuurt hij geen event af, dan meldt het
 * inlogformulier "E-mailadres is verplicht." terwijl beide velden zichtbaar
 * gevuld zijn. Met een `input`-event lukt inloggen wel, en de gangbare managers
 * vuren dat event juist daarom af. `wire:model.blur` lost dit níet op: in
 * `getModifierTail` (regel 11890) wordt `blur` eruit gefilterd voordat het naar
 * Alpine gaat, en Livewires eigen `@blur` roept alleen `$commit()` aan, wat de
 * bestaande staat verstuurt zonder de DOM te lezen. Geprobeerd, gemeten, weer
 * verwijderd.
 */

it('marks the login fields so a password manager knows what they are', function () {
    $html = Livewire::test(Login::class)->html();

    expect($html)
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="current-password"');
});

it('marks the registration fields, with the nickname apart from the login name', function () {
    $html = Livewire::test(Register::class)->html();

    expect($html)
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="nickname"')
        ->toContain('autocomplete="new-password"');
});

/*
 * Het e-mailveld staat hier op readonly en lijkt daarmee overbodig. Het is het
 * tegendeel: Chrome en Firefox bieden het opslaan van een gewijzigd wachtwoord
 * alleen aan als er een veld met `autocomplete="username"` in hetzelfde
 * formulier staat. Zonder dat veld slaat de manager het nieuwe wachtwoord
 * nergens op en staat de gebruiker de volgende keer opnieuw buiten.
 */
it('keeps a username field on the reset form so the new password can be saved', function () {
    $html = Livewire::test(ResetPassword::class, ['token' => 'x'])->html();

    expect($html)
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="new-password"');
});

it('marks the forgot-password field as the account name', function () {
    $html = Livewire::test(ForgotPassword::class)->html();

    expect($html)->toContain('autocomplete="username"');
});

it('marks the wallet onboarding fields', function () {
    $html = Livewire::test(SiweOnboarding::class, ['address' => '0x'.str_repeat('a', 40)])->html();

    expect($html)
        ->toContain('autocomplete="username"')
        ->toContain('autocomplete="nickname"');
});

/*
 * Dit veld was al goed en moet dat blijven: `one-time-code` is wat iOS en
 * Android nodig hebben om de code uit een bericht aan te bieden. Het staat hier
 * omdat het de tegenhanger is van de suggestie om juist plakken te blokkeren,
 * en het `inputmode` is bewust `text` en niet `numeric`, want een recovery-code
 * mag hier ook.
 */
it('keeps the two-factor field fillable from a message', function () {
    // Zonder een lopende 2FA-poging in de sessie breekt `mount()` af met 401.
    session(['pending_2fa_user_id' => User::factory()->create()->id]);

    $html = Livewire::test(TwoFactorChallenge::class)->html();

    expect($html)->toContain('autocomplete="one-time-code"');
});

it('marks the password behind switching off 2FA', function () {
    $user = User::factory()->create(['password_hash' => bcrypt('hunter2')]);
    UserIdentity::factory()->password()->for($user)->create();
    $user->forceFill([
        'two_factor_secret' => (new Google2FA)->generateSecretKey(),
        'two_factor_recovery_codes' => ['a'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $html = Livewire::actingAs($user)->test(TwoFactorSetup::class)->html();

    expect($html)->toContain('autocomplete="current-password"');
});

/*
 * Plakken blokkeren op wachtwoord- of MFA-velden is precies de verkeerde kant
 * op: NIST SP 800-63B (§5.1.1.2) schrijft voor dat verifiers het moeten
 * toestaan, omdat het blokkeren mensen wegduwt van hun wachtwoordmanager en dus
 * naar kortere wachtwoorden. Deze test staat hier zodat een goedbedoelde
 * suggestie het later niet alsnog binnensluipt.
 */
it('never blocks pasting anywhere in the interface', function () {
    $blade = collect(File::allFiles(resource_path('views')))
        ->merge(File::allFiles(resource_path('js')));

    $overtreders = $blade
        ->filter(fn ($f) => str_contains(strtolower($f->getContents()), 'onpaste')
            || str_contains($f->getContents(), "'paste'")
            || str_contains($f->getContents(), '"paste"'))
        ->map(fn ($f) => $f->getRelativePathname())
        ->values()
        ->all();

    expect($overtreders)->toBe([]);
});
