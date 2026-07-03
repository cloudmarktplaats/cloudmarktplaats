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

it('only reflects the authenticated user (no other user data)', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Listing::factory()->sold()->for($other)->count(5)->create();

    Livewire::actingAs($me)
        ->test(Stats::class)
        ->assertOk()
        ->assertDontSee('Handelaar'); // 'other' would have it; I must not
});

it('404s when the stats feature is off', function () {
    config()->set('cloudmarktplaats.features.stats', false);

    Livewire::actingAs(User::factory()->create())->test(Stats::class)->assertStatus(404);
});
