<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\DealService;
use App\Services\Ops\IntegrityReport;

/*
 * `deals_bevestigd` telde `status = 'confirmed'`, een waarde die de enum
 * (pending|completed|cancelled) niet kent. Het cijfer stond dus structureel op
 * nul en zou daar ook zijn blijven staan als de claim-link perfect werkte.
 */
it('counts a reported sale and a confirmed one', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();
    $tx = app(DealService::class)->markSold($listing, $seller);

    $rapport = app(IntegrityReport::class)->build(now());
    expect($rapport['cijfers']['verkopen_gemeld'])->toBe(1)
        ->and($rapport['cijfers']['deals_bevestigd'])->toBe(0);

    app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect(app(IntegrityReport::class)->build(now())['cijfers']['deals_bevestigd'])->toBe(1);
});
