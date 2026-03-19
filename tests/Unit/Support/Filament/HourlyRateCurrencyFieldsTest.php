<?php

use App\Support\Filament\HourlyRateCurrencyFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

it('builds reusable currency and hourly rate components', function (): void {
    $components = HourlyRateCurrencyFields::make(
        currencyField: 'billing_currency',
        rateField: 'hourly_rate_override',
    );

    expect($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(Select::class)
        ->and($components[0]->getName())->toBe('billing_currency')
        ->and($components[1])->toBeInstanceOf(TextInput::class)
        ->and($components[1]->getName())->toBe('hourly_rate_override');
});
