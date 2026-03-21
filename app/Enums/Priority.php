<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum Priority: int implements HasColor, HasIcon, HasLabel
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
    case Critical = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Normal => __('Normal'),
            self::High => __('High'),
            self::Critical => __('Critical'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Low => Heroicon::ChevronDown,
            self::Normal => Heroicon::Minus,
            self::High => Heroicon::ChevronUp,
            self::Critical => Heroicon::Fire,
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function defaultCase(): self
    {
        return self::Normal;
    }
}
