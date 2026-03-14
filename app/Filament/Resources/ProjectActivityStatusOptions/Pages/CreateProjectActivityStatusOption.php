<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions\Pages;

use App\Filament\Resources\ProjectActivityStatusOptions\ProjectActivityStatusOptionResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectActivityStatusOption extends CreateRecord
{
    protected static string $resource = ProjectActivityStatusOptionResource::class;

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
