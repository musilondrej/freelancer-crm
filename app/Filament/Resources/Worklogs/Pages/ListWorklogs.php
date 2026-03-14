<?php

namespace App\Filament\Resources\Worklogs\Pages;

use App\Filament\Resources\Worklogs\WorklogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorklogs extends ListRecords
{
    protected static string $resource = WorklogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
