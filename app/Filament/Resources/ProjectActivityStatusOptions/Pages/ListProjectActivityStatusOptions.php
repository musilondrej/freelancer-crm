<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions\Pages;

use App\Filament\Resources\ProjectActivityStatusOptions\ProjectActivityStatusOptionResource;
use Filament\Resources\Pages\ListRecords;

class ListProjectActivityStatusOptions extends ListRecords
{
    protected static string $resource = ProjectActivityStatusOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
