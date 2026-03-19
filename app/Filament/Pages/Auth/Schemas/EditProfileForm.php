<?php

namespace App\Filament\Pages\Auth\Schemas;

use App\Enums\Currency;
use App\Enums\Profile\DateFormatEnum;
use App\Enums\Profile\TimeFormatEnum;
use App\Enums\TimeTrackingRoundingInterval;
use App\Enums\TimeTrackingRoundingMode;
use App\Enums\UserSettingLocale;
use App\Enums\UserSettingWeekStartsOn;
use BackedEnum;
use Closure;
use DateTimeZone;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EditProfileForm
{
    /**
     * @param array{
     *     nameComponent: Closure(): mixed,
     *     emailComponent: Closure(): mixed,
     *     passwordComponent: Closure(): mixed,
     *     passwordConfirmationComponent: Closure(): mixed,
     *     currentPasswordComponent: Closure(): mixed,
     *     formatCurrency: Closure(Get): string,
     *     formatHourlyRate: Closure(Get): string
     * } $callbacks
     */
    public static function configure(Schema $schema, array $callbacks): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Account identity'))
                    ->icon('heroicon-o-user')
                    ->schema([
                        ($callbacks['nameComponent'])(),
                        ($callbacks['emailComponent'])(),
                    ])
                    ->columns(1),
                Section::make(__('Password update'))
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        ($callbacks['passwordComponent'])(),
                        ($callbacks['passwordConfirmationComponent'])(),
                        ($callbacks['currentPasswordComponent'])(),
                    ])
                    ->columns(1),
                Section::make(__('Billing defaults'))
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Select::make('default_currency')
                            ->label(__('Default currency'))
                            ->options(Currency::class)
                            ->required(),
                        TextInput::make('default_hourly_rate')
                            ->label(__('Default hourly rate'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('/ h')
                            ->placeholder('0'),
                        Repeater::make('preferences.billing.hourly_rates')
                            ->label(__('Default hourly rates by currency'))
                            ->schema([
                                Select::make('currency')
                                    ->label(__('Currency'))
                                    ->options(Currency::class)
                                    ->required(),
                                TextInput::make('hourly_rate')
                                    ->label(__('Hourly rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get, 'currency')),
                            ])
                            ->columns(1)
                            ->addActionLabel(__('Add currency hourly rate'))
                            ->defaultItems(0)
                            ->collapsed()
                            ->reorderable(false)
                            ->helperText(__('Optional. Used when your default rate differs by invoice currency.')),
                    ])
                    ->columns(1),
                Section::make(__('Rounding rules'))
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Toggle::make('preferences.time_tracking.rounding.enabled')
                            ->label(__('Enable rounding'))
                            ->inline(false)
                            ->live(),
                        Select::make('preferences.time_tracking.rounding.mode')
                            ->label(__('Rounding mode'))
                            ->options(TimeTrackingRoundingMode::class)
                            ->required()
                            ->visible(fn (Get $get): bool => (bool) $get('preferences.time_tracking.rounding.enabled')),
                        Select::make('preferences.time_tracking.rounding.interval_minutes')
                            ->label(__('Rounding interval'))
                            ->options(TimeTrackingRoundingInterval::class)
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
                Section::make(__('UI Preferences'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Select::make('preferences.ui.locale')
                            ->label(__('Locale'))
                            ->options(UserSettingLocale::class)
                            ->default((string) config('app.locale', 'en'))
                            ->required(),
                        Select::make('preferences.ui.timezone')
                            ->label(__('Timezone'))
                            ->options(self::timezoneOptions())
                            ->searchable()
                            ->required(),
                        Select::make('preferences.ui.date_format')
                            ->label(__('Date format'))
                            ->options(DateFormatEnum::class)
                            ->required(),
                        Select::make('preferences.ui.time_format')
                            ->label(__('Time format'))
                            ->options(TimeFormatEnum::class)
                            ->required(),
                        Select::make('preferences.ui.week_starts_on')
                            ->label(__('Week starts on'))
                            ->options(UserSettingWeekStartsOn::class)
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }

    protected static function stringState(mixed $value, string $fallback): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $fallback;
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
