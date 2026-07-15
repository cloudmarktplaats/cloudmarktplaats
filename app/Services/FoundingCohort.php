<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Gamification\StatsService;

/**
 * The "first 100" beta cohort. Two concerns live here so registration,
 * the homepage counter and the badge all agree:
 *
 *  - {@see hasFoundingSpot()} — has the 100th founding badge been stamped?
 *    Drives the permanent `is_founding_member` flag set at registration.
 *  - {@see isRegistrationOpen()} — may a new account be created at all? When
 *    the waitlist feature is on and the cohort is full, registration closes
 *    and new arrivals are sent to the waitlist instead.
 *
 * The two count different things, deliberately. Registration looks at live
 * members: someone who left or was banned no longer occupies a seat, so the
 * seat is free. The badge looks at history: it counts every badge ever
 * stamped, including those of deleted and banned accounts, because "one of
 * the first 100" is a fact about the past that a departure cannot undo.
 *
 * That distinction was missing until 2026-07-15 and it cost us a badge: User
 * uses SoftDeletes, so when one founder deleted their account members() fell
 * to 99 and the next arrival — number 101 — was stamped as a founder.
 */
class FoundingCohort
{
    public function size(): int
    {
        return StatsService::FOUNDING_COHORT;
    }

    public function members(): int
    {
        return User::query()->where('is_banned', false)->count();
    }

    public function spotsLeft(): int
    {
        return max(0, $this->size() - $this->members());
    }

    /**
     * Has the 100th founding badge been stamped?
     *
     * Counts badges ever issued — `withTrashed()` is the whole point, not a
     * detail. Without it a departure frees a badge slot and the next arrival
     * is stamped as a founder they never were.
     */
    public function hasFoundingSpot(): bool
    {
        return User::withTrashed()->where('is_founding_member', true)->count() < $this->size();
    }

    /**
     * May a brand-new account be created? Always true unless the waitlist
     * feature is enabled AND the cohort is full.
     */
    public function isRegistrationOpen(): bool
    {
        if (! (bool) config('cloudmarktplaats.features.waitlist')) {
            return true;
        }

        return $this->members() < $this->size();
    }
}
