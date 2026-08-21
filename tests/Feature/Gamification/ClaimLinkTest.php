<?php

declare(strict_types=1);

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Illuminate\Database\QueryException;

it('stores a sale that has no buyer yet', function () {
    $tx = Transaction::factory()->unclaimed()->create();

    expect($tx->buyer_user_id)->toBeNull()
        ->and($tx->status)->toBe('pending')
        ->and(strlen((string) $tx->claim_token))->toBe(32)
        ->and($tx->claim_expires_at->isFuture())->toBeTrue();
});

it('still refuses buyer == seller at the database level', function () {
    $u = User::factory()->create();

    expect(fn () => Transaction::factory()->create(['buyer_user_id' => $u->id, 'seller_user_id' => $u->id]))
        ->toThrow(QueryException::class);
});

/** Een gemelde verkoop van een verse verkoper, klaar om geclaimd te worden. */
function reportedSale(?User $seller = null): Transaction
{
    $seller ??= User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 4500]);

    return app(DealService::class)->markSold($listing, $seller);
}

it('lets the buyer claim and confirm in one go, and counts it for the seller', function () {
    $tx = reportedSale();
    $buyer = User::factory()->create(['email_verified_at' => now()]);

    $claimed = app(DealService::class)->claim((string) $tx->claim_token, $buyer);

    expect($claimed->status)->toBe('completed')
        ->and($claimed->buyer_user_id)->toBe($buyer->id)
        ->and($claimed->completed_at)->not->toBeNull()
        ->and(app(DealService::class)->confirmedSalesCount($claimed->seller))->toBe(1);
});

it('does not count an unclaimed sale towards the seller', function () {
    $tx = reportedSale();

    expect(app(DealService::class)->confirmedSalesCount($tx->seller))->toBe(0);
});

it('refuses a second claim on the same token', function () {
    $tx = reportedSale();
    app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect(fn () => app(DealService::class)->claim((string) $tx->claim_token, User::factory()->create()))
        ->toThrow(DealException::class, 'Deze deal is al bevestigd.');
});

it('refuses an unknown, an expired and a self-claimed token', function () {
    expect(fn () => app(DealService::class)->claim('nope', User::factory()->create()))
        ->toThrow(DealException::class, 'Deze link kennen we niet.');

    $seller = User::factory()->create();
    $expired = reportedSale($seller);
    $expired->forceFill(['claim_expires_at' => now()->subDay()])->save();
    expect(fn () => app(DealService::class)->claim((string) $expired->claim_token, User::factory()->create()))
        ->toThrow(DealException::class, 'Deze link is verlopen. Vraag de verkoper om een nieuwe.');

    $own = reportedSale($seller);
    expect(fn () => app(DealService::class)->claim((string) $own->claim_token, $seller))
        ->toThrow(DealException::class, 'Je kunt je eigen verkoop niet bevestigen.');
});

it('cancels the deal when the buyer says it was not them', function () {
    $tx = reportedSale();
    $listing = $tx->listing;

    $declined = app(DealService::class)->decline((string) $tx->claim_token, User::factory()->create(['email_verified_at' => now()]));

    expect($declined->status)->toBe('cancelled')
        ->and($declined->buyer_user_id)->toBeNull()
        // Weigeren zet de advertentie niet terug op published: of er nog iets
        // te koop staat bepaalt de verkoper, niet de koper die zegt dat hij
        // het niet was.
        ->and($listing?->fresh()->state)->toBe('sold');
});

it('gives the seller a fresh link when the old one expired', function () {
    $seller = User::factory()->create();
    $tx = reportedSale($seller);
    $old = (string) $tx->claim_token;
    $tx->forceFill(['claim_expires_at' => now()->subDay()])->save();

    $refreshed = app(DealService::class)->refreshClaimToken($tx, $seller);

    expect($refreshed->claim_token)->not->toBe($old)
        ->and($refreshed->claim_expires_at->isFuture())->toBeTrue();

    $buyer = User::factory()->create(['email_verified_at' => now()]);
    expect(app(DealService::class)->claim((string) $refreshed->claim_token, $buyer)->status)->toBe('completed');
});

it('does not let a stranger refresh someone elses link', function () {
    $tx = reportedSale();

    expect(fn () => app(DealService::class)->refreshClaimToken($tx, User::factory()->create()))
        ->toThrow(DealException::class, 'Alleen de verkoper kan een nieuwe link maken.');
});
