<?php

declare(strict_types=1);

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;

it('records every reported sale, buyer or no buyer', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['price_cents' => 5000]);

    $tx = app(DealService::class)->markSold($listing, $seller);

    expect($tx->status)->toBe('pending')
        ->and($tx->buyer_user_id)->toBeNull()
        ->and($tx->seller_user_id)->toBe($seller->id)
        ->and($tx->amount_cents)->toBe(5000)
        ->and($tx->claim_token)->not->toBeNull()
        ->and($listing->fresh()->state)->toBe('sold');
});

it('rejects marking someone elses listing or a non-published one', function () {
    $seller = User::factory()->create();
    $stranger = User::factory()->create();
    $published = Listing::factory()->published()->for($seller)->create();
    $draft = Listing::factory()->for($seller)->create(['state' => 'draft']);

    expect(fn () => app(DealService::class)->markSold($published, $stranger))->toThrow(DealException::class);
    expect(fn () => app(DealService::class)->markSold($draft, $seller))->toThrow(DealException::class);
});

it('counts down quantity instead of closing the listing, and hands out one link per unit', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create(['quantity' => 2]);

    $first = app(DealService::class)->markSold($listing, $seller);
    expect($listing->fresh()->state)->toBe('published')
        ->and($listing->fresh()->quantity)->toBe(1);

    $second = app(DealService::class)->markSold($listing->fresh(), $seller);
    expect($listing->fresh()->state)->toBe('sold')
        ->and($first->claim_token)->not->toBe($second->claim_token)
        ->and(app(DealService::class)->openClaims($listing)->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('rejects a second markSold on a sold listing (sequential proxy for the concurrent race)', function () {
    // De echte race is twee gelijktijdige markSold-aanroepen die allebei
    // state='published' zien voordat een van beide commit. Twee keer achter
    // elkaar aanroepen raakt dezelfde bewaking: de tweede aanroep leest de rij
    // opnieuw onder lockForUpdate() (nu 'sold') en moet hem weigeren. Dat
    // bewijst dat de controle onder lock gebeurt en niet op het mogelijk
    // verouderde $listing dat de aanroeper meegaf.
    $seller = User::factory()->create();
    $listing = Listing::factory()->published()->for($seller)->create();

    app(DealService::class)->markSold($listing, $seller);

    expect(fn () => app(DealService::class)->markSold($listing, $seller))->toThrow(DealException::class);
    expect($listing->fresh()->state)->toBe('sold')
        ->and(Transaction::query()->count())->toBe(1);
});
