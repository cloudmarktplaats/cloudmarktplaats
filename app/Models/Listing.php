<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'condition',
        'price_cents',
        'currency',
        'is_trade_allowed',
        'region_postcode',
        'shipping_options',
        'state',
        'published_at',
        'sold_at',
        'moderation_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_options' => 'array',
            'is_trade_allowed' => 'boolean',
            'published_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ListingPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('position');
    }

    protected static function booted(): void
    {
        static::creating(function (self $l): void {
            $l->ulid = $l->ulid ?? (string) Str::ulid();
            $l->slug = $l->slug ?: Str::slug($l->title).'-'.substr((string) $l->ulid, -6);
        });
    }
}
