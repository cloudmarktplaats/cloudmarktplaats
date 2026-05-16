<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ListingResource\Pages;
use App\Models\Listing;
use App\Services\Admin\AdminActionLogger;
use App\Services\Listings\ListingStateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Filament resource for moderating listings.
 *
 * State transitions go through {@see ListingStateService::transition()}
 * so the domain events (`ListingPublished`, `ListingRejected`, …) stay
 * the canonical integration point for downstream cross-cutting concerns
 * (search indexing, DAC7 reporting, reputation, owner notifications).
 *
 * Filament never writes `state` directly — even bulk actions delegate to
 * the service in a loop so each transition is auditable and produces an
 * event per listing.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('category_id')
                ->relationship('category', 'name')
                ->required(),
            Forms\Components\TextInput::make('price_cents')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('region_postcode')
                ->maxLength(10),
            Forms\Components\Select::make('state')
                ->options(array_combine(
                    array_keys(ListingStateService::TRANSITIONS),
                    array_keys(ListingStateService::TRANSITIONS),
                ))
                ->disabled()
                ->dehydrated(false),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\Textarea::make('moderation_notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Seller')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending_review' => 'warning',
                        'rejected' => 'danger',
                        'sold' => 'info',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->money('EUR', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('state')->options(
                    array_combine(
                        array_keys(ListingStateService::TRANSITIONS),
                        array_keys(ListingStateService::TRANSITIONS),
                    ),
                ),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('has_open_reports')
                    ->label('Has open reports')
                    ->query(fn (Builder $query): Builder => $query->whereExists(
                        fn ($q) => $q
                            ->selectRaw('1')
                            ->from('reports')
                            ->whereColumn('reports.reportable_id', 'listings.id')
                            ->where('reports.reportable_type', 'listing')
                            ->where('reports.status', 'open'),
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Listing $l): bool => in_array($l->state, ['pending_review'], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->label('Reason (sent to seller)'),
                    ])
                    ->action(function (Listing $record, array $data): void {
                        app(ListingStateService::class)
                            ->transition($record, 'rejected', $data['reason']);
                        AdminActionLogger::log(
                            'listing.reject',
                            'listing',
                            $record->id,
                            ['reason' => $data['reason']],
                        );
                    }),
                Tables\Actions\Action::make('publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Listing $l): bool => $l->state === 'pending_review')
                    ->requiresConfirmation()
                    ->action(function (Listing $record): void {
                        app(ListingStateService::class)
                            ->transition($record, 'published');
                        AdminActionLogger::log('listing.publish', 'listing', $record->id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $svc = app(ListingStateService::class);
                            /** @var Listing $listing */
                            foreach ($records as $listing) {
                                if ($listing->state === 'pending_review') {
                                    $svc->transition($listing, 'published');
                                    AdminActionLogger::log(
                                        'listing.publish',
                                        'listing',
                                        (int) $listing->id,
                                        ['source' => 'bulk'],
                                    );
                                }
                            }
                        }),
                    Tables\Actions\BulkAction::make('reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $svc = app(ListingStateService::class);
                            /** @var Listing $listing */
                            foreach ($records as $listing) {
                                if ($listing->state === 'pending_review') {
                                    $svc->transition($listing, 'rejected', $data['reason']);
                                    AdminActionLogger::log(
                                        'listing.reject',
                                        'listing',
                                        (int) $listing->id,
                                        ['reason' => $data['reason'], 'source' => 'bulk'],
                                    );
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListings::route('/'),
            'create' => Pages\CreateListing::route('/create'),
            'edit' => Pages\EditListing::route('/{record}/edit'),
        ];
    }
}
