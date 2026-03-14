<?php

namespace App\Models;

use Database\Factories\UserSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    /** @use HasFactory<UserSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'preferences' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPreferences(): array
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

    public static function ensureForUser(int $userId): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $userId],
            ['preferences' => self::defaultPreferences()],
        );
    }

    /**
     * @return array{enabled: bool, mode: string, interval_minutes: int, minimum_minutes: int}
     */
    public static function roundingForUser(?int $userId): array
    {
        $preferences = self::preferencesForUser($userId);
        $defaults = self::defaultPreferences()['time_tracking']['rounding'];
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
     * @return array{locale: string, timezone: string, week_starts_on: string, date_format: string, time_format: string}
     */
    public static function uiForUser(?int $userId): array
    {
        $preferences = self::preferencesForUser($userId);
        $defaults = self::defaultPreferences()['ui'];
        $defaultLocale = (string) ($defaults['locale'] ?? 'en');
        $defaultTimezone = self::normalizeTimezone($defaults['timezone'] ?? null, 'UTC');
        $defaultWeekStartsOn = (string) ($defaults['week_starts_on'] ?? 'monday');
        $defaultDateFormat = (string) ($defaults['date_format'] ?? 'd.m.Y');
        $defaultTimeFormat = (string) ($defaults['time_format'] ?? 'H:i');

        $resolvedLocale = (string) data_get($preferences, 'ui.locale', $defaultLocale);
        if (! in_array($resolvedLocale, ['en', 'cs'], true)) {
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

    public static function timezoneForUser(?int $userId): string
    {
        return self::uiForUser($userId)['timezone'];
    }

    public static function dateFormatForUser(?int $userId): string
    {
        return self::uiForUser($userId)['date_format'];
    }

    public static function timeFormatForUser(?int $userId): string
    {
        return self::uiForUser($userId)['time_format'];
    }

    public static function dateTimeFormatForUser(?int $userId): string
    {
        $ui = self::uiForUser($userId);

        return sprintf('%s %s', $ui['date_format'], $ui['time_format']);
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
     * @return array<string, mixed>
     */
    private static function preferencesForUser(?int $userId): array
    {
        if ($userId === null) {
            return self::defaultPreferences();
        }

        $storedPreferences = self::ensureForUser($userId)->getAttribute('preferences');

        if (is_string($storedPreferences)) {
            $decodedPreferences = json_decode($storedPreferences, true);
            $storedPreferences = is_array($decodedPreferences) ? $decodedPreferences : null;
        }

        if (! is_array($storedPreferences)) {
            return self::defaultPreferences();
        }

        return array_replace_recursive(
            self::defaultPreferences(),
            $storedPreferences,
        );
    }

    private static function normalizeTimezone(mixed $timezone, string $fallback): string
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
