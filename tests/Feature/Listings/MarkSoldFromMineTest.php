<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;

/*
 * "Markeer als verkocht" woonde alleen op de publieke advertentiepagina, terwijl
 * de verkoper zijn spullen beheert op Mijn advertenties. Meting 19-08: 14
 * contactverzoeken over 10 advertenties, 0 bevestigde deals — de knop stond
 * gewoon niet waar de verkoper stond.
 */
it('offers to mark a published listing sold straight from Mijn advertenties', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->published()->create();

    $this->actingAs($user)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertSee('Verkocht melden')
        ->assertSee('#deal-panel', false);
});

it('does not offer it for a draft — there is nothing to sell yet', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['state' => 'draft']);

    $this->actingAs($user)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertDontSee('Verkocht melden');
});

// De knop linkt naar het paneel op de detailpagina; zonder dat anker landt de
// verkoper bovenaan een lange pagina en is hij alsnog kwijt waar hij moet zijn.
it('anchors at the deal panel on the listing page', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->published()->create();

    $this->actingAs($user)
        ->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('id="deal-panel"', false);
});
