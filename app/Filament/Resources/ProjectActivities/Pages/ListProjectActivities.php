<?php

namespace App\Filament\Resources\ProjectActivities\Pages;

use App\Filament\Resources\ProjectActivities\ProjectActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectActivities extends ListRecords
{
    protected static string $resource = ProjectActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
