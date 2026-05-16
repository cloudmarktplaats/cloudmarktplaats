<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surface the depth of the moderation queue.
 *
 * `pending_review` is the only state where a human action is required
 * for a listing to become discoverable, so this number doubles as
 * "moderator SLA" health. The yellow descriptor lets staff see at a
 * glance whether they're falling behind.
 */
class PendingReviewsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $count = Listing::query()->where('state', 'pending_review')->count();

        return [
            Stat::make('Listings awaiting review', (string) $count)
                ->description('In de wachtrij voor moderatie')
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
        ];
    }
}
