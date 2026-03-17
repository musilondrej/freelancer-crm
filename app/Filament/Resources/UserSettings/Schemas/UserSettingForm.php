<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Time Tracking'))
                    ->columns(1)
                    ->schema([
                        Toggle::make('preferences.time_tracking.rounding.enabled')
                            ->label(__('Enable rounding'))
                            ->default((bool) config('crm.time_tracking.rounding.enabled', true))
                            ->inline(false)
                            ->columnSpanFull(),
                        Select::make('preferences.time_tracking.rounding.mode')
                            ->label(__('Rounding mode'))
                            ->options([
                                'ceil' => __('Round up'),
                                'nearest' => __('Round to nearest'),
                                'floor' => __('Round down'),
                            ])
                            ->default((string) config('crm.time_tracking.rounding.mode', 'ceil'))
                            ->required(),
                        Select::make('preferences.time_tracking.rounding.interval_minutes')
                            ->label(__('Interval (minutes)'))
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
                            ->default((int) config('crm.time_tracking.rounding.interval_minutes', 15))
                            ->required(),
                        TextInput::make('preferences.time_tracking.rounding.minimum_minutes')
                            ->label(__('Minimum billable minutes'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(240)
                            ->default((int) config('crm.time_tracking.rounding.minimum_minutes', 1))
                            ->required(),
                    ]),
                Section::make(__('Interface'))
                    ->columns(1)
                    ->schema([
                        Select::make('preferences.ui.locale')
                            ->label(__('Locale'))
                            ->options([
                                'en' => __('English'),
                                'cs' => __('Czech'),
                            ])
                            ->default((string) config('app.locale', 'en'))
                            ->required(),
                        Select::make('preferences.ui.timezone')
                            ->label(__('Timezone'))
                            ->options(self::timezoneOptions())
                            ->searchable()
                            ->default((string) config('app.timezone', 'UTC'))
                            ->required(),
                        Select::make('preferences.ui.date_format')
                            ->label(__('Date format'))
                            ->options([
                                'd.m.Y' => '31.12.2026',
                                'Y-m-d' => '2026-12-31',
                                'm/d/Y' => '12/31/2026',
                                'd/m/Y' => '31/12/2026',
                            ])
                            ->default('d.m.Y')
                            ->required(),
                        Select::make('preferences.ui.time_format')
                            ->label(__('Time format'))
                            ->options([
                                'H:i' => '14:30 (24h)',
                                'H:i:s' => '14:30:25 (24h + sec)',
                                'h:i A' => '02:30 PM (12h)',
                            ])
                            ->default('H:i')
                            ->required(),
                        Select::make('preferences.ui.week_starts_on')
                            ->label(__('Week starts on'))
                            ->options([
                                'monday' => __('Monday'),
                                'sunday' => __('Sunday'),
                            ])
                            ->default('monday')
                            ->required(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function timezoneOptions(): array
    {
        $timezones = timezone_identifiers_list(DateTimeZone::ALL);

        return array_combine($timezones, $timezones) ?: [];
    }
}
