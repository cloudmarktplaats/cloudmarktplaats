<?php

use App\Models\AdminAction;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates listing photos with ordering', function () {
    $listing = Listing::factory()->create();
    ListingPhoto::factory()->for($listing)->create(['position' => 1]);
    ListingPhoto::factory()->for($listing)->create(['position' => 2]);

    expect($listing->photos)->toHaveCount(2);
});

it('creates a report on a listing', function () {
    $listing = Listing::factory()->create();
    $r = Report::create([
        'reportable_type' => Listing::class,
        'reportable_id' => $listing->id,
        'reason' => 'spam',
        'details' => 'looks fake',
        'status' => 'open',
    ]);

    expect($r->reportable->is($listing))->toBeTrue();
});

it('creates a transaction stub', function () {
    $listing = Listing::factory()->create();
    $buyer = User::factory()->create();
    Transaction::create([
        'listing_id' => $listing->id,
        'buyer_user_id' => $buyer->id,
        'seller_user_id' => $listing->user_id,
        'amount_cents' => 50_00,
        'currency' => 'EUR',
        'status' => 'pending',
        'off_platform' => true,
    ]);

    expect(Transaction::count())->toBe(1);
});

it('logs an admin action', function () {
    $admin = User::factory()->admin()->create();
    AdminAction::create([
        'user_id' => $admin->id,
        'action' => 'listing.reject',
        'target_type' => Listing::class,
        'target_id' => 1,
        'meta' => ['reason' => 'duplicate'],
        'ip_hash' => str_repeat('a', 64),
    ]);

    expect(AdminAction::count())->toBe(1);
});
