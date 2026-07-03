<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Gamification\StatsService;
use Illuminate\View\View;
use Livewire\Component;

class RescuedCounter extends Component
{
    public function render(): View
    {
        $count = config('cloudmarktplaats.features.stats')
            ? app(StatsService::class)->rescuedCount()
            : 0;

        return view('livewire.rescued-counter', ['count' => $count]);
    }
}
