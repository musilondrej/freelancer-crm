<?php

namespace App\Support\Filament;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Utilities\Get;

class Currency
{
    public static function defaultCode(): string
    {
        return (string) data_get(Filament::auth()->user(), 'default_currency', 'CZK');
    }

    public static function resolve(Get $get, string $field = 'currency'): string
    {
        $value = $get($field);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return self::defaultCode();
    }
}
