<?php

namespace App\Support\UserSettings;

use App\Enums\Profile\DateFormatEnum;
use App\Enums\Profile\TimeFormatEnum;
use App\Enums\TimeTrackingRoundingInterval;
use App\Enums\TimeTrackingRoundingMode;
use App\Enums\UserSettingLocale;
use App\Enums\UserSettingWeekStartsOn;

final class UserSettingPreferences
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $roundingMode = TimeTrackingRoundingMode::tryFrom((string) config('crm.time_tracking.rounding.mode', 'ceil'))
            ?? TimeTrackingRoundingMode::RoundUp;
        $roundingInterval = TimeTrackingRoundingInterval::tryFrom((int) config('crm.time_tracking.rounding.interval_minutes', 15))
            ?? TimeTrackingRoundingInterval::FifteenMinutes;
        $locale = UserSettingLocale::tryFrom((string) config('app.locale', UserSettingLocale::English->value))
            ?? UserSettingLocale::English;

        return [
            'billing' => [
                'hourly_rates' => [],
            ],
            'time_tracking' => [
                'rounding' => [
                    'enabled' => (bool) config('crm.time_tracking.rounding.enabled', true),
                    'mode' => $roundingMode->value,
                    'interval_minutes' => $roundingInterval->value,
                    'minimum_minutes' => (int) config('crm.time_tracking.rounding.minimum_minutes', 1),
                ],
            ],
            'ui' => [
                'locale' => $locale->value,
                'timezone' => config('app.timezone', 'UTC'),
                'week_starts_on' => UserSettingWeekStartsOn::Monday->value,
                'date_format' => DateFormatEnum::EuropeanDot->value,
                'time_format' => TimeFormatEnum::HourMinute24h->value,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedLocales(): array
    {
        return UserSettingLocale::values();
    }

    /**
     * @return list<string>
     */
    public static function allowedDateFormats(): array
    {
        return DateFormatEnum::values();
    }

    /**
     * @return list<string>
     */
    public static function allowedTimeFormats(): array
    {
        return TimeFormatEnum::values();
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{enabled: bool, mode: string, interval_minutes: int, minimum_minutes: int}
     */
    public static function resolveRounding(array $preferences): array
    {
        $defaults = self::defaults()['time_tracking']['rounding'];
        $defaultEnabled = (bool) ($defaults['enabled'] ?? true);
        $defaultMode = (string) ($defaults['mode'] ?? 'ceil');
        $defaultIntervalMinutes = (int) ($defaults['interval_minutes'] ?? 15);
        $defaultMinimumMinutes = (int) ($defaults['minimum_minutes'] ?? 1);

        $resolvedMode = TimeTrackingRoundingMode::tryFrom(
            (string) data_get($preferences, 'time_tracking.rounding.mode', $defaultMode),
        ) ?? TimeTrackingRoundingMode::from($defaultMode);
        $resolvedIntervalMinutes = TimeTrackingRoundingInterval::tryFrom(
            (int) data_get($preferences, 'time_tracking.rounding.interval_minutes', $defaultIntervalMinutes),
        ) ?? TimeTrackingRoundingInterval::from($defaultIntervalMinutes);

        return [
            'enabled' => (bool) data_get($preferences, 'time_tracking.rounding.enabled', $defaultEnabled),
            'mode' => $resolvedMode->value,
            'interval_minutes' => $resolvedIntervalMinutes->value,
            'minimum_minutes' => (int) data_get($preferences, 'time_tracking.rounding.minimum_minutes', $defaultMinimumMinutes),
        ];
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{locale: string, timezone: string, week_starts_on: string, date_format: string, time_format: string}
     */
    public static function resolveUi(array $preferences): array
    {
        $defaults = self::defaults()['ui'];
        $defaultLocale = (string) ($defaults['locale'] ?? 'en');
        $defaultTimezone = self::normalizeTimezone($defaults['timezone'] ?? null, 'UTC');
        $defaultWeekStartsOn = (string) ($defaults['week_starts_on'] ?? 'monday');
        $defaultDateFormat = (string) ($defaults['date_format'] ?? 'd.m.Y');
        $defaultTimeFormat = (string) ($defaults['time_format'] ?? 'H:i');

        $resolvedLocale = (string) data_get($preferences, 'ui.locale', $defaultLocale);
        if (! in_array($resolvedLocale, self::allowedLocales(), true)) {
            $resolvedLocale = $defaultLocale;
        }

        $resolvedWeekStartsOn = (string) data_get($preferences, 'ui.week_starts_on', $defaultWeekStartsOn);
        if (! in_array($resolvedWeekStartsOn, UserSettingWeekStartsOn::values(), true)) {
            $resolvedWeekStartsOn = $defaultWeekStartsOn;
        }

        $resolvedDateFormat = (string) data_get($preferences, 'ui.date_format', $defaultDateFormat);
        if (! in_array($resolvedDateFormat, self::allowedDateFormats(), true)) {
            $resolvedDateFormat = $defaultDateFormat;
        }

        $resolvedTimeFormat = (string) data_get($preferences, 'ui.time_format', $defaultTimeFormat);
        if (! in_array($resolvedTimeFormat, self::allowedTimeFormats(), true)) {
            $resolvedTimeFormat = $defaultTimeFormat;
        }

        return [
            'locale' => $resolvedLocale,
            'timezone' => self::normalizeTimezone(
                data_get($preferences, 'ui.timezone'),
                $defaultTimezone,
            ),
            'week_starts_on' => $resolvedWeekStartsOn,
            'date_format' => $resolvedDateFormat,
            'time_format' => $resolvedTimeFormat,
        ];
    }

    public static function normalizeTimezone(mixed $timezone, string $fallback): string
    {
        if (! is_string($timezone)) {
            return $fallback;
        }

        static $identifiers = null;
        $identifiers ??= timezone_identifiers_list();

        if (! in_array($timezone, $identifiers, true)) {
            return $fallback;
        }

        return $timezone;
    }
}
