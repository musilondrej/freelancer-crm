<?php

namespace App\Filament\Resources\ProjectStatusOptions\Pages;

use App\Filament\Resources\ProjectStatusOptions\ProjectStatusOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditProjectStatusOption extends EditRecord
{
    protected static string $resource = ProjectStatusOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['owner_id'] = Filament::auth()->id();

        return $data;
    }
}
