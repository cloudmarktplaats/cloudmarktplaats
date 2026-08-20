<?php

declare(strict_types=1);

use App\Livewire\RecentListings;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

/*
 * Op 19-08 vulde één verkoper zes van de twaalf kaarten op de voorpagina met
 * vier identieke mini-PC's en twee identieke servers. De voorpagina werd zijn
 * etalage. Deze grens geldt voor iedereen en staat er bewust vóórdat er een
 * bedrijf voor zichtbaarheid kan betalen.
 */
it('lets no single seller take over the front page', function () {
    $hoarder = User::factory()->create();
    foreach (range(1, 6) as $i) {
        Listing::factory()->for($hoarder)->published()->create(['published_at' => now()->subMinutes($i)]);
    }

    $others = User::factory()->count(4)->create();
    foreach ($others as $i => $other) {
        Listing::factory()->for($other)->published()->create(['published_at' => now()->subMinutes(30 + $i)]);
    }

    $listings = Livewire::test(RecentListings::class)->instance()->listings();

    expect($listings)->toHaveCount(6)
        ->and($listings->where('user_id', $hoarder->id))->toHaveCount(2);
});

// Is er nog geen keuze, dan mag de grens het aanbod niet uitdunnen: een lege
// voorpagina is erger dan een eenzijdige.
it('still fills the page when there is only one seller', function () {
    $only = User::factory()->create();
    foreach (range(1, 5) as $i) {
        Listing::factory()->for($only)->published()->create(['published_at' => now()->subMinutes($i)]);
    }

    expect(Livewire::test(RecentListings::class)->instance()->listings())->toHaveCount(2);
});

it('keeps the newest of a seller, not a random pair', function () {
    $seller = User::factory()->create();
    $newest = Listing::factory()->for($seller)->published()->create(['published_at' => now()->subMinute()]);
    $second = Listing::factory()->for($seller)->published()->create(['published_at' => now()->subMinutes(2)]);
    Listing::factory()->for($seller)->published()->create(['published_at' => now()->subMinutes(3)]);

    $ids = Livewire::test(RecentListings::class)->instance()->listings()->pluck('id');

    expect($ids->all())->toBe([$newest->id, $second->id]);
});
