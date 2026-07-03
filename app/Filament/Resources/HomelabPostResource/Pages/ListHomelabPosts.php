<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomelabPostResource\Pages;

use App\Filament\Resources\HomelabPostResource;
use Filament\Resources\Pages\ListRecords;

class ListHomelabPosts extends ListRecords
{
    protected static string $resource = HomelabPostResource::class;
}
