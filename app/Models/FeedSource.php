<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 nieuwsbron waarvan de server periodiek de feed ophaalt en cachet.
 *
 * `last_error`/`last_fetched_at` maken een stille storing zichtbaar: een bron
 * die niet meer binnenkomt hoort in beeld, niet stil weg te vallen.
 */
class FeedSource extends Model
{
    /** @use HasFactory<FeedSourceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'feed_url', 'homepage_url',
        'is_active', 'sort', 'last_fetched_at', 'last_error', 'last_error_at',
    ];

    /**
     * @return array{is_active: 'boolean', last_fetched_at: 'datetime', last_error_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /** @return HasMany<FeedItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FeedItem::class);
    }

    /**
     * @param  Builder<FeedSource>  $query
     * @return Builder<FeedSource>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }
}
