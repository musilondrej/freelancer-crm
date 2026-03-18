<?php

use App\Enums\Currency;

it('builds options for selected currencies', function (): void {
    expect(Currency::options([Currency::CZK, Currency::EUR]))->toBe([
        'CZK' => Currency::CZK->getLabel(),
        'EUR' => Currency::EUR->getLabel(),
    ]);
});

it('builds dashboard options from the supported subset', function (): void {
    expect(Currency::dashboardOptions())->toBe([
        'CZK' => Currency::CZK->getLabel(),
        'EUR' => Currency::EUR->getLabel(),
        'USD' => Currency::USD->getLabel(),
    ]);
});
