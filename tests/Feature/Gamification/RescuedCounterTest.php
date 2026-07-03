<?php

declare(strict_types=1);

use App\Livewire\RescuedCounter;
use App\Models\Listing;
use Livewire\Livewire;

it('shows the cooperative rescued count', function () {
    Listing::factory()->sold()->count(4)->create();

    Livewire::test(RescuedCounter::class)
        ->assertSee('4')
        ->assertSee('gered');
});

it('renders nothing when there are no sold listings', function () {
    Livewire::test(RescuedCounter::class)->assertDontSee('gered');
});

it('renders nothing when the stats feature is off', function () {
    config()->set('cloudmarktplaats.features.stats', false);
    Listing::factory()->sold()->create();

    Livewire::test(RescuedCounter::class)->assertDontSee('gered');
});
