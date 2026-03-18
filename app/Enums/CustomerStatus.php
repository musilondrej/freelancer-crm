<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum CustomerStatus: string implements HasColor, HasIcon, HasLabel
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lead => __('Lead'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Lead => 'warning',
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Lead => Heroicon::OutlinedBolt,
            self::Active => Heroicon::OutlinedCheckCircle,
            self::Inactive => Heroicon::OutlinedPauseCircle,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
