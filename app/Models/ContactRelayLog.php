<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only abuse/rate-metrics record for the seller-contact relay.
 *
 * Stores listing_id + created_at only. No PII — never add buyer email,
 * message body, or IP here; the relay's whole point is not to archive
 * messages (privacy statement). There is no updated_at.
 */
class ContactRelayLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['listing_id'];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
