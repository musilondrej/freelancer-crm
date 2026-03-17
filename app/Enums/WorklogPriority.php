<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorklogPriority: int implements HasColor, HasLabel
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Highest = 4;
    case Blocker = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Highest => 'Highest',
            self::Blocker => 'Blocker',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Highest => 'danger',
            self::Blocker => 'danger',
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
