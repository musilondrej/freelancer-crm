<?php

use App\Support\CurrencyConverter;

it('returns rates from Currency enum', function (): void {
    $rates = CurrencyConverter::rates();

    expect($rates)->toHaveKeys(['EUR', 'USD', 'CZK'])
        ->and($rates['EUR'])->toBe(1.0);
});

it('converts between currencies through EUR base', function (): void {
    $eurToCzk = CurrencyConverter::convert(100, 'EUR', 'CZK');

    expect($eurToCzk)->toBe(2530.0);
});

it('returns original amount when currency is unknown', function (): void {
    expect(CurrencyConverter::convert(500, 'GBP', 'EUR'))->toBe(500.0)
        ->and(CurrencyConverter::convert(500, 'EUR', 'GBP'))->toBe(500.0);
});

it('returns symbol from Currency enum', function (): void {
    expect(CurrencyConverter::symbol('eur'))->toBe('€')
        ->and(CurrencyConverter::symbol('CZK'))->toBe('Kč')
        ->and(CurrencyConverter::symbol('usd'))->toBe('$')
        ->and(CurrencyConverter::symbol('GBP'))->toBe('GBP');
});
