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
use InvalidArgumentException;

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

    /** Variants we can actually address. See photoUrl(). */
    private const ADDRESSABLE_VARIANTS = ['card', 'thumb'];

    /**
     * URL for a derived variant of this post's photo.
     *
     * Only the webp variants are addressable. `original` is deliberately NOT:
     * StoreHomelabPhotoJob writes it as `original.{source-ext}` (jpg/png/webp),
     * but the source mime is nowhere in the database — `photo_path` always
     * holds the *card* path, so there is nothing to recover the real extension
     * from. The old code derived the extension via pathinfo($this->photo_path),
     * which therefore always yielded "webp" and produced `original.webp` for a
     * file stored as `original.jpg`: a URL that 404s.
     *
     * That is exactly the bug ListingPhoto carried (fixed 2026-07-14) — it sat
     * latent for months because nothing requested the original, and then broke
     * the moment something did. So rather than leave a guess in place, this
     * throws: a dead URL is worse than a clear error.
     *
     * The original file stays on disk as an archive; it is simply not
     * addressable. If posts ever need a shareable og:image, add a `mime` column
     * (as listing_photos has) — then the original becomes buildable.
     *
     * @throws InvalidArgumentException for a variant we cannot build a URL for
     */
    public function photoUrl(string $variant = 'card'): string
    {
        if (! in_array($variant, self::ADDRESSABLE_VARIANTS, true)) {
            throw new InvalidArgumentException(
                "Cannot build a URL for homelab photo variant '{$variant}': only "
                .implode('/', self::ADDRESSABLE_VARIANTS).' are addressable (the source mime is not stored).'
            );
        }

        $variantPath = dirname($this->photo_path).'/'.$variant.'.webp';

        return app(StorageManager::class)->driver($this->photo_disk)->url($variantPath);
    }
}
