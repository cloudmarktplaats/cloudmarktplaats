<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 gecacht artikel uit een feed. Nooit een image of tracking-pixel. */
class FeedItem extends Model
{
    /** @use HasFactory<FeedItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'feed_source_id', 'guid', 'title', 'url', 'summary', 'published_at', 'fetched_at',
    ];

    /**
     * @return array{published_at: 'datetime', fetched_at: 'datetime'}
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FeedSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }
}
