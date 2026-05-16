<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Snapshot of the live catalog.
 *
 * Counts listings in the `published` state — what an anonymous visitor
 * actually sees on /browse. Useful for capacity planning (Postgres FTS
 * index size scales linearly with this number) and for spotting catalog
 * collapses (e.g. a bug that incorrectly archived everything).
 */
class ActiveListingsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $count = Listing::query()->where('state', 'published')->count();

        return [
            Stat::make('Active listings', (string) $count)
                ->description('Live in de catalogus')
                ->color('success')
                ->icon('heroicon-o-rectangle-stack'),
        ];
    }
}
