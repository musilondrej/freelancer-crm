<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TimeTrackingRoundingInterval: int implements HasLabel
{
    case OneMinute = 1;
    case FiveMinutes = 5;
    case SixMinutes = 6;
    case TenMinutes = 10;
    case TwelveMinutes = 12;
    case FifteenMinutes = 15;
    case TwentyMinutes = 20;
    case ThirtyMinutes = 30;
    case SixtyMinutes = 60;

    public function getLabel(): string
    {
        return sprintf('%d min', $this->value);
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
