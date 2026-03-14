<?php

namespace App\Support;

use App\Models\UserSetting;

final class TimeTrackingRounding
{
    /**
     * @var array<int, array{enabled: bool, mode: string, interval: int, minimum: int}>
     */
    private static array $ownerSettingsCache = [];

    public static function roundMinutes(int $rawMinutes, ?int $ownerId = null): int
    {
        $settings = self::settings($ownerId);
        $minimumMinutes = max($settings['minimum'], 0);
        $normalizedMinutes = max($rawMinutes, $minimumMinutes);

        if (! $settings['enabled']) {
            return $normalizedMinutes;
        }

        $interval = max($settings['interval'], 1);

        if ($interval === 1) {
            return $normalizedMinutes;
        }

        $rounded = match ($settings['mode']) {
            'floor' => (int) (floor($normalizedMinutes / $interval) * $interval),
            'nearest' => (int) (round($normalizedMinutes / $interval) * $interval),
            default => (int) (ceil($normalizedMinutes / $interval) * $interval),
        };

        return max($rounded, $minimumMinutes);
    }

    /**
     * @return array{enabled: bool, mode: string, interval: int, minimum: int}
     */
    private static function settings(?int $ownerId): array
    {
        $fallback = [
            'enabled' => (bool) config('crm.time_tracking.rounding.enabled', true),
            'mode' => (string) config('crm.time_tracking.rounding.mode', 'ceil'),
            'interval' => (int) config('crm.time_tracking.rounding.interval_minutes', 15),
            'minimum' => (int) config('crm.time_tracking.rounding.minimum_minutes', 1),
        ];

        if ($ownerId === null) {
            return $fallback;
        }

        if (array_key_exists($ownerId, self::$ownerSettingsCache)) {
            return self::$ownerSettingsCache[$ownerId];
        }

        $rounding = UserSetting::roundingForUser($ownerId);
        $settings = [
            'enabled' => (bool) $rounding['enabled'],
            'mode' => (string) $rounding['mode'],
            'interval' => (int) $rounding['interval_minutes'],
            'minimum' => (int) $rounding['minimum_minutes'],
        ];

        self::$ownerSettingsCache[$ownerId] = $settings;

        return $settings;
    }
}
