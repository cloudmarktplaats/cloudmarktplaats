<?php

declare(strict_types=1);

use App\Jobs\Listings\IncrementViewJob;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Wipe the Redis test DB so the view-count SETNX guard starts
    // clean — otherwise prior tests' (listing_id, ip_hash) pairs
    // count as duplicates and our increments are no-ops.
    Redis::flushdb();
});

it('authed user can file a report; row carries reporter id', function () {
    $listing = Listing::factory()->published()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/reports/listing/{$listing->id}", [
            'reason' => 'illegal',
            'details' => 'Looks like a stolen Mac.',
        ])
        ->assertRedirect();

    $report = Report::query()->where('reportable_id', $listing->id)->firstOrFail();
    expect($report->reportable_type)->toBe('listing')
        ->and($report->reporter_user_id)->toBe($user->id)
        ->and($report->reason)->toBe('illegal')
        ->and($report->details)->toBe('Looks like a stolen Mac.')
        ->and($report->status)->toBe('open');
});

it('reports require auth', function () {
    $listing = Listing::factory()->published()->create();

    $this->post("/reports/listing/{$listing->id}", ['reason' => 'spam'])
        ->assertRedirect('/login');
});

it('IncrementViewJob increments once per (listing, ip_hash) per hour', function () {
    $listing = Listing::factory()->published()->create(['view_count' => 0]);
    $ipHash = hash('sha256', '1.2.3.4');

    (new IncrementViewJob($listing->id, $ipHash))->handle();
    (new IncrementViewJob($listing->id, $ipHash))->handle();
    (new IncrementViewJob($listing->id, $ipHash))->handle();

    expect($listing->fresh()->view_count)->toBe(1);

    // Different IP → counts again.
    (new IncrementViewJob($listing->id, hash('sha256', '5.6.7.8')))->handle();

    expect($listing->fresh()->view_count)->toBe(2);
});
