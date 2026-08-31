<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MailSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén inschrijving op de mailinglijst, gesleuteld op e-mailadres.
 *
 * `user_id` mag leeg zijn: je hoeft geen account te hebben om op de lijst te
 * staan. Dat lege veld is meteen de segmentatie tussen wel en geen account.
 *
 * Bewaar hier nooit een IP-adres. Dit platform wist IP's na 24 uur en dat is
 * een architectuurbelofte; het bewijs van toestemming bestaat uit
 * `consent_text`, `consent_given_at` en de bevestigingsklik in `confirmed_at`.
 *
 * Invariant: een rij kan tegelijk bevestigd zijn (`confirmed_at` gezet) én een
 * levend `confirm_token` dragen. Dat is geen inconsistentie maar het geval
 * waarin er een wijziging in `pending_changes` geparkeerd staat: de eigenaar
 * heeft nog niet op die bevestigingslink geklikt. Alleen `confirmed_at` is
 * dus gezaghebbend voor de vraag of iemand mail mag krijgen; lees
 * `confirm_token !== null` nooit als "nog niet bevestigd".
 */
class MailSubscription extends Model
{
    /** @use HasFactory<MailSubscriptionFactory> */
    use HasFactory;

    /**
     * `pending_changes` staat er bewust niet bij: dat vak vult de service zelf
     * met `forceFill`, en het mag nooit rechtstreeks uit een request komen.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email', 'user_id', 'wants_offers', 'wants_updates', 'categories',
        'confirm_token', 'confirmed_at', 'unsubscribe_token',
        'consent_text', 'consent_given_at', 'consent_source',
        'offers_sent_at', 'updates_sent_at', 'unsubscribed_at',
    ];

    /**
     * Larastan leest de Laravel-11-vorm niet, vandaar de expliciete shape.
     *
     * @return array{categories: 'array', pending_changes: 'array', wants_offers: 'boolean', wants_updates: 'boolean', confirmed_at: 'datetime', consent_given_at: 'datetime', offers_sent_at: 'datetime', updates_sent_at: 'datetime', unsubscribed_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'pending_changes' => 'array',
            'wants_offers' => 'boolean',
            'wants_updates' => 'boolean',
            'confirmed_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'offers_sent_at' => 'datetime',
            'updates_sent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<MailSubscription> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->whereNotNull('confirmed_at');
    }
}
