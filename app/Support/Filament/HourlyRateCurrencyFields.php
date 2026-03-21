<?php

namespace App\Support\Filament;

use App\Enums\Currency;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

class HourlyRateCurrencyFields
{
    /**
     * @param  bool|Closure(Get): bool  $currencyRequired
     * @param  bool|Closure(Get): bool  $rateRequired
     * @param  Closure(Get): bool|null  $currencyVisible
     * @param  Closure(Get): bool|null  $rateVisible
     * @return array<int, Component>
     */
    public static function make(
        string $currencyField = 'currency',
        string $rateField = 'hourly_rate',
        string $currencyLabel = 'Currency',
        string $rateLabel = 'Hourly rate',
        bool|Closure $currencyRequired = true,
        bool|Closure $rateRequired = true,
        ?Closure $currencyVisible = null,
        ?Closure $rateVisible = null,
        ?string $helperText = null,
    ): array {
        $currency = Select::make($currencyField)
            ->label(__($currencyLabel))
            ->options(Currency::class)
            ->required($currencyRequired)
            ->live();

        if ($currencyVisible instanceof Closure) {
            $currency->visible($currencyVisible);
        }

        $rate = TextInput::make($rateField)
            ->label(__($rateLabel))
            ->numeric()
            ->minValue(0)
            ->required($rateRequired)
            ->suffix(fn (Get $get): string => Currency::resolveFromForm($get, $currencyField));

        if ($helperText !== null) {
            $rate->helperText(__($helperText));
        }

        if ($rateVisible instanceof Closure) {
            $rate->visible($rateVisible);
        }

        return [$currency, $rate];
    }
}
