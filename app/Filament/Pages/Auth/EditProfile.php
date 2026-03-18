<?php

namespace App\Filament\Pages\Auth;

use App\Enums\Currency;
use App\Filament\Pages\Auth\Schemas\EditProfileForm;
use App\Models\UserSetting;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditProfile
{
    protected static ?string $title = 'Profile & Preferences';

    protected ?string $localeBeforeSave = null;

    protected bool $shouldForceReload = false;

    public function getTitle(): string
    {
        return __('Profile & Preferences');
    }

    /**
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $settings = UserSetting::ensureForUser((int) $this->getUser()->getKey());
        $storedPreferences = $settings->preferences;

        if (is_string($storedPreferences)) {
            $decodedPreferences = json_decode($storedPreferences, true);
            $storedPreferences = is_array($decodedPreferences) ? $decodedPreferences : [];
        }

        if ($storedPreferences === null) {
            $storedPreferences = [];
        }

        $data['preferences'] = array_replace_recursive(
            UserSetting::defaultPreferences(),
            $storedPreferences,
        );

        $this->localeBeforeSave = (string) data_get($data, 'preferences.ui.locale', config('app.locale', 'en'));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $preferences = $data['preferences'] ?? [];
        $newLocale = data_get($preferences, 'ui.locale', config('app.locale', 'en'));
        $this->shouldForceReload = $this->localeBeforeSave !== null && $newLocale !== $this->localeBeforeSave;
        unset($data['preferences']);

        $record = parent::handleRecordUpdate($record, $data);

        UserSetting::query()->updateOrCreate(
            ['user_id' => (int) $record->getKey()],
            [
                'preferences' => array_replace_recursive(
                    UserSetting::defaultPreferences(),
                    is_array($preferences) ? $preferences : [],
                ),
            ],
        );

        return $record;
    }

    protected function afterSave(): void
    {
        if (! $this->shouldForceReload) {
            return;
        }

        $this->redirect(request()->fullUrl(), navigate: false);
    }

    public function form(Schema $schema): Schema
    {
        return EditProfileForm::configure($schema, [
            'nameComponent' => $this->getNameFormComponent(...),
            'emailComponent' => $this->getEmailFormComponent(...),
            'passwordComponent' => $this->getPasswordFormComponent(...),
            'passwordConfirmationComponent' => $this->getPasswordConfirmationFormComponent(...),
            'currentPasswordComponent' => $this->getCurrentPasswordFormComponent(...),
            'formatCurrency' => fn (Get $get): string => $this->formatCurrency($get('default_currency')),
            'formatHourlyRate' => fn (Get $get): string => $this->formatHourlyRate($get('default_hourly_rate')),
        ]);
    }

    private function formatHourlyRate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Not set');
        }

        return number_format((float) $value, 0, '.', ' ').' / h';
    }

    protected function formatCurrency(mixed $value): string
    {
        if ($value instanceof Currency) {
            return $value->value;
        }

        if (is_string($value) && trim($value) !== '') {
            $currency = Currency::tryFrom($value);

            if ($currency !== null) {
                return $currency->value;
            }

            return $value;
        }

        return __('Not set');
    }
}
