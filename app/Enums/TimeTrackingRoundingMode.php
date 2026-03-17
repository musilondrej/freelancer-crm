<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TimeTrackingRoundingMode: string implements HasLabel
{
    case RoundUp = 'ceil';
    case RoundToNearest = 'nearest';
    case RoundDown = 'floor';

    public function getLabel(): string
    {
        return match ($this) {
            self::RoundUp => 'Round up',
            self::RoundToNearest => 'Round to nearest',
            self::RoundDown => 'Round down',
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
