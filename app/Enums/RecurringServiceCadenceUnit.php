<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RecurringServiceCadenceUnit: string implements HasColor, HasIcon, HasLabel
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    public function getLabel(): string
    {
        return match ($this) {
            self::Week => 'Week',
            self::Month => 'Month',
            self::Quarter => 'Quarter',
            self::Year => 'Year',
        };
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Week => Heroicon::OutlinedCalendarDays,
            self::Month => Heroicon::OutlinedCalendar,
            self::Quarter => Heroicon::OutlinedCalendarDays,
            self::Year => Heroicon::OutlinedCalendar,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
