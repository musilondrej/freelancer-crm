<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use App\Enums\Profile\DateFormatEnum;
use App\Enums\Profile\TimeFormatEnum;
use App\Enums\TimeTrackingRoundingInterval;
use App\Enums\TimeTrackingRoundingMode;
use App\Enums\UserSettingLocale;
use App\Enums\UserSettingWeekStartsOn;
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
                Section::make(__('Time tracking'))
                    ->columns(1)
                    ->schema([
                        Toggle::make('preferences.time_tracking.rounding.enabled')
                            ->label(__('Enable rounding'))
                            ->default((bool) config('crm.time_tracking.rounding.enabled', true))
                            ->inline(false)
                            ->columnSpanFull(),
                        Select::make('preferences.time_tracking.rounding.mode')
                            ->label(__('Rounding mode'))
                            ->options(TimeTrackingRoundingMode::class)
                            ->default(TimeTrackingRoundingMode::RoundUp)
                            ->required(),
                        Select::make('preferences.time_tracking.rounding.interval_minutes')
                            ->label(__('Interval (minutes)'))
                            ->options(TimeTrackingRoundingInterval::class)
                            ->default(TimeTrackingRoundingInterval::FifteenMinutes)
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
                            ->options(UserSettingLocale::class)
                            ->default(UserSettingLocale::English)
                            ->required(),
                        Select::make('preferences.ui.timezone')
                            ->label(__('Timezone'))
                            ->options(self::timezoneOptions())
                            ->searchable()
                            ->default((string) config('app.timezone', 'UTC'))
                            ->required(),
                        Select::make('preferences.ui.date_format')
                            ->label(__('Date format'))
                            ->options(DateFormatEnum::class)
                            ->default(DateFormatEnum::EuropeanDot)
                            ->required(),
                        Select::make('preferences.ui.time_format')
                            ->label(__('Time format'))
                            ->options(TimeFormatEnum::class)
                            ->default(TimeFormatEnum::HourMinute24h)
                            ->required(),
                        Select::make('preferences.ui.week_starts_on')
                            ->label(__('Week starts on'))
                            ->options(UserSettingWeekStartsOn::class)
                            ->default(UserSettingWeekStartsOn::Monday)
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
