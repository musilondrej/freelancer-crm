<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\CurrencyConverter;
use Filament\Facades\Filament;

trait InteractsWithCurrencyConversion
{
    private function resolveDisplayCurrency(): string
    {
        $rawCurrency = $this->pageFilters['currency'] ?? null;
        $selectedCurrency = is_string($rawCurrency)
            ? strtoupper($rawCurrency)
            : null;
        $rates = CurrencyConverter::rates();

        if ($selectedCurrency !== null && array_key_exists($selectedCurrency, $rates)) {
            return $selectedCurrency;
        }

        $defaultCurrency = strtoupper((string) (Filament::auth()->user()->default_currency ?? 'CZK'));

        if (array_key_exists($defaultCurrency, $rates)) {
            return $defaultCurrency;
        }

        return array_key_first($rates) ?? 'EUR';
    }

    private function convertAmount(float $amount, ?string $fromCurrency, ?string $toCurrency = null): float
    {
        $normalizedFromCurrency = strtoupper((string) $fromCurrency);
        $normalizedToCurrency = strtoupper((string) ($toCurrency ?? $this->resolveDisplayCurrency()));
        $rates = CurrencyConverter::rates();

        if (! array_key_exists($normalizedFromCurrency, $rates) || ! array_key_exists($normalizedToCurrency, $rates)) {
            return $amount;
        }

        return CurrencyConverter::convert($amount, $normalizedFromCurrency, $normalizedToCurrency);
    }

    private function formatAmountWithCurrency(float $amount, ?string $currency = null): string
    {
        $normalizedCurrency = strtoupper((string) ($currency ?? $this->resolveDisplayCurrency()));
        $symbol = $this->currencySymbol($normalizedCurrency);
        $formattedAmount = $this->formatCurrencyDecimal($amount);

        if ($symbol === $normalizedCurrency) {
            return $formattedAmount.' '.$normalizedCurrency;
        }

        return match ($normalizedCurrency) {
            'CZK' => $formattedAmount.' '.$symbol,
            default => $symbol.' '.$formattedAmount,
        };
    }

    private function formatCurrencyDecimal(float $amount): string
    {
        return number_format(round($amount), 0, '.', ' ');
    }

    private function currencySymbol(string $currency): string
    {
        return CurrencyConverter::symbol($currency);
    }
}
