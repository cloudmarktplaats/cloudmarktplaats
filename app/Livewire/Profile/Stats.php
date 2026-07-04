<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StatsService;
use App\Services\Gamification\TrustLevelService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketing', ['title' => 'Statistieken — Cloudmarktplaats'])]
class Stats extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.stats'), 404);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $stats = app(StatsService::class)->forUser($user);

        return view('livewire.profile.stats', [
            'stats' => $stats,
            'badges' => app(BadgeService::class)->earnedFor($stats),
            'trust' => config('cloudmarktplaats.features.trust')
                ? app(TrustLevelService::class)->forUser($user)
                : null,
        ]);
    }
}
