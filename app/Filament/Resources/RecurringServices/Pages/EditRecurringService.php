<?php

namespace App\Filament\Resources\RecurringServices\Pages;

use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringService extends EditRecord
{
    protected static string $resource = RecurringServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
