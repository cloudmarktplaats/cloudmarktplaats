<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\User;
use Livewire\Livewire;

it('serves the scope page with the rule, the tests and the edge cases', function () {
    $this->get('/wat-mag-erop')
        ->assertOk()
        ->assertSee('Hoort het in een lab,')
        ->assertSee('Categorietest')
        ->assertSee('Doeltest')
        ->assertSee('Ruistest')
        ->assertSee('Losse desktop-CPU')
        ->assertSee('Spelcomputer of controller')
        ->assertSee('Data eraf, altijd.');
});

it('links the scope page from the footer', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('scope'), false);
});

// De scopelijst hoort te staan waar de twijfel ontstaat: bij het kiezen van
// een categorie in stap 1 van de wizard. Losgekoppeld verliest hij zijn functie.
it('links the scope page from step one of the listing wizard', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    Livewire::test(Wizard::class)
        ->assertSee(route('scope'), false);
});
