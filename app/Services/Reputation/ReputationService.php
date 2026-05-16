<?php

declare(strict_types=1);

namespace App\Services\Reputation;

/**
 * Placeholder for the future reputation / review service.
 *
 * The Foundation phase intentionally does not ship reviews — the spec
 * argues at length that ratings without dispute resolution turn into
 * a reputation-extortion vector. The sub-project that brings real
 * reviews (gated on the `reputation` flag) is sequenced after messaging
 * and dispute flows.
 *
 * This noop exists so list/detail views can read "no rating yet" without
 * having to know whether the reputation surface is live.
 *
 * Sentinel returns:
 *   - {@see getRating()}      → `null`
 *   - {@see getReviewCount()} → `0`
 */
class ReputationService
{
    public function getRating(int $userId): ?float
    {
        return null;
    }

    public function getReviewCount(int $userId): int
    {
        return 0;
    }
}
