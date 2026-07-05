<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Gamification\StatsService;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Public homepage "beta cohort" strip: real, live platform numbers plus a
 * founding-members progress bar toward the first {@see StatsService::FOUNDING_COHORT}.
 * The numbers are never inflated — early scarcity ("be #7 of 100") is the hook.
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
            'full' => $members >= $cohort,
        ]);
    }
}
