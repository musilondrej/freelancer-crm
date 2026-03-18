<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserSettingLocale: string implements HasLabel
{
    case English = 'en';
    case Czech = 'cs';

    public function getLabel(): string
    {
        return match ($this) {
            self::English => __('English'),
            self::Czech => __('Czech'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
