<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\DealService;

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

// Zonder deze regel is de claim-link kwijt zodra de verkoper de detailpagina
// sluit: de advertentie staat dan op 'sold' en verdwijnt uit zijn blikveld.
it('flags a sold listing whose buyer has not confirmed yet', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->published()->create();
    app(DealService::class)->markSold($listing, $seller);

    $this->actingAs($seller)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertSee('koper nog niet bevestigd');
});

// "Verkocht melden" checkte de vlag al, deze regel niet — met FEATURE_DEALS=false
// linkte hij naar #deal-panel, dat dan niet meer rendert.
it('does not offer the unconfirmed-buyer link when the deals feature is off', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller)->published()->create();
    app(DealService::class)->markSold($listing, $seller);

    config()->set('cloudmarktplaats.features.deals', false);

    $this->actingAs($seller)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertDontSee('koper nog niet bevestigd');
});
