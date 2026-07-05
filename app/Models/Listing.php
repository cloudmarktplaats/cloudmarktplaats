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

    /**
     * Dutch label for the `condition` enum, shown on cards and detail.
     */
    public function conditionLabel(): string
    {
        return (string) match ($this->condition) {
            'new' => __('Nieuw'),
            'used' => __('Gebruikt'),
            'defective' => __('Defect'),
            'for_parts' => __('Voor onderdelen'),
        };
    }

    /**
     * House-style colour token for the condition badge. Returns the bare
     * token (e.g. `cmp-blue`) so views can compose `text-{token}` /
     * `border-{token}` as needed.
     */
    public function conditionColor(): string
    {
        return match ($this->condition) {
            'new' => 'cmp-blue',
            'used' => 'cmp-signal',
            'defective' => 'cmp-amber',
            'for_parts' => 'cmp-muted',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (self $l): void {
            $l->ulid = $l->ulid ?? (string) Str::ulid();
            // The slug must match the public route regex `[a-z0-9-]+`,
            // so we lowercase the ulid-suffix before appending it. ULIDs
            // use [0-9A-HJKMNP-TV-Z] (uppercase Crockford base32); without
            // strtolower the slug carries `01H...` letters and the
            // detail URL would 404 against its own router constraint.
            $suffix = strtolower(substr((string) $l->ulid, -6));
            $l->slug = $l->slug ?: Str::slug($l->title).'-'.$suffix;
        });
    }
}
