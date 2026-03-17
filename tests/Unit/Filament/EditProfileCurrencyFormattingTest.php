<?php

use App\Enums\Currency;
use App\Filament\Pages\Auth\EditProfile;

test('formats enum currency in profile snapshot without casting error', function (): void {
    $page = new class extends EditProfile
    {
        public function exposeFormatCurrency(mixed $value): string
        {
            return $this->formatCurrency($value);
        }
    };

    expect($page->exposeFormatCurrency(Currency::CZK))->toBe('CZK');
});

test('formats non-empty string currency in profile snapshot', function (): void {
    $page = new class extends EditProfile
    {
        public function exposeFormatCurrency(mixed $value): string
        {
            return $this->formatCurrency($value);
        }
    };

    expect($page->exposeFormatCurrency('EUR'))->toBe('EUR');
});

test('returns fallback label when profile currency is missing', function (): void {
    $page = new class extends EditProfile
    {
        public function exposeFormatCurrency(mixed $value): string
        {
            return $this->formatCurrency($value);
        }
    };

    expect($page->exposeFormatCurrency(null))->toBe(__('Not set'));
});
