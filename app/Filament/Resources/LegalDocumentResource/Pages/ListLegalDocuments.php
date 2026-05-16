<?php

namespace App\Filament\Resources\LegalDocumentResource\Pages;

use App\Filament\Resources\LegalDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
