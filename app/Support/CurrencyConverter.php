<?php

namespace App\Support;

final class CurrencyConverter
{
    /**
     * @return array<string, float>
     */
    public static function rates(): array
    {
        $configuredRates = config('crm.fx.rates', []);

        if (! is_array($configuredRates) || $configuredRates === []) {
            return [
                'EUR' => 1.0,
                'USD' => 1.09,
                'CZK' => 25.30,
            ];
        }

        $rates = [];

        foreach ($configuredRates as $currency => $rate) {
            if (! is_string($currency)) {
                continue;
            }

            if (! is_numeric($rate)) {
                continue;
            }

            $rates[strtoupper($currency)] = (float) $rate;
        }

        return $rates !== [] ? $rates : ['EUR' => 1.0];
    }

    public static function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $rates = self::rates();
        $normalizedFrom = strtoupper($fromCurrency);
        $normalizedTo = strtoupper($toCurrency);

        if (! array_key_exists($normalizedFrom, $rates) || ! array_key_exists($normalizedTo, $rates)) {
            return $amount;
        }

        $amountInEur = $amount / $rates[$normalizedFrom];

        return $amountInEur * $rates[$normalizedTo];
    }

    public static function symbol(string $currency): string
    {
        $symbols = config('crm.fx.symbols', []);
        $normalizedCurrency = strtoupper($currency);

        if (is_array($symbols) && array_key_exists($normalizedCurrency, $symbols) && is_string($symbols[$normalizedCurrency])) {
            return $symbols[$normalizedCurrency];
        }

        return $normalizedCurrency;
    }
}
