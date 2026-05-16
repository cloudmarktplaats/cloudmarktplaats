<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * 7-day rolling chart of new user registrations.
 *
 * Buckets are calendar days in the application timezone — we count the
 * raw `users.created_at` so the line includes every funnel exit:
 * password, OAuth and SIWE onboarding all land in the same table.
 *
 * Days with zero signups are rendered explicitly (no missing X-axis
 * tick) so the chart shape stays stable when the marketplace is quiet.
 */
class NewUsersChartWidget extends ChartWidget
{
    protected static ?string $heading = 'New users (last 7 days)';

    protected static ?int $sort = 50;

    /**
     * @return array{datasets: list<array{label: string, data: list<int>}>, labels: list<string>}
     */
    protected function getData(): array
    {
        $today = Carbon::today();
        $labels = [];
        $counts = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $labels[] = $day->format('M j');
            $counts[] = User::query()
                ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'New users',
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
