<?php

namespace App\Filament\Resources\UserSettings\Pages;

use App\Filament\Resources\UserSettings\UserSettingResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUserSetting extends CreateRecord
{
    protected static string $resource = UserSettingResource::class;

    protected bool $shouldForceReload = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $newLocale = (string) data_get($data, 'preferences.ui.locale', config('app.locale', 'en'));
        $this->shouldForceReload = $newLocale !== (string) config('app.locale', 'en');

        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldForceReload) {
            return;
        }

        $this->redirect(request()->fullUrl(), navigate: false);
    }
}
