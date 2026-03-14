<?php

namespace App\Filament\Resources\ProjectActivities\Pages;

use App\Filament\Resources\ProjectActivities\ProjectActivityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectActivity extends EditRecord
{
    protected static string $resource = ProjectActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
