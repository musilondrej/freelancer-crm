<?php

namespace App\Support;

use App\Enums\Currency;

final class CurrencyConverter
{
    /**
     * @return array<string, float>
     */
    public static function rates(): array
    {
        $rates = [];

        foreach (Currency::cases() as $currency) {
            $rates[$currency->value] = $currency->fxRate();
        }

        return $rates;
    }

    public static function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $from = Currency::tryFrom(strtoupper($fromCurrency));
        $to = Currency::tryFrom(strtoupper($toCurrency));

        if ($from === null || $to === null) {
            return $amount;
        }

        return Currency::convert($amount, $from, $to);
    }

    public static function symbol(string $currency): string
    {
        return Currency::tryFrom(strtoupper($currency))?->symbol() ?? strtoupper($currency);
    }
}
