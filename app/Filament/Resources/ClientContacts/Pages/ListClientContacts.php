<?php

namespace App\Filament\Resources\ClientContacts\Pages;

use App\Filament\Resources\ClientContacts\ClientContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientContacts extends ListRecords
{
    protected static string $resource = ClientContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
