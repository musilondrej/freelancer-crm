<?php

namespace App\Filament\Resources\ProjectStatusOptions\Pages;

use App\Filament\Resources\ProjectStatusOptions\ProjectStatusOptionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectStatusOption extends CreateRecord
{
    protected static string $resource = ProjectStatusOptionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = Filament::auth()->id();

        return $data;
    }
}
