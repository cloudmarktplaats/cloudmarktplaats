<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Storage\StorageManager;
use Database\Factories\HomelabPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén foto van een homelab-post. Spiegel van ListingPhoto: `path` wijst naar de
 * card-variant (webp), `mime` bewaart de bron zodat original.{ext} te bouwen is.
 */
class HomelabPhoto extends Model
{
    /** @use HasFactory<HomelabPhotoFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'homelab_post_id',
        'disk',
        'path',
        'width',
        'height',
        'mime',
        'byte_size',
        'position',
    ];

    /** @return BelongsTo<HomelabPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(HomelabPost::class, 'homelab_post_id');
    }

    /**
     * URL voor een variant. `path` wijst naar de card (webp); siblings worden
     * eruit afgeleid. Alleen `original` kent zijn eigen extensie, via `mime`.
     */
    public function urlFor(string $variant = 'card'): string
    {
        $ext = $variant === 'original' ? self::extForMime($this->mime) : 'webp';

        $variantPath = dirname($this->path).'/'.$variant.'.'.$ext;

        return app(StorageManager::class)->driver($this->disk)->url($variantPath);
    }

    /** Bron-extensie voor een mime. Naast de lezer gehouden, niet naast de schrijver. */
    public static function extForMime(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
