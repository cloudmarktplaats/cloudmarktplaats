<?php

namespace App\Models;

use App\Services\Storage\StorageManager;
use Database\Factories\ListingPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPhoto extends Model
{
    /** @use HasFactory<ListingPhotoFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'listing_id',
        'disk',
        'path',
        'width',
        'height',
        'mime',
        'byte_size',
        'position',
    ];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Resolve a URL for a derived variant of this photo.
     *
     * Photos are stored under `listings/{ulid}/{photo_id}/{variant}.{ext}`,
     * where the model row records the path to the `card` variant. We
     * compose sibling variant paths from that base and resolve the URL
     * via the {@see StorageManager} driver matching `$this->disk` so
     * the same model works against local, S3, or future IPFS storage.
     */
    public function urlFor(string $variant = 'card'): string
    {
        $sourceExt = pathinfo($this->path, PATHINFO_EXTENSION);
        $ext = $variant === 'original' ? $sourceExt : 'webp';

        $variantPath = dirname($this->path).'/'.$variant.'.'.$ext;

        return app(StorageManager::class)->driver($this->disk)->url($variantPath);
    }
}
