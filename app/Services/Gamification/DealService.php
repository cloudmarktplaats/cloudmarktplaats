<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DealService
{
    public function __construct(private readonly ListingStateService $state) {}

    /** Hoeveel dagen een claim-link bruikbaar blijft. */
    public const CLAIM_DAYS = 30;

    /**
     * Meld een verkoop. Levert altijd een transactie op, ook zonder koper.
     *
     * De verkoper kán de koper niet aanwijzen: de contact-relay is anoniem en
     * geeft hem alleen een e-mailadres. Daarom legt melden de verkoop vast met
     * een claim-token, en vult de koper zichzelf later in via die link.
     */
    public function markSold(Listing $listing, User $seller): Transaction
    {
        if ($seller->id !== $listing->user_id) {
            throw new DealException('Alleen de verkoper kan deze advertentie als verkocht markeren.');
        }

        return DB::transaction(function () use ($listing, $seller): Transaction {
            /** @var Listing $locked */
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->state !== 'published') {
                throw new DealException('Alleen een gepubliceerde advertentie kan als verkocht worden gemarkeerd.');
            }

            // Eén exemplaar verkopen is niet hetzelfde als de advertentie
            // sluiten. Staan er nog meer, dan gaat er eentje af en blijft hij
            // gewoon staan; pas bij de laatste gaat hij op `sold`.
            if ($locked->quantity > 1) {
                $locked->decrement('quantity');
            } else {
                $this->state->transition($locked, 'sold');
            }

            return Transaction::query()->create([
                'listing_id' => $locked->id,
                'seller_user_id' => $seller->id,
                'buyer_user_id' => null,
                'amount_cents' => $locked->price_cents,
                'currency' => 'EUR',
                'status' => 'pending',
                'off_platform' => true,
                'claim_token' => Str::random(32),
                'claim_expires_at' => now()->addDays(self::CLAIM_DAYS),
            ]);
        });
    }

    /**
     * Gemelde verkopen van deze advertentie die nog op een koper wachten.
     *
     * @return Collection<int, Transaction>
     */
    public function openClaims(Listing $listing): Collection
    {
        return Transaction::query()
            ->where('listing_id', $listing->id)
            ->where('status', 'pending')
            ->whereNull('buyer_user_id')
            ->orderBy('id')
            ->get();
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
        return Transaction::query()->confirmedSaleFor($seller->id)->count();
    }
}
