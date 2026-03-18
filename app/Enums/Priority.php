<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

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
            self::Low => 'info',
            self::Normal => 'primary',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-trending-down',
            self::Normal => 'heroicon-o-arrow-trending-up',
            self::High => 'heroicon-o-fire',
            self::Critical => 'heroicon-o-exclamation-triangle',
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
