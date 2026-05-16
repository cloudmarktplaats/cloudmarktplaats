<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminActionResource\Pages;
use App\Models\AdminAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament resource for the staff audit log.
 *
 * Read-only by design — the log is append-only and the panel must not
 * accidentally let an admin rewrite history. Create / edit / delete are
 * all disabled. Search lets compliance trace moderator activity by
 * action label or target type.
 */
class AdminActionResource extends Resource
{
    protected static ?string $model = AdminAction::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 70;

    protected static ?string $modelLabel = 'Audit entry';

    protected static ?string $pluralModelLabel = 'Audit log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('admin.username')
                    ->label('Admin')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('target_type')->badge(),
                Tables\Columns\TextColumn::make('target_id'),
                Tables\Columns\TextColumn::make('meta')
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? ''
                        : (string) json_encode($state, JSON_UNESCAPED_SLASHES))
                    ->limit(60),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Admin')
                    ->relationship('admin', 'username'),
                Tables\Filters\SelectFilter::make('target_type')
                    ->options(fn (): array => AdminAction::query()
                        ->distinct()
                        ->pluck('target_type', 'target_type')
                        ->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActions::route('/'),
        ];
    }
}
