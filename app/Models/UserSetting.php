<?php

namespace App\Models;

use App\Support\UserSettings\UserSettingPreferences;
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
        return UserSettingPreferences::defaults();
    }

    /**
     * @return list<string>
     */
    public static function allowedLocales(): array
    {
        return UserSettingPreferences::allowedLocales();
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
        return UserSettingPreferences::resolveRounding(
            self::preferencesForUser($userId),
        );
    }

    /**
     * @return array{locale: string, timezone: string, week_starts_on: string, date_format: string, time_format: string}
     */
    public static function uiForUser(?int $userId): array
    {
        return UserSettingPreferences::resolveUi(
            self::preferencesForUser($userId),
        );
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
        return UserSettingPreferences::allowedDateFormats();
    }

    /**
     * @return list<string>
     */
    public static function allowedTimeFormats(): array
    {
        return UserSettingPreferences::allowedTimeFormats();
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
}
