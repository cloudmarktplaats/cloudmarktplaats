<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomelabPostResource\Pages;
use App\Models\HomelabPost;
use App\Services\Admin\AdminActionLogger;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Moderatie van homelab-posts. Dit is de ENIGE plek waar de poster
 * zichtbaar is — publiek is de feed volledig anoniem.
 */
class HomelabPostResource extends Resource
{
    protected static ?string $model = HomelabPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Moderatie';

    protected static ?string $modelLabel = 'Homelab-post';

    protected static ?string $pluralModelLabel = 'Homelab-posts';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->state(fn (HomelabPost $record): string => $record->photoUrl('card'))
                    ->square(),
                Tables\Columns\TextColumn::make('body')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Poster (intern!)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['published' => 'published', 'removed' => 'removed']),
            ])
            ->actions([
                Tables\Actions\Action::make('remove')
                    ->visible(fn (HomelabPost $record): bool => $record->status === 'published')
                    ->requiresConfirmation()
                    ->action(function (HomelabPost $record): void {
                        $record->update(['status' => 'removed']);
                        AdminActionLogger::log('homelab_post.remove', 'homelab_post', $record->id);
                    }),
                Tables\Actions\Action::make('restore')
                    ->visible(fn (HomelabPost $record): bool => $record->status === 'removed')
                    ->requiresConfirmation()
                    ->action(function (HomelabPost $record): void {
                        $record->update(['status' => 'published']);
                        AdminActionLogger::log('homelab_post.restore', 'homelab_post', $record->id);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomelabPosts::route('/'),
        ];
    }
}
