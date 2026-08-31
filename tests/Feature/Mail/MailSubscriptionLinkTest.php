<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Livewire\Auth\SiweOnboarding;
use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use App\Services\Profile\AccountRemovalService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
 * De koppeling tussen een losse inschrijving en een account is niet cosmetisch:
 * de wiscascade op `user_id` is het enige dat de inschrijving meeneemt als het
 * account wordt verwijderd. Precies daarom mag hij pas ontstaan als het adres
 * bewezen van dat account is, en moet hij op elk pad ontstaan dat accounts
 * aanmaakt. Deze tests lopen daarom door de echte registratiepaden heen en niet
 * langs de service, zodat de bedrading zelf vastligt.
 */

beforeEach(function (): void {
    Notification::fake();
});

/** Registreren zoals een bezoeker dat doet: via het formulier. */
function registerThroughTheForm(string $email, string $username = 'nieuwlid'): User
{
    Livewire::test(Register::class)
        ->set('email', $email)
        ->set('username', $username)
        ->set('password', 'secret-pass-123')
        ->set('password_confirmation', 'secret-pass-123')
        ->set('accept_tos', true)
        ->call('submit');

    return User::query()->where('email', $email)->firstOrFail();
}

/** De echte verificatielink uit de mail, met dezelfde handtekening. */
function visitVerificationLink(User $user): void
{
    test()->actingAs($user)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1((string) $user->email),
    ]))->assertRedirect();
}

function subscriptionFor(string $email): ?MailSubscription
{
    return MailSubscription::query()->where('email', $email)->first();
}

/*
 * Een account is bij het aanmaken niets meer dan een claim op een adres. Hangt
 * de inschrijving daar meteen aan, dan neemt een vreemde die op jouw adres
 * registreert jouw bevestigde inschrijving over, en wist hij hem met zijn eigen
 * account weer weg.
 */
it('leaves a confirmed subscription alone when a stranger registers on that address', function () {
    MailSubscription::factory()->create(['email' => 'jij@example.test', 'user_id' => null]);

    $stranger = registerThroughTheForm('jij@example.test', 'vreemde');

    expect(subscriptionFor('jij@example.test')?->user_id)->toBeNull();

    app(AccountRemovalService::class)->remove($stranger);

    expect(subscriptionFor('jij@example.test'))->not->toBeNull();
});

/*
 * En andersom: zodra het adres wél bewezen is, hoort de koppeling er te staan.
 * Het wissen van het account bewijst dat de koppeling echt de cascade draagt en
 * niet alleen een ingevuld veld is.
 */
it('links the subscription as soon as the account proves the address', function () {
    MailSubscription::factory()->create(['email' => 'ik@example.test', 'user_id' => null]);

    $user = registerThroughTheForm('ik@example.test');
    visitVerificationLink($user);

    expect(subscriptionFor('ik@example.test')?->user_id)->toBe($user->id);

    app(AccountRemovalService::class)->remove($user);

    expect(subscriptionFor('ik@example.test'))->toBeNull();
});

/*
 * OAuth maakt ook accounts aan. De provider levert een al bewezen adres, dus
 * hier hoort de koppeling meteen te staan; zonder dat blijft de inschrijving
 * met toestemmingsbewijs achter als het account verdwijnt.
 */
it('links the subscription when an oauth account is created on that address', function () {
    MailSubscription::factory()->create(['email' => 'gh@example.test', 'user_id' => null]);

    fakeSocialiteUser('github', '4242', 'gh@example.test', 'Ghost');
    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    $user = User::query()->where('email', 'gh@example.test')->firstOrFail();

    expect(subscriptionFor('gh@example.test')?->user_id)->toBe($user->id);

    app(AccountRemovalService::class)->remove($user);

    expect(subscriptionFor('gh@example.test'))->toBeNull();
});

/*
 * SIWE vraagt een adres maar bewijst het niet: dat pad verstuurt geen
 * verificatiemail. De koppeling hoort dus te wachten tot dit lid alsnog
 * verifieert, en dan gewoon te ontstaan.
 */
it('links the subscription for a wallet account only after it proves the address', function () {
    MailSubscription::factory()->create(['email' => 'wallet@example.test', 'user_id' => null]);

    Livewire::test(SiweOnboarding::class, ['address' => '0xdead000000000000000000000000000000000042'])
        ->set('email', 'wallet@example.test')
        ->set('username', 'walletlid')
        ->set('accept_tos', true)
        ->call('submit');

    $user = User::query()->where('email', 'wallet@example.test')->firstOrFail();

    expect(subscriptionFor('wallet@example.test')?->user_id)->toBeNull();

    visitVerificationLink($user);

    expect(subscriptionFor('wallet@example.test')?->user_id)->toBe($user->id);
});

/* Het slot op de service zelf, voor het geval een vijfde pad ooit vergeet te wachten. */
it('refuses to link a subscription to an account that never proved its address', function () {
    MailSubscription::factory()->create(['email' => 'onbewezen@example.test', 'user_id' => null]);

    $user = User::factory()->unverified()->create(['email' => 'onbewezen@example.test']);
    app(MailSubscriptionService::class)->linkToUser($user);

    expect(subscriptionFor('onbewezen@example.test')?->user_id)->toBeNull();
});
