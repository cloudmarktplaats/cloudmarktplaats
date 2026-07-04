<?php

declare(strict_types=1);

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Illuminate\Database\QueryException;

it('marks a listing sold without a buyer tag', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    $tx = app(DealService::class)->markSold($listing, $seller, null);

    expect($tx)->toBeNull()
        ->and($listing->fresh()->state)->toBe('sold');
});

it('marks sold and creates a pending transaction when a buyer is tagged', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 5000]);

    $tx = app(DealService::class)->markSold($listing, $seller, 'koper');

    expect($tx->status)->toBe('pending')
        ->and($tx->buyer_user_id)->toBe($buyer->id)
        ->and($tx->seller_user_id)->toBe($seller->id)
        ->and($tx->amount_cents)->toBe(5000)
        ->and($listing->fresh()->state)->toBe('sold');
});

it('rejects marking someone elses listing, a non-published listing, and self/invalid buyer', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $published = Listing::factory()->published()->for($seller)->create();
    $draft = Listing::factory()->for($seller)->create(['state' => 'draft']);

    expect(fn () => app(DealService::class)->markSold($published, $stranger, null))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->markSold($draft, $seller, null))->toThrow(DealException::class);
    // self as buyer:
    $p2 = Listing::factory()->published()->for($seller)->create();
    expect(fn () => app(DealService::class)->markSold($p2, $seller, $seller->username))->toThrow(DealException::class);
    // unknown buyer:
    $p3 = Listing::factory()->published()->for($seller)->create();
    expect(fn () => app(DealService::class)->markSold($p3, $seller, 'nobody'))->toThrow(DealException::class);
});

it('lets the tagged buyer confirm, exactly once, and counts confirmed sales', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create(['username' => 'koper', 'email_verified_at' => now()]);
    $listing = Listing::factory()->published()->for($seller)->create();
    $tx = app(DealService::class)->markSold($listing, $seller, 'koper');

    app(DealService::class)->confirm($tx, $buyer);

    expect($tx->fresh()->status)->toBe('completed')
        ->and($tx->fresh()->completed_at)->not->toBeNull()
        ->and(app(DealService::class)->confirmedSalesCount($seller))->toBe(1);

    // a stranger cannot confirm; a second confirm is rejected
    expect(fn () => app(DealService::class)->confirm($tx->fresh(), User::factory()->create()))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->confirm($tx->fresh(), $buyer))->toThrow(DealException::class);
});

it('rejects a second markSold on the same listing (sequential proxy for the concurrent race)', function () {
    // The real race is two concurrent markSold calls on one listing each
    // seeing state='published' before either commits. We can't fork two
    // real concurrent transactions in a single-process test, but calling
    // markSold twice in sequence exercises the same guard: the second
    // call's lockForUpdate()->findOrFail() re-reads the row (now 'sold')
    // and must reject it, proving the authoritative check happens under
    // lock rather than on the possibly-stale hydrated $listing.
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    $first = app(DealService::class)->markSold($listing, $seller, null);
    expect($first)->toBeNull()
        ->and($listing->fresh()->state)->toBe('sold');

    expect(fn () => app(DealService::class)->markSold($listing, $seller, null))->toThrow(DealException::class);

    // Only one transaction-worthy state change happened; no duplicate
    // listing state churn occurred as a side effect of the second attempt.
    expect($listing->fresh()->state)->toBe('sold');
});

it('is blocked at the database level from ever storing buyer == seller', function () {
    // Belt-and-braces: even if application-level checks are ever
    // bypassed (a bug, a raw insert, a future code path), the DB CHECK
    // constraint is the last line of defense against a self-sale.
    $u = User::factory()->create();

    expect(fn () => Transaction::factory()->create(['buyer_user_id' => $u->id, 'seller_user_id' => $u->id]))
        ->toThrow(QueryException::class);
});
