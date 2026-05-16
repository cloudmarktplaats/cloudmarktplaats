<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LegalDocument;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How many existing users have not accepted the active Dutch ToS.
 *
 * "Active" = the most recently published `(type=tos, locale=nl)` row.
 * The query counts users without any acceptance row pointing at that
 * document. When the active ToS hasn't been published yet (fresh
 * install) the widget shows 0 instead of "every user is out of date".
 */
class OutdatedTosWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 40;

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $current = LegalDocument::current('tos', 'nl');

        $count = $current === null
            ? 0
            : User::query()
                ->whereDoesntHave(
                    'legalAcceptances',
                    fn ($q) => $q->where('legal_document_id', $current->id),
                )
                ->count();

        return [
            Stat::make('Users with outdated ToS', (string) $count)
                ->description('Hebben de huidige voorwaarden nog niet bevestigd')
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-document-text'),
        ];
    }
}
