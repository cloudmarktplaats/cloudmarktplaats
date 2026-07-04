<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\DB;

class DealService
{
    public function __construct(private readonly ListingStateService $state) {}

    public function markSold(Listing $listing, User $seller, ?string $buyerUsername = null): ?Transaction
    {
        if ($seller->id !== $listing->user_id) {
            throw new DealException('Alleen de verkoper kan deze advertentie als verkocht markeren.');
        }
        if ($listing->state !== 'published') {
            throw new DealException('Alleen een gepubliceerde advertentie kan als verkocht worden gemarkeerd.');
        }

        return DB::transaction(function () use ($listing, $seller, $buyerUsername): ?Transaction {
            $buyer = null;
            if (is_string($buyerUsername) && trim($buyerUsername) !== '') {
                $buyer = User::query()->where('username', strtolower(trim($buyerUsername)))->first();
                if ($buyer === null || $buyer->email_verified_at === null) {
                    throw new DealException('Onbekende of niet-geverifieerde koper.');
                }
                if ($buyer->id === $seller->id) {
                    throw new DealException('Je kunt jezelf niet als koper opgeven.');
                }
            }

            $this->state->transition($listing, 'sold');

            if ($buyer === null) {
                return null;
            }

            return Transaction::query()->create([
                'listing_id' => $listing->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => $buyer->id,
                'amount_cents' => $listing->price_cents,
                'currency' => 'EUR',
                'status' => 'pending',
                'off_platform' => true,
            ]);
        });
    }

    public function confirm(Transaction $tx, User $buyer): void
    {
        DB::transaction(function () use ($tx, $buyer): void {
            /** @var Transaction $locked */
            $locked = Transaction::query()->lockForUpdate()->findOrFail($tx->id);

            if ($locked->buyer_user_id !== $buyer->id) {
                throw new DealException('Alleen de gemarkeerde koper kan deze deal bevestigen.');
            }
            if ($locked->status !== 'pending') {
                throw new DealException('Deze deal is al afgehandeld.');
            }

            $locked->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });
    }

    public function confirmedSalesCount(User $seller): int
    {
        return Transaction::query()
            ->where('seller_user_id', $seller->id)
            ->where('status', 'completed')
            ->count();
    }
}
