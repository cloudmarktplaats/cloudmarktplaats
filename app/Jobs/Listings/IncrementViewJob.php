<?php

declare(strict_types=1);

namespace App\Jobs\Listings;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

/**
 * View-count anti-abuse counter.
 *
 * The detail page dispatches this after the response so it doesn't add
 * latency to page render. We throttle increments to once-per-hour per
 * (listing, ip_hash) using Redis SETNX. The Redis key is set on the
 * first hit with a 1-hour TTL; subsequent hits within the window are
 * no-ops, which protects against bots and refresh-spam from inflating
 * popular-listing rankings.
 */
class IncrementViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $listingId,
        public string $ipHash,
    ) {}

    public function handle(): void
    {
        $key = "views:{$this->listingId}:{$this->ipHash}";

        // Laravel's PhpRedisConnection::set signature is
        //   set($key, $value, $expireResolution, $expireTTL, $flag).
        // Returns truthy on first set, null/false on duplicate
        // (i.e. another hit from the same IP within the past hour),
        // which is exactly the SETNX-with-TTL semantics we need.
        /** @phpstan-ignore-next-line method.tooFewArgs */
        $result = Redis::set($key, '1', 'EX', 3600, 'NX');

        if (! $result) {
            return;
        }

        Listing::query()->where('id', $this->listingId)->increment('view_count');
    }
}
