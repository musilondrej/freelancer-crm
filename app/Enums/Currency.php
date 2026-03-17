<?php

namespace App\Enums;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case CZK = 'CZK';
    case EUR = 'EUR';
    case USD = 'USD';

    public function getLabel(): string
    {
        return match ($this) {
            self::CZK => 'CZK (Kč)',
            self::EUR => 'EUR (€)',
            self::USD => 'USD ($)',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::CZK => 'Kč',
            self::EUR => '€',
            self::USD => '$',
        };
    }

    public function symbolPosition(): string
    {
        return match ($this) {
            self::CZK => 'suffix',
            self::EUR, self::USD => 'prefix',
        };
    }

    public function fxRate(): float
    {
        return match ($this) {
            self::EUR => 1.0,
            self::USD => 1.09,
            self::CZK => 25.30,
        };
    }

    /**
     * Format a numeric amount with this currency's symbol.
     */
    public function format(float $amount): string
    {
        $formatted = number_format(round($amount), 0, '.', ' ');

        return match ($this->symbolPosition()) {
            'prefix' => $this->symbol().' '.$formatted,
            default => $formatted.' '.$this->symbol(),
        };
    }

    /**
     * Format amount with currency code as suffix (e.g. "150.00 CZK").
     */
    public function formatWithCode(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return $formatted.' '.$this->value;
    }

    /**
     * Resolve the currency code from a Filament form field, falling back to user default.
     */
    public static function resolveFromForm(Get $get, string $field = 'currency'): string
    {
        $value = $get($field);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return self::userDefault()->value;
    }

    public static function userDefault(): self
    {
        $code = data_get(Filament::auth()->user(), 'default_currency', 'CZK');

        return self::tryFrom((string) $code) ?? self::CZK;
    }

    public static function convert(float $amount, self $from, self $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $amountInEur = $amount / $from->fxRate();

        return $amountInEur * $to->fxRate();
    }
}
