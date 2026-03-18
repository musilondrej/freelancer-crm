<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Priority: int implements HasColor, HasLabel
{
    case Backlog = 1;
    case Low = 2;
    case Normal = 3;
    case High = 4;
    case Critical = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => __('Backlog'),
            self::Low => __('Low'),
            self::Normal => __('Normal'),
            self::High => __('High'),
            self::Critical => __('Critical'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog => 'gray',
            self::Low => 'info',
            self::Normal => 'primary',
            self::High => 'warning',
            self::Critical => 'danger',
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
