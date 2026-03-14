<?php

namespace App\Filament\Pages\Auth;

use App\Models\UserSetting;
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

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $preferences = $data['preferences'] ?? [];
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Profile Workspace')
                            ->tabs([
                                Tab::make('Account')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->schema([
                                        Section::make('Account Identity')
                                            ->schema([
                                                $this->getNameFormComponent(),
                                                $this->getEmailFormComponent(),
                                            ])
                                            ->columns(2),
                                        Section::make('Password Update')
                                            ->schema([
                                                $this->getPasswordFormComponent(),
                                                $this->getPasswordConfirmationFormComponent(),
                                                $this->getCurrentPasswordFormComponent(),
                                            ])
                                            ->columns(2),
                                    ]),
                                Tab::make('Billing')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->schema([
                                        Section::make('Billing Defaults')
                                            ->schema([
                                                Select::make('default_currency')
                                                    ->label('Default currency')
                                                    ->options([
                                                        'CZK' => 'CZK (Kc)',
                                                        'EUR' => 'EUR (EUR)',
                                                        'USD' => 'USD (USD)',
                                                    ])
                                                    ->required(),
                                                TextInput::make('default_hourly_rate')
                                                    ->label('Default hourly rate')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('/ h')
                                                    ->placeholder('0'),
                                            ])
                                            ->columns(2),
                                    ]),
                                Tab::make('Time Tracking')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->schema([
                                        Section::make('Rounding Rules')
                                            ->schema([
                                                Toggle::make('preferences.time_tracking.rounding.enabled')
                                                    ->label('Enable rounding')
                                                    ->inline(false)
                                                    ->live(),
                                                Select::make('preferences.time_tracking.rounding.mode')
                                                    ->label('Rounding mode')
                                                    ->options([
                                                        'ceil' => 'Round up',
                                                        'nearest' => 'Round to nearest',
                                                        'floor' => 'Round down',
                                                    ])
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                                Select::make('preferences.time_tracking.rounding.interval_minutes')
                                                    ->label('Rounding interval')
                                                    ->options([
                                                        1 => '1 min',
                                                        5 => '5 min',
                                                        6 => '6 min',
                                                        10 => '10 min',
                                                        12 => '12 min',
                                                        15 => '15 min',
                                                        20 => '20 min',
                                                        30 => '30 min',
                                                        60 => '60 min',
                                                    ])
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                                TextInput::make('preferences.time_tracking.rounding.minimum_minutes')
                                                    ->label('Minimum billable minutes')
                                                    ->integer()
                                                    ->minValue(0)
                                                    ->maxValue(240)
                                                    ->required()
                                                    ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                                            ])
                                            ->columns(2),
                                    ]),
                                Tab::make('Interface')
                                    ->icon(Heroicon::OutlinedComputerDesktop)
                                    ->schema([
                                        Section::make('UI Preferences')
                                            ->schema([
                                                Select::make('preferences.ui.locale')
                                                    ->label('Locale')
                                                    ->options([
                                                        'en' => 'English',
                                                        'cs' => 'Czech',
                                                    ])
                                                    ->required(),
                                                TextInput::make('preferences.ui.timezone')
                                                    ->label('Timezone')
                                                    ->maxLength(64)
                                                    ->required(),
                                                Select::make('preferences.ui.week_starts_on')
                                                    ->label('Week starts on')
                                                    ->options([
                                                        'monday' => 'Monday',
                                                        'sunday' => 'Sunday',
                                                    ])
                                                    ->required(),
                                            ])
                                            ->columns(2),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Profile Snapshot')
                            ->schema([
                                Placeholder::make('snapshot_currency')
                                    ->label('Default currency')
                                    ->content(fn (Get $get): string => (string) ($get('default_currency') ?: 'Not set')),
                                Placeholder::make('snapshot_hourly_rate')
                                    ->label('Default hourly rate')
                                    ->content(fn (Get $get): string => $this->formatHourlyRate($get('default_hourly_rate'))),
                                Placeholder::make('snapshot_rounding')
                                    ->label('Rounding')
                                    ->content(fn (Get $get): string => (bool) $get('preferences.time_tracking.rounding.enabled') ? 'Enabled' : 'Disabled'),
                                Placeholder::make('snapshot_locale')
                                    ->label('Locale')
                                    ->content(fn (Get $get): string => (string) ($get('preferences.ui.locale') ?: 'Not set')),
                                Placeholder::make('snapshot_timezone')
                                    ->label('Timezone')
                                    ->content(fn (Get $get): string => (string) ($get('preferences.ui.timezone') ?: 'Not set')),
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
            return 'Not set';
        }

        return number_format((float) $value, 0, '.', ' ').' / h';
    }
}
