<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\Gamification\StatsService;

/**
 * The "first 100" beta cohort. Two concerns live here so registration,
 * the homepage counter and the badge all agree:
 *
 *  - {@see hasFoundingSpot()} — is there still a founding-member slot? Drives
 *    the permanent `is_founding_member` flag stamped at registration.
 *  - {@see isRegistrationOpen()} — may a new account be created at all? When
 *    the waitlist feature is on and the cohort is full, registration closes
 *    and new arrivals are sent to the waitlist instead.
 *
 * Members are counted excluding banned accounts, matching the homepage
 * counter — a banned founder frees a slot for the next arrival.
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

    /** Is there room in the first-100 cohort for one more founder? */
    public function hasFoundingSpot(): bool
    {
        return $this->members() < $this->size();
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

        return $this->hasFoundingSpot();
    }
}
