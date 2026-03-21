<?php

namespace App\Support;

final readonly class FinancialDefaults
{
    public function __construct(
        public ?string $currency,
        public ?float $hourlyRate,
    ) {}
}
