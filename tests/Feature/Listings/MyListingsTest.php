<?php

declare(strict_types=1);

use App\Livewire\Listings\Mine;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication to view my listings', function () {
    $this->get(route('listings.mine'))->assertRedirect(route('login'));
});

it('shows only the current users own listings', function () {
    $me = User::factory()->create();
    Listing::factory()->for($me)->published()->create(['title' => 'Mijn ThinkPad']);
    Listing::factory()->published()->create(['title' => 'Andermans Sun']);

    Livewire::actingAs($me)->test(Mine::class)
        ->assertSee('Mijn ThinkPad')
        ->assertDontSee('Andermans Sun');
});

it('includes listings in every state, not just published', function () {
    $me = User::factory()->create();
    Listing::factory()->for($me)->create(['state' => 'draft', 'title' => 'Concept-advertentie']);
    Listing::factory()->for($me)->create(['state' => 'pending_review', 'title' => 'Wachtende advertentie']);
    Listing::factory()->for($me)->published()->create(['title' => 'Live advertentie']);
    Listing::factory()->for($me)->sold()->create(['title' => 'Verkochte advertentie']);

    Livewire::actingAs($me)->test(Mine::class)
        ->assertSee('Concept-advertentie')
        ->assertSee('Wachtende advertentie')
        ->assertSee('Live advertentie')
        ->assertSee('Verkochte advertentie');
});

it('links each listing to its edit form', function () {
    $me = User::factory()->create();
    $listing = Listing::factory()->for($me)->create(['state' => 'draft']);

    Livewire::actingAs($me)->test(Mine::class)
        ->assertSee(route('listings.edit', $listing), false);
});

it('shows an empty state with a create CTA when the user has no listings', function () {
    $me = User::factory()->create();

    Livewire::actingAs($me)->test(Mine::class)
        ->assertSee(route('listings.create'), false);
});
