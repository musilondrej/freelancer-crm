<?php

namespace App\Models\Concerns;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait FormatsHourlyRate
{
    protected function hourlyRateWithCurrency(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $hourlyRate = $attributes['hourly_rate'] ?? null;

                if ($hourlyRate === null) {
                    return null;
                }

                $currency = Currency::tryFrom((string) ($attributes[$this->hourlyCurrencyColumn()] ?? ''));

                return $currency !== null
                    ? $currency->formatWithCode((float) $hourlyRate)
                    : Currency::userDefault()->formatWithCode((float) $hourlyRate);
            },
        );
    }

    protected function hourlyCurrencyColumn(): string
    {
        return 'currency';
    }
}
