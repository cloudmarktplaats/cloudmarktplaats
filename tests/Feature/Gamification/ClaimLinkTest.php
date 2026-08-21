<?php

declare(strict_types=1);

use App\Models\Transaction;
use App\Models\User;
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
