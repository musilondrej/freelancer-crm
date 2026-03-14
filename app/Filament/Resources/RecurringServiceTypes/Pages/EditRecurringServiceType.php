<?php

namespace App\Filament\Resources\RecurringServiceTypes\Pages;

use App\Filament\Resources\RecurringServiceTypes\RecurringServiceTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringServiceType extends EditRecord
{
    protected static string $resource = RecurringServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
