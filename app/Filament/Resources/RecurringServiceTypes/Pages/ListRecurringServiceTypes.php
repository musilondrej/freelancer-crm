<?php

namespace App\Filament\Resources\RecurringServiceTypes\Pages;

use App\Filament\Resources\RecurringServiceTypes\RecurringServiceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringServiceTypes extends ListRecords
{
    protected static string $resource = RecurringServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
