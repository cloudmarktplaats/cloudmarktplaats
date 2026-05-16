<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\Admin\AdminActionLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource for managing user accounts.
 *
 * Restricted to admins (moderators see the panel but not this resource).
 * Custom actions:
 *   - Edit role (user/moderator/admin)
 *   - Ban / unban with a mandatory reason captured in `banned_reason`
 *   - Force-disable 2FA — clears two_factor_* columns and audits the act
 *   - Soft-delete via bulk action
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('username')
                ->required()
                ->maxLength(30),
            Forms\Components\TextInput::make('display_name')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('role')
                ->options([
                    'user' => 'User',
                    'moderator' => 'Moderator',
                    'admin' => 'Admin',
                ])
                ->required()
                ->default('user'),
            Forms\Components\Toggle::make('is_banned'),
            Forms\Components\Textarea::make('banned_reason')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('username')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'moderator' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_banned')->boolean()->label('Banned'),
                Tables\Columns\IconColumn::make('two_factor_confirmed_at')
                    ->boolean()
                    ->label('2FA'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    'user' => 'User',
                    'moderator' => 'Moderator',
                    'admin' => 'Admin',
                ]),
                Tables\Filters\TernaryFilter::make('is_banned'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $user): bool => ! $user->is_banned)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->label('Reden voor ban'),
                    ])
                    ->action(function (User $user, array $data): void {
                        $user->forceFill([
                            'is_banned' => true,
                            'banned_reason' => $data['reason'],
                        ])->save();
                        AdminActionLogger::log('user.ban', 'user', $user->id, [
                            'reason' => $data['reason'],
                        ]);
                    }),
                Tables\Actions\Action::make('unban')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $user): bool => (bool) $user->is_banned)
                    ->requiresConfirmation()
                    ->action(function (User $user): void {
                        $user->forceFill([
                            'is_banned' => false,
                            'banned_reason' => null,
                        ])->save();
                        AdminActionLogger::log('user.unban', 'user', $user->id);
                    }),
                Tables\Actions\Action::make('force_disable_2fa')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->label('Force-disable 2FA')
                    ->visible(fn (User $user): bool => $user->two_factor_confirmed_at !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Resets the user\'s 2FA. Use only on support request, never silently.')
                    ->action(function (User $user): void {
                        $user->forceFill([
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                        ])->save();
                        AdminActionLogger::log('user.force_disable_2fa', 'user', $user->id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
