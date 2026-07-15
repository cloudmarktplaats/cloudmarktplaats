<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Livewire\LaunchStats;
use App\Models\HomelabPost;
use App\Models\InviteCode;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\FoundingCohort;
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
     * inflation. Cached briefly so the landing page stays cheap under load.
     *
     * `founding_members` is the live member count, not the badge count — the
     * two diverged the moment the cohort closed. {@see FoundingCohort} for
     * why those are different questions.
     *
     * `invites_open` leans on InviteCode's `redeemable` scope rather than
     * rebuilding the condition here; one definition of "open" is enough.
     *
     * @return array{founding_members: int, listings_live: int, rescued: int, homelabs: int, invites_open: int}
     */
    public function homepageStats(): array
    {
        /** @var array{founding_members: int, listings_live: int, rescued: int, homelabs: int, invites_open: int} */
        // Key is versioned so a deploy that changes this array's shape never
        // reads an old entry written by the previous shape (a file-sync +
        // restart doesn't clear Redis, so a stale entry can outlive both).
        // Bump the suffix whenever a key is added, renamed or removed here —
        // that forces a cache miss instead of a reader crashing, or worse,
        // silently defaulting a real field to a fake value.
        return Cache::remember('stats:homepage:v2', 60, fn (): array => [
            'founding_members' => User::query()->where('is_banned', false)->count(),
            'listings_live' => Listing::query()->where('state', 'published')->count(),
            'rescued' => Listing::query()->where('state', 'sold')->count(),
            'homelabs' => HomelabPost::query()->published()->count(),
            'invites_open' => InviteCode::query()->redeemable()->count(),
        ]);
    }

    /**
     * Beta cohort size. Once this many founding badges have been stamped the
     * badge stops being handed out — {@see FoundingCohort::hasFoundingSpot()}.
     * The homepage no longer treats it as a scarcity hook once the cohort is
     * closed: it drops the progress bar and "spots left" copy and shows the
     * live member count instead ({@see LaunchStats}).
     */
    public const FOUNDING_COHORT = 100;
}
