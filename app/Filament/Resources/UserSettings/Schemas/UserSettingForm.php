<?php

namespace App\Filament\Resources\UserSettings\Schemas;

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
                Section::make('Time Tracking')
                    ->columns(1)
                    ->schema([
                        Toggle::make('preferences.time_tracking.rounding.enabled')
                            ->label('Enable rounding')
                            ->default((bool) config('crm.time_tracking.rounding.enabled', true))
                            ->inline(false)
                            ->columnSpanFull(),
                        Select::make('preferences.time_tracking.rounding.mode')
                            ->label('Rounding mode')
                            ->options([
                                'ceil' => 'Round up',
                                'nearest' => 'Round to nearest',
                                'floor' => 'Round down',
                            ])
                            ->default((string) config('crm.time_tracking.rounding.mode', 'ceil'))
                            ->required(),
                        Select::make('preferences.time_tracking.rounding.interval_minutes')
                            ->label('Interval (minutes)')
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
                            ->default((int) config('crm.time_tracking.rounding.interval_minutes', 15))
                            ->required(),
                        TextInput::make('preferences.time_tracking.rounding.minimum_minutes')
                            ->label('Minimum billable minutes')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(240)
                            ->default((int) config('crm.time_tracking.rounding.minimum_minutes', 1))
                            ->required(),
                    ]),
                Section::make('Interface')
                    ->columns(1)
                    ->schema([
                        Select::make('preferences.ui.locale')
                            ->label('Locale')
                            ->options([
                                'en' => 'English',
                                'cs' => 'Czech',
                            ])
                            ->default((string) config('app.locale', 'en'))
                            ->required(),
                        TextInput::make('preferences.ui.timezone')
                            ->label('Timezone')
                            ->default((string) config('app.timezone', 'UTC'))
                            ->required()
                            ->maxLength(64),
                        Select::make('preferences.ui.date_format')
                            ->label('Date format')
                            ->options([
                                'd.m.Y' => '31.12.2026',
                                'Y-m-d' => '2026-12-31',
                                'm/d/Y' => '12/31/2026',
                                'd/m/Y' => '31/12/2026',
                            ])
                            ->default('d.m.Y')
                            ->required(),
                        Select::make('preferences.ui.time_format')
                            ->label('Time format')
                            ->options([
                                'H:i' => '14:30 (24h)',
                                'H:i:s' => '14:30:25 (24h + sec)',
                                'h:i A' => '02:30 PM (12h)',
                            ])
                            ->default('H:i')
                            ->required(),
                        Select::make('preferences.ui.week_starts_on')
                            ->label('Week starts on')
                            ->options([
                                'monday' => 'Monday',
                                'sunday' => 'Sunday',
                            ])
                            ->default('monday')
                            ->required(),
                    ]),
            ]);
    }
}
