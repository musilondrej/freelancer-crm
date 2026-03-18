<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RecurringServiceBillingModel: string implements HasColor, HasIcon, HasLabel
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed'),
            self::Hourly => __('Hourly'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fixed => 'primary',
            self::Hourly => 'info',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Fixed => Heroicon::OutlinedCurrencyDollar,
            self::Hourly => Heroicon::OutlinedClock,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
