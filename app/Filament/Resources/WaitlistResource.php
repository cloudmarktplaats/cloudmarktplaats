<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WaitlistResource\Pages;
use App\Models\WaitlistEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Waitlist of prospective members who arrived after the founding cohort
 * filled up. Read-only apart from the inline "invited" toggle, which lets
 * an admin mark someone as already invited once a slot opens.
 */
class WaitlistResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Waitlist entry';

    protected static ?string $pluralModelLabel = 'Waitlist';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()->where('invited', false)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
                Tables\Columns\ToggleColumn::make('invited')->label('Invited'),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('invited'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaitlistEntries::route('/'),
        ];
    }
}
