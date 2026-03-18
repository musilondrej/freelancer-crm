<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserSettingWeekStartsOn: string implements HasLabel
{
    case Monday = 'monday';
    case Sunday = 'sunday';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monday => __('Monday'),
            self::Sunday => __('Sunday'),
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
