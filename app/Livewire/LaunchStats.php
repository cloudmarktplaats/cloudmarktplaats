<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\FoundingCohort;
use App\Services\Gamification\StatsService;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Public homepage "beta cohort" strip: real, live platform numbers, plus one
 * of two views depending on whether the founding-member cohort is still open.
 *
 * While open ({@see FoundingCohort::hasFoundingSpot()} is true):
 * a founding-members progress bar toward the first {@see StatsService::FOUNDING_COHORT},
 * with early scarcity ("be #7 of 100") as the hook.
 *
 * Once closed: no progress bar and no scarcity copy — the badge is no longer
 * up for grabs, so pretending otherwise would just be a frozen "100/100"
 * monument to a door that's shut. Instead it shows the live member count and
 * how many invites are still open, since registration itself stays open even
 * after the badge stops.
 *
 * The numbers are never inflated either way.
 */
class LaunchStats extends Component
{
    public function render(): View
    {
        $stats = app(StatsService::class)->homepageStats();
        $cohort = StatsService::FOUNDING_COHORT;
        $members = $stats['founding_members'];

        return view('livewire.launch-stats', [
            'stats' => $stats,
            'cohort' => $cohort,
            'members' => $members,
            'spotsLeft' => max(0, $cohort - $members),
            'pct' => min(100, (int) round($members / $cohort * 100)),
            // Volgt de badge-toestand, niet het ledental: anders klapt de
            // weergave terug naar "plekken vrij" zodra iemand vertrekt,
            // terwijl er geen badge meer te vergeven is.
            'full' => ! app(FoundingCohort::class)->hasFoundingSpot(),
            'invitesOpen' => $stats['invites_open'],
        ]);
    }
}
