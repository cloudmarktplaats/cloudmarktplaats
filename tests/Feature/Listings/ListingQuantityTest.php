<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\DealService;
use Livewire\Livewire;

/*
 * Een verkoper met vier identieke ThinkCentres liep de wizard vier keer door,
 * en zette "(2x)" in de omschrijving van zijn servers: hij had het veld zelf al
 * bedacht, het bestond alleen niet. Zes van de twaalf kaarten op de eerste
 * pagina van het aanbod waren daardoor van één verkoper.
 */
it('defaults to one and stores what the seller typed', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $category = Category::factory()->create();

    $this->actingAs($user);

    Livewire::test(Wizard::class)
        ->assertSet('quantity', 1)
        ->set('title', 'Lenovo ThinkCentre M710q')
        ->set('category_id', $category->id)
        ->set('condition', 'used')
        ->set('price_cents', 10000)
        ->set('quantity', 4)
        ->call('next');

    expect(Listing::query()->where('user_id', $user->id)->firstOrFail()->quantity)->toBe(4);
});

it('refuses a quantity below one', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $category = Category::factory()->create();

    $this->actingAs($user);

    Livewire::test(Wizard::class)
        ->set('title', 'Lenovo ThinkCentre M710q')
        ->set('category_id', $category->id)
        ->set('condition', 'used')
        ->set('price_cents', 10000)
        ->set('quantity', 0)
        ->call('next')
        ->assertHasErrors('quantity');
});

// Eén exemplaar verkopen is niet hetzelfde als de advertentie sluiten.
it('takes one off the pile and keeps the listing up', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->published()->create(['quantity' => 3]);

    app(DealService::class)->markSold($listing, $seller);

    $listing->refresh();
    expect($listing->quantity)->toBe(2)
        ->and($listing->state)->toBe('published');
});

it('closes the listing when the last one goes', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->published()->create(['quantity' => 1]);

    app(DealService::class)->markSold($listing, $seller);

    expect($listing->refresh()->state)->toBe('sold');
});

it('shows the count in the listing overview only when there is more than one', function () {
    $one = Listing::factory()->published()->create(['quantity' => 1]);
    $many = Listing::factory()->published()->create(['quantity' => 5]);

    $html = $this->get('/listings')->assertOk()->getContent();

    expect($html)->toContain('5 stuks')
        ->and($html)->not->toContain('1 stuk<');
});
