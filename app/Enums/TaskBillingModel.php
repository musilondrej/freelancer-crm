<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskBillingModel: string implements HasColor, HasIcon, HasLabel
{
    case Hourly = 'hourly';
    case FixedPrice = 'fixed_price';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => __('Hourly'),
            self::FixedPrice => __('Fixed Price'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hourly => 'info',
            self::FixedPrice => 'primary',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Hourly => Heroicon::OutlinedClock,
            self::FixedPrice => Heroicon::OutlinedBanknotes,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
