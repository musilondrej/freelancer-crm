<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum RecurringServiceType: string implements HasColor, HasIcon, HasLabel
{
    case Hosting = 'hosting';
    case Domain = 'domain';
    case Maintenance = 'maintenance';
    case Support = 'support';
    case License = 'license';
    case Retainer = 'retainer';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting',
            self::Domain => 'Domain',
            self::Maintenance => 'Maintenance',
            self::Support => 'Support',
            self::License => 'License',
            self::Retainer => 'Retainer',
            self::Other => 'Other',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hosting => 'info',
            self::Domain => 'primary',
            self::Maintenance => 'warning',
            self::Support => 'success',
            self::License => 'gray',
            self::Retainer => 'success',
            self::Other => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Hosting => Heroicon::OutlinedServerStack,
            self::Domain => Heroicon::OutlinedGlobeAlt,
            self::Maintenance => Heroicon::OutlinedWrenchScrewdriver,
            self::Support => Heroicon::OutlinedLifebuoy,
            self::License => Heroicon::OutlinedDocumentCheck,
            self::Retainer => Heroicon::OutlinedReceiptPercent,
            self::Other => Heroicon::OutlinedRectangleStack,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
