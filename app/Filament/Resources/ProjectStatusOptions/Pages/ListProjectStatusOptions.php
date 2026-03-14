<?php

namespace App\Filament\Resources\ProjectStatusOptions\Pages;

use App\Filament\Resources\ProjectStatusOptions\ProjectStatusOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectStatusOptions extends ListRecords
{
    protected static string $resource = ProjectStatusOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
