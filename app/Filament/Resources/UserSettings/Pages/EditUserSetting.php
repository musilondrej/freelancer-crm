<?php

namespace App\Filament\Resources\UserSettings\Pages;

use App\Filament\Resources\UserSettings\UserSettingResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUserSetting extends EditRecord
{
    protected static string $resource = UserSettingResource::class;

    protected bool $shouldForceReload = false;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentLocale = (string) data_get($this->getRecord()->getAttribute('preferences'), 'ui.locale', config('app.locale', 'en'));
        $newLocale = (string) data_get($data, 'preferences.ui.locale', config('app.locale', 'en'));

        $this->shouldForceReload = $newLocale !== $currentLocale;
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->shouldForceReload) {
            return;
        }

        $this->redirect(request()->fullUrl(), navigate: false);
    }
}
