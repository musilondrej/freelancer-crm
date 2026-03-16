<?php

namespace App\Filament\Pages\Auth;

use App\Models\UserSetting;
use Carbon\CarbonImmutable;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        $newLocale = (string) data_get($preferences, 'ui.locale', config('app.locale', 'en'));
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
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make(__('Profile Workspace'))
                            ->tabs([
                                Tab::make(__('Account'))
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->schema([
                                        Section::make(__('Account Identity'))
                                            ->schema([
                                                $this->getNameFormComponent(),
                                                $this->getEmailFormComponent(),
                                            ])
                                            ->columns(1),
                                        Section::make(__('Password Update'))
                                            ->schema([
                                                $this->getPasswordFormComponent(),
                                                $this->getPasswordConfirmationFormComponent(),
                                                $this->getCurrentPasswordFormComponent(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make(__('Billing'))
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->schema([
                                        Section::make(__('Billing Defaults'))
                                            ->schema([
                                                Select::make('default_currency')
                                                    ->label(__('Default currency'))
                                                    ->options([
                                                        'CZK' => 'CZK (Kc)',
                                                        'EUR' => 'EUR (EUR)',
                                                        'USD' => 'USD (USD)',
                                                    ])
                                                    ->required(),
                                                TextInput::make('default_hourly_rate')
                                                    ->label(__('Default hourly rate'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('/ h')
                                                    ->placeholder('0'),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make(__('Time Tracking'))
                                    ->icon(Heroicon::OutlinedClock)
                                    ->schema([
                                        Section::make(__('Rounding Rules'))
                                            ->schema([
                                                Toggle::make('preferences.time_tracking.rounding.enabled')
                                                    ->label(__('Enable rounding'))
                                                    ->inline(false)
                                                    ->live(),
                                                Select::make('preferences.time_tracking.rounding.mode')
                                                    ->label(__('Rounding mode'))
                                                    ->options([
                                                        'ceil' => __('Round up'),
                                                        'nearest' => __('Round to nearest'),
                                                        'floor' => __('Round down'),
                                                    ])
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                                Select::make('preferences.time_tracking.rounding.interval_minutes')
                                                    ->label(__('Rounding interval'))
                                                    ->options([
                                                        1 => __('1 min'),
                                                        5 => __('5 min'),
                                                        6 => __('6 min'),
                                                        10 => __('10 min'),
                                                        12 => __('12 min'),
                                                        15 => __('15 min'),
                                                        20 => __('20 min'),
                                                        30 => __('30 min'),
                                                        60 => __('60 min'),
                                                    ])
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                                TextInput::make('preferences.time_tracking.rounding.minimum_minutes')
                                                    ->label(__('Minimum billable minutes'))
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(240)
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make(__('Interface'))
                                    ->icon(Heroicon::OutlinedComputerDesktop)
                                    ->schema([
                                        Section::make(__('UI Preferences'))
                                            ->schema([
                                                Select::make('preferences.ui.locale')
                                                    ->label(__('Locale'))
                                                    ->options([
                                                        'en' => __('English'),
                                                        'cs' => __('Czech'),
                                                    ])
                                                    ->default((string) config('app.locale', 'en'))
                                                    ->required(),
                                                TextInput::make('preferences.ui.timezone')
                                                    ->label(__('Timezone'))
                                                    ->maxLength(64)
                                                    ->required(),
                                                Select::make('preferences.ui.date_format')
                                                    ->label(__('Date format'))
                                                    ->options($this->dateFormatOptions())
                                                    ->required(),
                                                Select::make('preferences.ui.time_format')
                                                    ->label(__('Time format'))
                                                    ->options($this->timeFormatOptions())
                                                    ->required(),
                                                Select::make('preferences.ui.week_starts_on')
                                                    ->label(__('Week starts on'))
                                                    ->options([
                                                        'monday' => __('Monday'),
                                                        'sunday' => __('Sunday'),
                                                    ])
                                                    ->required(),
                                            ])
                                            ->columns(1),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make(__('Profile Snapshot'))
                            ->schema([
                                Placeholder::make('snapshot_currency')
                                    ->label(__('Default currency'))
                                    ->content(fn (Get $get): string => (string) ($get('default_currency') ?: __('Not set'))),
                                Placeholder::make('snapshot_hourly_rate')
                                    ->label(__('Default hourly rate'))
                                    ->content(fn (Get $get): string => $this->formatHourlyRate($get('default_hourly_rate'))),
                                Placeholder::make('snapshot_rounding')
                                    ->label(__('Rounding'))
                                    ->content(fn (Get $get): string => (bool) $get('preferences.time_tracking.rounding.enabled') ? __('Enabled') : __('Disabled')),
                                Placeholder::make('snapshot_locale')
                                    ->label(__('Locale'))
                                    ->content(fn (Get $get): string => (string) ($get('preferences.ui.locale') ?: __('Not set'))),
                                Placeholder::make('snapshot_timezone')
                                    ->label(__('Timezone'))
                                    ->content(fn (Get $get): string => (string) ($get('preferences.ui.timezone') ?: __('Not set'))),
                                Placeholder::make('snapshot_datetime_preview')
                                    ->label(__('Date/time preview'))
                                    ->content(function (Get $get): string {
                                        $timezone = (string) ($get('preferences.ui.timezone') ?: config('app.timezone', 'UTC'));
                                        $dateFormat = (string) ($get('preferences.ui.date_format') ?: 'd.m.Y');
                                        $timeFormat = (string) ($get('preferences.ui.time_format') ?: 'H:i');

                                        if (! in_array($timezone, timezone_identifiers_list(), true)) {
                                            $timezone = (string) config('app.timezone', 'UTC');
                                        }

                                        return CarbonImmutable::now($timezone)->format(sprintf('%s %s', $dateFormat, $timeFormat));
                                    }),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 4,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 12,
            ]);
    }

    private function formatHourlyRate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('Not set');
        }

        return number_format((float) $value, 0, '.', ' ').' / h';
    }

    /**
     * @return array<string, string>
     */
    private function dateFormatOptions(): array
    {
        return [
            'd.m.Y' => '31.12.2026',
            'Y-m-d' => '2026-12-31',
            'm/d/Y' => '12/31/2026',
            'd/m/Y' => '31/12/2026',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function timeFormatOptions(): array
    {
        return [
            'H:i' => '14:30 (24h)',
            'H:i:s' => '14:30:25 (24h + sec)',
            'h:i A' => '02:30 PM (12h)',
        ];
    }
}
