<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Transaction;
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

/*
 * Een claim-link is 30 dagen geldig (DealService::CLAIM_DAYS), ver ruimer
 * dan `silence_days` (7). Een tien dagen oude, nog niet geclaimde verkoop
 * is dus volstrekt normaal en mag geen signaal geven.
 */
it('does not signal a reported sale whose claim link is still valid', function () {
    Transaction::factory()->unclaimed()->create(['created_at' => now()->subDays(10)]);

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->contains(fn ($signaal) => str_contains($signaal, 'koper') || str_contains($signaal, 'claim')))->toBeFalse();
});

it('signals a reported sale whose claim link has expired unused', function () {
    Transaction::factory()->unclaimed()->create([
        'created_at' => now()->subDay(),
        'claim_expires_at' => now()->subDay(),
    ]);

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->contains(fn ($signaal) => str_contains($signaal, 'claim')))->toBeTrue();
});

it('still signals a legacy pending sale without a claim link that has gone quiet', function () {
    Transaction::factory()->create([
        'status' => 'pending',
        'claim_token' => null,
        'claim_expires_at' => null,
        'created_at' => now()->subDays(10),
    ]);

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->contains(fn ($signaal) => str_contains($signaal, 'koper') || str_contains($signaal, 'claim')))->toBeTrue();
});

/*
 * Declining is a one-way door: the listing stays 'sold', markSold() and
 * refreshClaimToken() both refuse, and no screen surfaces a cancelled row.
 * This signal is the only place a decline becomes visible to anyone.
 */
it('signals a deal the buyer declined in the last 24 hours', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();
    $tx = app(DealService::class)->markSold($listing, $seller);
    app(DealService::class)->decline((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->contains(fn ($signaal) => str_contains($signaal, 'geweigerd')))->toBeTrue();
});

it('does not signal a decline from outside the 24-hour window', function () {
    Transaction::factory()->create([
        'status' => 'cancelled',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->contains(fn ($signaal) => str_contains($signaal, 'geweigerd')))->toBeFalse();
});

/*
 * Het geval dat op 02-09-2026 het verkeerde advies kreeg. Een deal waar al een
 * koper aan hangt maar die nooit bevestigd is, kan de verkoper niet oplossen:
 * `openClaims()` gebruikt `unclaimed()`, en die filtert op rijen zónder koper.
 * De verkoper ziet zo'n deal dus nergens. Alleen de koper kan hem afmaken, op
 * /profile/deals, en dat moet het signaal dan ook zeggen.
 */
it('tells the buyer, not the seller, when a deal already has a buyer but was never confirmed', function () {
    $koper = User::factory()->create();
    $verkoper = User::factory()->create();
    $listing = Listing::factory()->for($verkoper, 'user')->create(['state' => 'sold']);

    Transaction::factory()->create([
        'listing_id' => $listing->id,
        'seller_user_id' => $verkoper->id,
        'buyer_user_id' => $koper->id,
        'status' => 'pending',
        'claim_token' => null,
        'claim_expires_at' => null,
        'created_at' => now()->subDays(20),
    ]);

    $signalen = app(IntegrityReport::class)->build(now())['signalen'];

    expect(collect($signalen)->filter(fn ($s) => str_contains($s, '/profile/deals')))->toHaveCount(1)
        ->and(collect($signalen)->filter(fn ($s) => str_contains($s, 'de verkoper kan')))->toBeEmpty();
});
