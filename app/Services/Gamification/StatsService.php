<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\HomelabPost;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StatsService
{
    /**
     * A user's own stats. Never includes anyone else's data.
     *
     * @return array{member_since: Carbon, listings_published: int, listings_sold: int, homelab_posts: int, karma: int, people_activated: int, is_founding_member: bool}
     */
    public function forUser(User $user): array
    {
        return [
            'member_since' => $user->created_at ?? now(),
            'is_founding_member' => (bool) $user->is_founding_member,
            'listings_published' => Listing::query()->where('user_id', $user->id)->where('state', 'published')->count(),
            'listings_sold' => Listing::query()->where('user_id', $user->id)->where('state', 'sold')->count(),
            'homelab_posts' => HomelabPost::query()->where('user_id', $user->id)->published()->count(),
            'karma' => $user->karma,
            'people_activated' => KarmaEvent::query()
                ->where('user_id', $user->id)
                ->where('type', 'invite_activation')
                ->count(),
        ];
    }

    /**
     * Platform-wide cooperative counter: devices given a second life.
     * Cached to keep the public homepage cheap.
     */
    public function rescuedCount(): int
    {
        return (int) Cache::remember(
            'stats:rescued',
            300,
            fn (): int => Listing::query()->where('state', 'sold')->count(),
        );
    }

    /**
     * Live platform-wide numbers for the public homepage. Real counts, no
     * inflation — the "founding members" figure drives the beta-cohort FOMO.
     * Cached briefly so the landing page stays cheap under load.
     *
     * @return array{founding_members: int, listings_live: int, rescued: int, homelabs: int}
     */
    public function homepageStats(): array
    {
        /** @var array{founding_members: int, listings_live: int, rescued: int, homelabs: int} */
        return Cache::remember('stats:homepage', 60, fn (): array => [
            'founding_members' => User::query()->where('is_banned', false)->count(),
            'listings_live' => Listing::query()->where('state', 'published')->count(),
            'rescued' => Listing::query()->where('state', 'sold')->count(),
            'homelabs' => HomelabPost::query()->published()->count(),
        ]);
    }

    /** Beta cohort size — the scarcity anchor on the homepage. */
    public const FOUNDING_COHORT = 100;
}
