<?php

namespace App\Filament\Resources\AdminActionResource\Pages;

use App\Filament\Resources\AdminActionResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminActions extends ListRecords
{
    protected static string $resource = AdminActionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
