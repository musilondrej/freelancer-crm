<?php

namespace App\Filament\Resources\RecurringServices\Pages;

use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringServices extends ListRecords
{
    protected static string $resource = RecurringServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
