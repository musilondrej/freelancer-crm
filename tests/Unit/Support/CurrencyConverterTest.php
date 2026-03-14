<?php

use App\Support\CurrencyConverter;

it('returns sanitized configured rates', function (): void {
    config()->set('crm.fx.rates', [
        'eur' => '1',
        'usd' => 1.1,
        'czk' => '25.5',
        'invalid' => 'not-a-number',
        10 => 2,
    ]);

    expect(CurrencyConverter::rates())->toBe([
        'EUR' => 1.0,
        'USD' => 1.1,
        'CZK' => 25.5,
    ]);
});

it('returns fallback rates when configuration is invalid', function (): void {
    config()->set('crm.fx.rates', 'invalid');

    expect(CurrencyConverter::rates())->toBe([
        'EUR' => 1.0,
        'USD' => 1.09,
        'CZK' => 25.3,
    ]);
});

it('converts between configured currencies through base rates', function (): void {
    config()->set('crm.fx.rates', [
        'EUR' => 1.0,
        'USD' => 1.25,
        'CZK' => 25.0,
    ]);

    expect(CurrencyConverter::convert(125, 'USD', 'EUR'))->toBe(100.0)
        ->and(CurrencyConverter::convert(100, 'EUR', 'CZK'))->toBe(2500.0);
});

it('returns original amount when conversion currencies are unknown', function (): void {
    config()->set('crm.fx.rates', [
        'EUR' => 1.0,
        'USD' => 1.2,
    ]);

    expect(CurrencyConverter::convert(500, 'GBP', 'EUR'))->toBe(500.0)
        ->and(CurrencyConverter::convert(500, 'EUR', 'GBP'))->toBe(500.0);
});

it('returns symbol from config or currency code fallback', function (): void {
    config()->set('crm.fx.symbols', [
        'EUR' => '€',
        'USD' => '$',
    ]);

    expect(CurrencyConverter::symbol('eur'))->toBe('€')
        ->and(CurrencyConverter::symbol('CZK'))->toBe('CZK');
});
