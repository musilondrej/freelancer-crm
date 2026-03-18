<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectPricingModel: string implements HasColor, HasIcon, HasLabel
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
    case Retainer = 'retainer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed price'),
            self::Hourly => __('Hourly rate'),
            self::Retainer => __('Retainer'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fixed => 'primary',
            self::Hourly => 'info',
            self::Retainer => 'success',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Fixed => Heroicon::OutlinedCurrencyDollar,
            self::Hourly => Heroicon::OutlinedClock,
            self::Retainer => Heroicon::OutlinedReceiptPercent,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
