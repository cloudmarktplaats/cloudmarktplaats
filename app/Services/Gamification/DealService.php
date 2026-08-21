<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Exceptions\DealException;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Listings\ListingStateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
            throw new DealException((string) __('Alleen de verkoper kan deze advertentie als verkocht markeren.'));
        }

        return DB::transaction(function () use ($listing, $seller): Transaction {
            /** @var Listing $locked */
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            if ($locked->state !== 'published') {
                throw new DealException((string) __('Alleen een gepubliceerde advertentie kan als verkocht worden gemarkeerd.'));
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
                ...self::freshClaim(),
            ]);
        });
    }

    /**
     * Een verse claim-link. Melden en "nieuwe link" maken er allebei een, en
     * dat moet dezelfde blijven — lengte, alfabet en looptijd horen bij elkaar.
     *
     * @return array{claim_token: string, claim_expires_at: Carbon}
     */
    private static function freshClaim(): array
    {
        return [
            'claim_token' => Str::random(32),
            'claim_expires_at' => now()->addDays(self::CLAIM_DAYS),
        ];
    }

    /**
     * Gemelde verkopen van deze advertentie die nog op een koper wachten.
     *
     * @return Collection<int, Transaction>
     */
    public function openClaims(Listing $listing): Collection
    {
        return Transaction::query()
            ->unclaimed()
            ->where('listing_id', $listing->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * De koper vult zichzelf in en bevestigt, in één handeling.
     *
     * Een tussenstap via /profile/deals zou friction zonder doel zijn: wie de
     * link opent en op "ja" klikt zegt precies wat we willen weten.
     */
    public function claim(string $token, User $buyer): Transaction
    {
        return DB::transaction(function () use ($token, $buyer): Transaction {
            $tx = $this->lockClaimable($token, $buyer);

            $tx->forceFill([
                'buyer_user_id' => $buyer->id,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            return $tx;
        });
    }

    /**
     * "Nee, dit klopt niet." Zonder deze uitweg is een claim-link een
     * eenrichtingsclaim en kan een verkoper er ongestraft mee strooien.
     */
    public function decline(string $token, User $buyer): Transaction
    {
        return DB::transaction(function () use ($token, $buyer): Transaction {
            $tx = $this->lockClaimable($token, $buyer);

            $tx->forceFill(['status' => 'cancelled'])->save();

            return $tx;
        });
    }

    /** Verlopen link? De verkoper maakt een nieuwe, anders zit hij op dag 31 klem. */
    public function refreshClaimToken(Transaction $tx, User $seller): Transaction
    {
        if ($tx->seller_user_id !== $seller->id) {
            throw new DealException((string) __('Alleen de verkoper kan een nieuwe link maken.'));
        }

        return DB::transaction(function () use ($tx): Transaction {
            /** @var Transaction $locked */
            $locked = Transaction::query()->lockForUpdate()->findOrFail($tx->id);

            if ($locked->status !== 'pending') {
                throw new DealException((string) __('Deze deal is al afgehandeld.'));
            }

            $locked->forceFill(self::freshClaim())->save();

            return $locked;
        });
    }

    /**
     * De token blijft na afhandeling staan, zodat een tweede klik "al
     * bevestigd" kan zeggen in plaats van "onbekende link". De status is wat
     * telt, niet het bestaan van de token.
     */
    private function lockClaimable(string $token, User $buyer): Transaction
    {
        $tx = Transaction::query()->lockForUpdate()->where('claim_token', $token)->first();

        if ($tx === null) {
            throw new DealException((string) __('Deze link kennen we niet.'));
        }
        if ($tx->status === 'completed') {
            throw new DealException((string) __('Deze deal is al bevestigd.'));
        }
        if ($tx->status === 'cancelled') {
            throw new DealException((string) __('Deze deal is al afgewezen.'));
        }
        if ($tx->claim_expires_at?->isPast() ?? false) {
            throw new DealException((string) __('Deze link is verlopen. Vraag de verkoper om een nieuwe.'));
        }
        if ($tx->seller_user_id === $buyer->id) {
            throw new DealException((string) __('Je kunt je eigen verkoop niet bevestigen.'));
        }

        return $tx;
    }

    public function confirm(Transaction $tx, User $buyer): void
    {
        DB::transaction(function () use ($tx, $buyer): void {
            /** @var Transaction $locked */
            $locked = Transaction::query()->lockForUpdate()->findOrFail($tx->id);

            if ($locked->buyer_user_id !== $buyer->id) {
                throw new DealException((string) __('Alleen de gemarkeerde koper kan deze deal bevestigen.'));
            }
            if ($locked->status !== 'pending') {
                throw new DealException((string) __('Deze deal is al afgehandeld.'));
            }

            $locked->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        });
    }

    public function confirmedSalesCount(User $seller): int
    {
        return Transaction::query()->confirmedSaleFor($seller->id)->count();
    }
}
