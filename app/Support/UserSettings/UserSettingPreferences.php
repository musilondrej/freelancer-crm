<?php

namespace App\Support\UserSettings;

final class UserSettingPreferences
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'time_tracking' => [
                'rounding' => [
                    'enabled' => (bool) config('crm.time_tracking.rounding.enabled', true),
                    'mode' => (string) config('crm.time_tracking.rounding.mode', 'ceil'),
                    'interval_minutes' => (int) config('crm.time_tracking.rounding.interval_minutes', 15),
                    'minimum_minutes' => (int) config('crm.time_tracking.rounding.minimum_minutes', 1),
                ],
            ],
            'ui' => [
                'locale' => config('app.locale', 'en'),
                'timezone' => config('app.timezone', 'UTC'),
                'week_starts_on' => 'monday',
                'date_format' => 'd.m.Y',
                'time_format' => 'H:i',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedLocales(): array
    {
        return ['en', 'cs'];
    }

    /**
     * @return list<string>
     */
    public static function allowedDateFormats(): array
    {
        return [
            'd.m.Y',
            'Y-m-d',
            'm/d/Y',
            'd/m/Y',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedTimeFormats(): array
    {
        return [
            'H:i',
            'H:i:s',
            'h:i A',
        ];
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

        return [
            'enabled' => (bool) data_get($preferences, 'time_tracking.rounding.enabled', $defaultEnabled),
            'mode' => (string) data_get($preferences, 'time_tracking.rounding.mode', $defaultMode),
            'interval_minutes' => (int) data_get($preferences, 'time_tracking.rounding.interval_minutes', $defaultIntervalMinutes),
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
        if (! in_array($resolvedWeekStartsOn, ['monday', 'sunday'], true)) {
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

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return $fallback;
        }

        return $timezone;
    }
}
