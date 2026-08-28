<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use Livewire\Livewire;

/**
 * Een post zonder foto hoort nergens op een lijst te komen.
 *
 * Het formulier laat er geen ontstaan, maar een halve migratie of een handmatig
 * opgeruimde foto wel. `photoUrl()` gooit dan bewust — alleen gebeurde dat
 * tijdens het renderen van de lijst, dus 1 zo'n post gaf een 500 op de hele
 * voorpagina en de hele feed. Gevonden op 28-08 op de ontwikkelmachine; op
 * productie stonden er op dat moment nul, dus het is nooit publiek misgegaan.
 */
it('keeps the homepage up when a post has no photo', function () {
    HomelabPost::factory()->create(['body' => 'post zonder foto']);
    HomelabPost::factory()->withPhoto()->create(['body' => 'post met foto']);

    $this->get('/')
        ->assertOk()
        ->assertSee('post met foto')
        ->assertDontSee('post zonder foto');
});

it('keeps the feed up when a post has no photo', function () {
    HomelabPost::factory()->create(['body' => 'post zonder foto']);
    HomelabPost::factory()->withPhoto()->create(['body' => 'post met foto']);

    $this->get('/homelabs')
        ->assertOk()
        ->assertSee('post met foto')
        ->assertDontSee('post zonder foto');
});

it('does not offer to load more when the rest has no photos', function () {
    HomelabPost::factory()->withPhoto()->create();
    HomelabPost::factory()->count(5)->create();

    // Zou "meer laden" op het totaal tellen in plaats van op wat te tonen is,
    // dan bleef die knop hier staan en leverde hij bij elke klik hetzelfde.
    Livewire::test(Feed::class)
        ->assertViewHas('hasMore', false);
});
