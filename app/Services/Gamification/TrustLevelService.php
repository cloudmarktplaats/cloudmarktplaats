<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\Listing;
use App\Models\User;

/**
 * Derives a user's trust level from their OWN proven activity — verified
 * email, account age, and completed sales. Recomputed on demand (no
 * storage), like badges.
 *
 * Moderation-skip (auto-publish) is gated on sales, never on karma or
 * invite activations: a completed sale requires a moderated listing plus
 * a real buyer, so a sockpuppet invite ring cannot farm its way to
 * skipping moderation. This is the deliberate anti-abuse boundary.
 */
class TrustLevelService
{
    /**
     * @return array{key: string, label: string, rank: int}
     */
    public function forUser(User $user): array
    {
        if ($user->is_banned) {
            return ['key' => 'new', 'label' => 'Nieuw', 'rank' => 0];
        }

        $verified = $user->email_verified_at !== null;
        if (! $verified) {
            return ['key' => 'new', 'label' => 'Nieuw', 'rank' => 0];
        }

        $ageDays = ($user->created_at ?? now())->diffInDays(now());
        $sold = Listing::query()->where('user_id', $user->id)->where('state', 'sold')->count();

        if ($ageDays >= 30 && $sold >= 5) {
            return ['key' => 'veteran', 'label' => 'Veteraan', 'rank' => 3];
        }
        if ($ageDays >= 14 && $sold >= 2) {
            return ['key' => 'trusted', 'label' => 'Vertrouwd', 'rank' => 2];
        }

        return ['key' => 'member', 'label' => 'Lid', 'rank' => 1];
    }

    public function canSkipModeration(User $user): bool
    {
        return (bool) config('cloudmarktplaats.features.trust_autopublish')
            && $this->forUser($user)['rank'] >= 3;
    }
}
