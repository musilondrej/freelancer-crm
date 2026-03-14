<?php

namespace App\Filament\Resources\BacklogItems\Pages;

use App\Filament\Resources\BacklogItems\BacklogItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBacklogItems extends ListRecords
{
    protected static string $resource = BacklogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
