<?php

declare(strict_types=1);

use App\Livewire\Deals\Claim;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

function saleWithToken(string $title = 'Dell R720'): string
{
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['title' => $title]);

    return (string) app(DealService::class)->markSold($listing, $seller)->claim_token;
}

it('shows a verified buyer what they are confirming, and confirms it', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->assertSee('Dell R720')
        ->assertSee('Het is geen betaling en geen verplichting.')
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('done', 'confirmed');
});

it('lets the buyer decline', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->call('decline')
        ->assertSet('done', 'declined');
});

/*
 * Declining is a one-way door: refreshClaimToken() and markSold() both refuse
 * once the transaction has left 'pending', and no screen surfaces a
 * cancelled row. Unlike markSold on the detail page, which already asks for
 * confirmation, this button fired the moment it was clicked — a missed tap
 * on a phone would permanently kill a real sale with no way back.
 */
it('asks the buyer to confirm before declining, same as markSold does', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->assertSeeHtml('wire:confirm');
});

it('parks the url for a guest so login brings them back here', function () {
    $token = saleWithToken();

    $this->get("/deal/{$token}")
        ->assertOk()
        ->assertSee('Inloggen');

    expect(session('url.intended'))->toBe(route('deals.claim', ['token' => $token]));
});

/*
 * The button used to promise "Inloggen of registreren" but only linked to
 * /login, which has no way out to /register — a buyer without an account who
 * opens the link from the seller's mail hit a dead end. The marketing layout
 * always carries a header/footer link to /register, so this asserts on text
 * specific to the guest block on this page, not just the URL being present
 * somewhere in the document.
 */
it('offers a guest both a login and a register link', function () {
    $token = saleWithToken();

    $this->get("/deal/{$token}")
        ->assertOk()
        ->assertSee('Inloggen')
        ->assertSee('Registreer je')
        ->assertSeeInOrder(['Inloggen', 'Nog geen account?', 'Registreer je']);
});

it('refuses to confirm on an unverified account', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => null]);

    Livewire::actingAs($buyer)
        ->test(Claim::class, ['token' => $token])
        ->call('confirm')
        ->assertForbidden();
});

it('explains an unknown link instead of 404ing', function () {
    $this->get('/deal/'.str_repeat('x', 32))
        ->assertOk()
        ->assertSee('Deze link kennen we niet');
});

it('404s when the deals feature is off', function () {
    config()->set('cloudmarktplaats.features.deals', false);

    $this->get('/deal/'.saleWithToken())->assertNotFound();
});

it('refuses to confirm once the flag is switched off after the page was already open', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    $component = Livewire::actingAs($buyer)->test(Claim::class, ['token' => $token]);

    config()->set('cloudmarktplaats.features.deals', false);

    $component->call('confirm')->assertForbidden();

    expect(Transaction::query()->where('claim_token', $token)->first()->status)->toBe('pending');
});

it('parks the url for a logged-in but unverified buyer too', function () {
    $token = saleWithToken();
    $buyer = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($buyer)->get("/deal/{$token}")->assertOk();

    expect(session('url.intended'))->toBe(route('deals.claim', ['token' => $token]));
});

/*
 * The claim sentence splices the listing title straight from user input.
 * A seller controls that title, and the buyer on the other end of the link
 * has no reason to expect it might carry markup — it must never render as
 * HTML, only as literal text.
 */
it('escapes a listing title that contains HTML on the claim page', function () {
    $token = saleWithToken('<script>alert(1)</script>');

    $this->get("/deal/{$token}")
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<script>alert(1)</script>');
});
