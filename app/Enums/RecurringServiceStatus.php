<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RecurringServiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Paused => Heroicon::OutlinedPauseCircle,
            self::Cancelled => Heroicon::OutlinedNoSymbol,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
