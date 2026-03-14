<?php

namespace App\Filament\Resources\UserSettings\Pages;

use App\Filament\Resources\UserSettings\UserSettingResource;
use App\Models\UserSetting;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListUserSettings extends ListRecords
{
    protected static string $resource = UserSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(function (): bool {
                    $userId = Filament::auth()->id();

                    if ($userId === null) {
                        return false;
                    }

                    return ! UserSetting::query()
                        ->where('user_id', $userId)
                        ->exists();
                }),
        ];
    }
}
