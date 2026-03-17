<?php

namespace App\Support;

class TimeDuration
{
    private const int MINUTES_PER_HOUR = 60;

    private const int MINUTES_PER_DAY = 480;

    private const int MINUTES_PER_WEEK = 2400;

    /**
     * Parse a Jira-style duration string into minutes.
     *
     * Supported: "30m", "2h", "1d", "1w", "2h30m", "1d4h", "1d 2h 30m"
     * Also accepts plain numbers as minutes: "90" → 90
     */
    public static function toMinutes(string $input): ?int
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (is_numeric($input)) {
            return (int) $input;
        }

        $normalized = strtolower(preg_replace('/\s+/', '', $input) ?? $input);
        $totalMinutes = 0;
        $matched = false;

        if (preg_match_all('/(\d+(?:\.\d+)?)(w|d|h|m)/', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = (float) $match[1];
                $matched = true;

                $totalMinutes += (int) match ($match[2]) {
                    'w' => $value * self::MINUTES_PER_WEEK,
                    'd' => $value * self::MINUTES_PER_DAY,
                    'h' => $value * self::MINUTES_PER_HOUR,
                    'm' => $value,
                };
            }
        }

        return $matched ? $totalMinutes : null;
    }

    /**
     * Format minutes into a human-readable Jira-style duration.
     *
     * Examples: 30 → "30m", 60 → "1h", 150 → "2h 30m", 480 → "1d", 570 → "1d 1h 30m"
     */
    public static function format(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $remaining = $minutes;
        $parts = [];

        if ($remaining >= self::MINUTES_PER_WEEK) {
            $weeks = intdiv($remaining, self::MINUTES_PER_WEEK);
            $remaining -= $weeks * self::MINUTES_PER_WEEK;
            $parts[] = $weeks.'w';
        }

        if ($remaining >= self::MINUTES_PER_DAY) {
            $days = intdiv($remaining, self::MINUTES_PER_DAY);
            $remaining -= $days * self::MINUTES_PER_DAY;
            $parts[] = $days.'d';
        }

        if ($remaining >= self::MINUTES_PER_HOUR) {
            $hours = intdiv($remaining, self::MINUTES_PER_HOUR);
            $remaining -= $hours * self::MINUTES_PER_HOUR;
            $parts[] = $hours.'h';
        }

        if ($remaining > 0) {
            $parts[] = $remaining.'m';
        }

        return implode(' ', $parts);
    }
}
