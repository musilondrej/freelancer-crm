<?php

namespace App\Enums\Profile;

use Filament\Support\Contracts\HasLabel;

enum TimeFormatEnum: string implements HasLabel
{
    case HourMinute24h = 'H:i';
    case HourMinuteSecond24h = 'H:i:s';
    case HourMinute12h = 'h:i A';
    case HourMinuteSecond12h = 'h:i:s A';

    public function getLabel(): string
    {
        return match ($this) {
            self::HourMinute24h => '23:45 (H:i)',
            self::HourMinuteSecond24h => '23:45:30 (H:i:s)',
            self::HourMinute12h => '11:45 PM (h:i A)',
            self::HourMinuteSecond12h => '11:45:30 PM (h:i:s A)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
