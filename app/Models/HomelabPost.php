<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Storage\StorageManager;
use Database\Factories\HomelabPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Pseudonieme homelab-showcase-post.
 *
 * Anonimiteitscontract: user_id bestaat voor rate-limits, eigen-post-
 * verwijderen en moderatie, maar wordt NOOIT publiek gerenderd. Publiek
 * zichtbaar zijn uitsluitend foto, body en relatieve tijd.
 */
class HomelabPost extends Model
{
    /** @use HasFactory<HomelabPostFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'body',
        'photo_disk',
        'photo_path',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (HomelabPost $post) {
            $post->ulid ??= strtolower((string) Str::ulid());
        });
    }

    /**
     * @param  Builder<HomelabPost>  $query
     * @return Builder<HomelabPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<HomelabPostUpvote, $this> */
    public function upvotes(): HasMany
    {
        return $this->hasMany(HomelabPostUpvote::class);
    }

    public function photoUrl(string $variant = 'card'): string
    {
        $sourceExt = pathinfo($this->photo_path, PATHINFO_EXTENSION);
        $ext = $variant === 'original' ? $sourceExt : 'webp';
        $variantPath = dirname($this->photo_path).'/'.$variant.'.'.$ext;

        return app(StorageManager::class)->driver($this->photo_disk)->url($variantPath);
    }
}
