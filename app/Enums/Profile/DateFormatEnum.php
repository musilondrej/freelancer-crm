<?php

namespace App\Enums\Profile;

use Filament\Support\Contracts\HasLabel;

enum DateFormatEnum: string implements HasLabel
{
    case EuropeanDot = 'd. m. Y';
    case Iso = 'Y-m-d';
    case EuropeanSlash = 'd/m/Y';
    case AmericanSlash = 'm/d/Y';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::EuropeanDot => '31. 12. 2026 (d. m. Y)',
            self::Iso => '2026-12-31 (Y-m-d)',
            self::EuropeanSlash => '31/12/2026 (d/m/Y)',
            self::AmericanSlash => '12/31/2026 (m/d/Y)',
        };
    }
}
