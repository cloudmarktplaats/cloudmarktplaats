<?php

namespace App\Models;

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
     * The full implementation, which uses the StorageManager service to
     * dispatch on `$this->disk` and return a CDN-style URL, lands in
     * Phase G. Until then we expose the variant path so callers can wire
     * it up against the default disk if needed.
     */
    public function urlFor(string $variant = 'card'): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $this->path) ?? $this->path;
        $ext = $variant === 'original'
            ? pathinfo($this->path, PATHINFO_EXTENSION)
            : 'webp';

        return dirname($base).'/'.$variant.'.'.$ext;
    }
}
