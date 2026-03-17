<?php

use App\Support\CurrencyConverter;

it('returns rates from Currency enum', function (): void {
    $rates = CurrencyConverter::rates();

    expect($rates)->toHaveKeys(['EUR', 'USD', 'CZK', 'GBP', 'PLN'])
        ->and($rates['EUR'])->toBe(1.0);
});

it('converts between currencies through EUR base', function (): void {
    $eurToCzk = CurrencyConverter::convert(100, 'EUR', 'CZK');

    expect($eurToCzk)->toBe(2530.0);
});

it('converts between non-EUR currencies through EUR base', function (): void {
    $usdToGbp = CurrencyConverter::convert(109, 'USD', 'GBP');

    expect(round($usdToGbp, 2))->toBe(86.0);
});

it('returns original amount when currency is unknown', function (): void {
    expect(CurrencyConverter::convert(500, 'XYZ', 'EUR'))->toBe(500.0)
        ->and(CurrencyConverter::convert(500, 'EUR', 'XYZ'))->toBe(500.0);
});

it('returns symbol from Currency enum', function (): void {
    expect(CurrencyConverter::symbol('eur'))->toBe('€')
        ->and(CurrencyConverter::symbol('CZK'))->toBe('Kč')
        ->and(CurrencyConverter::symbol('usd'))->toBe('$')
        ->and(CurrencyConverter::symbol('GBP'))->toBe('£')
        ->and(CurrencyConverter::symbol('PLN'))->toBe('zł')
        ->and(CurrencyConverter::symbol('XYZ'))->toBe('XYZ');
});
