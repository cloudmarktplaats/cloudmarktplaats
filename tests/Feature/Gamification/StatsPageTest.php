<?php

declare(strict_types=1);

use App\Livewire\Profile\Stats;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('shows the user their own stats and earned badges', function () {
    $user = User::factory()->create();
    Listing::factory()->sold()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Stats::class)
        ->assertOk()
        ->assertSee('Eerste verkoop'); // a derived badge
});

it('only reflects the authenticated user (no other user data leaks)', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Listing::factory()->sold()->for($other)->count(12)->create();

    // $me has zero activity — if $other's sold listings leaked into
    // $me's stats, $me would see the sale/trader badges. They must not.
    Livewire::actingAs($me)
        ->test(Stats::class)
        ->assertOk()
        ->assertDontSee('Eerste verkoop')
        ->assertDontSee('Handelaar')
        ->assertSee('Nog geen badges');
});

it('404s when the stats feature is off', function () {
    config()->set('cloudmarktplaats.features.stats', false);

    Livewire::actingAs(User::factory()->create())->test(Stats::class)->assertStatus(404);
});
