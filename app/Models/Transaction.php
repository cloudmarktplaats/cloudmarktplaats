<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'listing_id',
        'buyer_user_id',
        'seller_user_id',
        'amount_cents',
        'currency',
        'status',
        'completed_at',
        'off_platform',
        'external_tx_ref',
        'claim_token',
        'claim_expires_at',
    ];

    /**
     * @return array{off_platform: 'boolean', completed_at: 'datetime', claim_expires_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'off_platform' => 'boolean',
            'completed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * De koper. Null zolang de verkoop nog niet geclaimd is — melden legt
     * de deal vast, bevestigen vult de koper in.
     *
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * Confirmed (buyer-completed) sales for a seller, excluding any sale
     * whose buyer has since been banned — a sockpuppet ring cannot farm
     * trust by having its own banned accounts still count as sales.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeConfirmedSaleFor(Builder $query, int $sellerUserId): Builder
    {
        return $query
            ->where('seller_user_id', $sellerUserId)
            ->where('status', 'completed')
            ->whereHas('buyer', fn ($q) => $q->where('is_banned', false));
    }

    /**
     * Gemelde verkopen die nog op een koper wachten.
     *
     * Wat een openstaande claim is, staat hier en nergens anders: het paneel
     * op de advertentie en de markering op Mijn advertenties leunen er allebei
     * op. Schreven ze het los uit, dan kon de markering blijven staan terwijl
     * het paneel de claim al niet meer als open zag.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->where('status', 'pending')->whereNull('buyer_user_id');
    }
}
