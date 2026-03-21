<?php

namespace App\Support;

use BackedEnum;

final class EnumValue
{
    public static function from(mixed $value): string
    {
        return $value instanceof BackedEnum ? $value->value : (string) $value;
    }
}
