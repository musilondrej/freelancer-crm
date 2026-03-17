<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Currency;

trait InteractsWithCurrencyConversion
{
    private function resolveDisplayCurrency(): Currency
    {
        $rawCurrency = $this->pageFilters['currency'] ?? null;

        if (is_string($rawCurrency)) {
            $selected = Currency::tryFrom(strtoupper($rawCurrency));

            if ($selected !== null) {
                return $selected;
            }
        }

        return Currency::userDefault();
    }

    private function convertAmount(float $amount, Currency|string|null $fromCurrency, Currency|string|null $toCurrency = null): float
    {
        $from = $fromCurrency instanceof Currency
            ? $fromCurrency
            : Currency::tryFrom(strtoupper((string) $fromCurrency));

        $to = $toCurrency instanceof Currency
            ? $toCurrency
            : ($toCurrency !== null
                ? Currency::tryFrom(strtoupper($toCurrency))
                : $this->resolveDisplayCurrency());

        if ($from === null || $to === null) {
            return $amount;
        }

        return Currency::convert($amount, $from, $to);
    }

    private function formatAmountWithCurrency(float $amount, Currency|string|null $currency = null): string
    {
        $resolved = match (true) {
            $currency instanceof Currency => $currency,
            is_string($currency) => Currency::tryFrom(strtoupper($currency)) ?? $this->resolveDisplayCurrency(),
            default => $this->resolveDisplayCurrency(),
        };

        return $resolved->format($amount);
    }

    private function currencySymbol(Currency|string|null $currency = null): string
    {
        $resolved = match (true) {
            $currency instanceof Currency => $currency,
            is_string($currency) => Currency::tryFrom(strtoupper($currency)) ?? $this->resolveDisplayCurrency(),
            default => $this->resolveDisplayCurrency(),
        };

        return $resolved->symbol();
    }
}
