<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LegalDocumentResource\Pages;
use App\Models\LegalDocument;
use App\Services\Admin\AdminActionLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource for ToS / Privacy versioning.
 *
 * Documents are immutable once published: instead of editing the active
 * row, admins use "Publish new version" which inserts a fresh row with
 * a bumped `version` string. The historic row is retained so we can
 * always reconstruct which exact ToS text a user accepted (legal trail).
 *
 * Table is grouped by `(type, locale)` to make version history obvious.
 */
class LegalDocumentResource extends Resource
{
    protected static ?string $model = LegalDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 60;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->options(['tos' => 'Terms of Service', 'privacy' => 'Privacy Policy'])
                ->required(),
            Forms\Components\TextInput::make('version')->required()->maxLength(20),
            Forms\Components\TextInput::make('locale')->required()->maxLength(5)->default('nl'),
            Forms\Components\MarkdownEditor::make('markdown_content')
                ->required()
                ->columnSpanFull(),
            Forms\Components\DateTimePicker::make('published_at')
                ->helperText('Leave empty to keep as draft; set to make active.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('locale')->badge(),
                Tables\Columns\TextColumn::make('version')->sortable(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->groups(['type', 'locale'])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'tos' => 'ToS',
                    'privacy' => 'Privacy',
                ]),
            ])
            ->actions([
                // Published rows are part of the legal trail and must
                // remain byte-for-byte stable: a user who clicked
                // "ik accepteer" against ToS v1.0.0 should always be able
                // to reread the exact text they accepted. Editing drafts
                // (published_at = null) is fine; once a row is live, force
                // admins through "Publish new version" instead.
                Tables\Actions\EditAction::make()
                    ->visible(fn (LegalDocument $record): bool => $record->published_at === null),
                Tables\Actions\Action::make('publish_new_version')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('primary')
                    ->label('Publish new version')
                    ->form([
                        Forms\Components\TextInput::make('version')
                            ->required()
                            ->helperText('New semver-ish version string, e.g. 1.1.0'),
                        Forms\Components\MarkdownEditor::make('markdown_content')
                            ->required(),
                    ])
                    ->action(function (LegalDocument $record, array $data): void {
                        $new = LegalDocument::query()->create([
                            'type' => $record->type,
                            'locale' => $record->locale,
                            'version' => $data['version'],
                            'markdown_content' => $data['markdown_content'],
                            'published_at' => now(),
                        ]);
                        AdminActionLogger::log(
                            'legal.publish',
                            'legal_document',
                            $new->id,
                            ['type' => $record->type, 'version' => $data['version']],
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalDocuments::route('/'),
            'create' => Pages\CreateLegalDocument::route('/create'),
            'edit' => Pages\EditLegalDocument::route('/{record}/edit'),
        ];
    }
}
