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
            self::RoundUp => __('Round up'),
            self::RoundToNearest => __('Round to nearest'),
            self::RoundDown => __('Round down'),
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
