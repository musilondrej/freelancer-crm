<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectActivityType: string implements HasColor, HasIcon, HasLabel
{
    case Hourly = 'hourly';
    case OneTime = 'one_time';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => __('Hourly'),
            self::OneTime => __('One Time'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hourly => 'info',
            self::OneTime => 'primary',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Hourly => Heroicon::OutlinedClock,
            self::OneTime => Heroicon::OutlinedBanknotes,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
