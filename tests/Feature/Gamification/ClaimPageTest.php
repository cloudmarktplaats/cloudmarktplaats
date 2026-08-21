<?php

declare(strict_types=1);

use App\Livewire\Deals\Claim;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

function saleWithToken(): string
{
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['title' => 'Dell R720']);

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

it('parks the url for a guest so login brings them back here', function () {
    $token = saleWithToken();

    $this->get("/deal/{$token}")
        ->assertOk()
        ->assertSee('Inloggen of registreren');

    expect(session('url.intended'))->toBe(route('deals.claim', ['token' => $token]));
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

it('explains an unknown link instead of 404ing for a truncated token', function () {
    $this->get('/deal/'.str_repeat('x', 20))
        ->assertOk()
        ->assertSee('Deze link kennen we niet');
});
