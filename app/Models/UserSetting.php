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
        $defaults = self::defaultPreferences()['time_tracking']['rounding'];
        $defaultEnabled = (bool) ($defaults['enabled'] ?? true);
        $defaultMode = (string) ($defaults['mode'] ?? 'ceil');
        $defaultIntervalMinutes = (int) ($defaults['interval_minutes'] ?? 15);
        $defaultMinimumMinutes = (int) ($defaults['minimum_minutes'] ?? 1);

        if ($userId === null) {
            return [
                'enabled' => $defaultEnabled,
                'mode' => $defaultMode,
                'interval_minutes' => $defaultIntervalMinutes,
                'minimum_minutes' => $defaultMinimumMinutes,
            ];
        }

        $settings = self::ensureForUser($userId);
        $preferences = $settings->preferences;

        return [
            'enabled' => (bool) data_get($preferences, 'time_tracking.rounding.enabled', $defaultEnabled),
            'mode' => (string) data_get($preferences, 'time_tracking.rounding.mode', $defaultMode),
            'interval_minutes' => (int) data_get($preferences, 'time_tracking.rounding.interval_minutes', $defaultIntervalMinutes),
            'minimum_minutes' => (int) data_get($preferences, 'time_tracking.rounding.minimum_minutes', $defaultMinimumMinutes),
        ];
    }
}
