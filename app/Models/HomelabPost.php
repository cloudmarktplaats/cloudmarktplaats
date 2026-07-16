<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomelabPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

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
        'title',
        'body',
        'feedback_prompt',
        'comments_open',
        'photo_disk',
        'photo_path',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'comments_open' => 'boolean',
        ];
    }

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

    /** @return HasMany<HomelabPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(HomelabPhoto::class)->orderBy('position');
    }

    /**
     * URL voor een variant van de eerste foto.
     *
     * Was een zelfstandige methode die `original` weigerde omdat de bron-mime
     * nergens stond. Nu leven foto's in homelab_photos mét mime, dus dit
     * delegeert naar HomelabPhoto::urlFor(). Feed, recent-blok en Filament
     * roepen dit onveranderd aan.
     *
     * @throws RuntimeException als de post geen foto heeft — kan niet via het
     *                          formulier, wel via een half mislukte migratie. Een dode URL is erger.
     */
    public function photoUrl(string $variant = 'card'): string
    {
        $photo = $this->photos->first();

        if ($photo === null) {
            throw new RuntimeException("Homelab post {$this->ulid} heeft geen foto.");
        }

        return $photo->urlFor($variant);
    }

    /**
     * URL-slug. Titel is de bron; is die leeg, dan de eerste woorden van de
     * body; is ook dat leeg, dan "homelab". Altijd een niet-lege slug, want de
     * route /homelabs/{ulid}-{slug} eist een slug-segment.
     */
    public function getSlugAttribute(): string
    {
        $base = Str::slug((string) ($this->title ?: Str::words($this->body, 6, '')));
        $base = $base !== '' ? $base : 'homelab';
        $suffix = strtolower(substr((string) $this->ulid, -6));

        return $base.'-'.$suffix;
    }

    /**
     * og:image voor de deelbare pagina: original van de eerste foto, mits
     * jpg/png. Anders null → de layout valt terug op og-default.png. Dezelfde
     * regel als de advertentiepagina, en de reden dat de twee bestaande posts
     * (mime webp) het merkbeeld tonen in plaats van een kapotte link.
     */
    public function getOgImageUrl(): ?string
    {
        $photo = $this->photos->first();

        if ($photo === null || ! in_array($photo->mime, ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return $photo->urlFor('original');
    }
}
