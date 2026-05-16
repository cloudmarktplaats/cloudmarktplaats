<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Filament resource for the category taxonomy (Postgres ltree).
 *
 * Admin-only because mis-categorising listings ripples through the
 * Postgres FTS search index and ltree path queries. A "parent" picker
 * keeps the UI flat — Filament has no first-class tree view in v3, so
 * we surface the canonical `path` column instead.
 *
 * Slug + path are derived from the name in the create flow so admins
 * only have to type the human label.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') === true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('slug', Str::slug((string) $state));
                    $set('path', str_replace('-', '_', Str::slug((string) $state)));
                }),
            Forms\Components\TextInput::make('slug')->required(),
            Forms\Components\TextInput::make('path')
                ->required()
                ->helperText('Postgres ltree path. Underscores only.'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\TextInput::make('icon')->maxLength(60),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('path')
                    ->label('ltree path')
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('path')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
