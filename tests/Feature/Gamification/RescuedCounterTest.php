<?php

declare(strict_types=1);

use App\Livewire\RescuedCounter;
use App\Models\Listing;
use Livewire\Livewire;

beforeEach(function () {
    // Livewire::test() mounts the component directly and never runs the
    // 'web' middleware group, so SetLocale never fires; force Dutch (the
    // default for real requests) so the component's translated strings
    // match what visitors actually see.
    app()->setLocale('nl');
});

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
