<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\Listing;
use App\Models\Report;
use App\Services\Admin\AdminActionLogger;
use App\Services\Listings\ListingStateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Filament resource for moderating reports.
 *
 * Mods + admins see this. The two terminal actions, `resolve` and
 * `dismiss`, both write the closing fields (`status`, `resolved_by_user_id`,
 * `resolution_note`) and log an audit row.
 *
 * `resolve` for listing reports can cascade — when the report is on a
 * listing and the mod chooses "archive the listing", the listing
 * transitions to `archived` via {@see ListingStateService} so the
 * domain events still fire.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reportable_type')->disabled(),
            Forms\Components\TextInput::make('reportable_id')->disabled(),
            Forms\Components\TextInput::make('reason')->disabled(),
            Forms\Components\Textarea::make('details')->columnSpanFull()->disabled(),
            Forms\Components\Select::make('status')->options([
                'open' => 'open',
                'resolved' => 'resolved',
                'dismissed' => 'dismissed',
            ])->disabled(),
            Forms\Components\Textarea::make('resolution_note')->columnSpanFull()->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reportable_type')
                    ->label('Target')
                    ->badge(),
                Tables\Columns\TextColumn::make('reportable_id')->label('Target #'),
                Tables\Columns\TextColumn::make('reason')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'resolved' => 'success',
                        'dismissed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reporter.username')
                    ->label('Reporter')
                    ->default('anonymous'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'resolved' => 'Resolved',
                    'dismissed' => 'Dismissed',
                ])->default('open'),
                Tables\Filters\SelectFilter::make('reason')->options([
                    'illegal' => 'illegal',
                    'stolen' => 'stolen',
                    'spam' => 'spam',
                    'wrong_category' => 'wrong_category',
                    'other' => 'other',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Report $r): bool => $r->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->required()
                            ->label('Resolution note'),
                        Forms\Components\Toggle::make('archive_listing')
                            ->label('Also archive the listing')
                            ->helperText('Only effective when this report targets a listing.'),
                    ])
                    ->action(function (Report $record, array $data): void {
                        $record->forceFill([
                            'status' => 'resolved',
                            'resolved_by_user_id' => Auth::id(),
                            'resolution_note' => $data['note'],
                        ])->save();

                        $cascade = (bool) ($data['archive_listing'] ?? false);
                        if ($cascade && $record->reportable_type === 'listing') {
                            $listing = Listing::find($record->reportable_id);
                            if ($listing && in_array(
                                $listing->state,
                                ['draft', 'pending_review', 'published', 'sold'],
                                true,
                            )) {
                                app(ListingStateService::class)->transition($listing, 'archived');
                            }
                        }

                        AdminActionLogger::log(
                            'report.resolve',
                            'report',
                            $record->id,
                            ['note' => $data['note'], 'cascade_archive' => $cascade],
                        );
                    }),
                Tables\Actions\Action::make('dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (Report $r): bool => $r->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->required()
                            ->label('Dismissal note'),
                    ])
                    ->action(function (Report $record, array $data): void {
                        $record->forceFill([
                            'status' => 'dismissed',
                            'resolved_by_user_id' => Auth::id(),
                            'resolution_note' => $data['note'],
                        ])->save();
                        AdminActionLogger::log(
                            'report.dismiss',
                            'report',
                            $record->id,
                            ['note' => $data['note']],
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
        ];
    }
}
