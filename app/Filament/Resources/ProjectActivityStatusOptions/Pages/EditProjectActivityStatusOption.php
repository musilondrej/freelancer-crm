<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions\Pages;

use App\Filament\Resources\ProjectActivityStatusOptions\ProjectActivityStatusOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditProjectActivityStatusOption extends EditRecord
{
    protected static string $resource = ProjectActivityStatusOptionResource::class;

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
