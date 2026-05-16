<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Report;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Open report backlog.
 *
 * Pairs with {@see PendingReviewsWidget} as the second moderator-health
 * stat. Reports that linger in `open` state are harder to triage than a
 * fresh listing because they imply someone in the community already
 * thinks something is wrong.
 */
class OpenReportsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $count = Report::query()->where('status', 'open')->count();

        return [
            Stat::make('Open reports', (string) $count)
                ->description('Wachten op moderator-actie')
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-flag'),
        ];
    }
}
