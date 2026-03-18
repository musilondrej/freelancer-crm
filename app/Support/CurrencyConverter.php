<?php

namespace App\Support;

use App\Enums\Currency;
use Illuminate\Support\Number;

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

    public static function format(float $amount, ?string $currency, int $precision = 2): string
    {
        $resolvedCurrency = is_string($currency) && trim($currency) !== ''
            ? strtoupper($currency)
            : Currency::userDefault()->value;

        $resolvedCurrencyEnum = Currency::tryFrom($resolvedCurrency);
        $code = $resolvedCurrencyEnum instanceof Currency
            ? $resolvedCurrencyEnum->value
            : $resolvedCurrency;

        return Number::currency(
            $amount,
            in: $code,
            locale: app()->getLocale(),
            precision: $precision,
        );
    }
}
