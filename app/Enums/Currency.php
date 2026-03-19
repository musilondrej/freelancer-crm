<?php

namespace App\Enums;

use Filament\Facades\Filament;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Number;

enum Currency: string implements HasLabel
{
    // Major global currencies
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case NZD = 'NZD';
    case CNY = 'CNY';
    case INR = 'INR';

    // EU member states (non-euro)
    case CZK = 'CZK';
    case PLN = 'PLN';
    case HUF = 'HUF';
    case SEK = 'SEK';
    case DKK = 'DKK';
    case RON = 'RON';
    case BGN = 'BGN';

    // Other commonly used
    case NOK = 'NOK';
    case TRY = 'TRY';
    case BRL = 'BRL';
    case MXN = 'MXN';
    case ZAR = 'ZAR';
    case KRW = 'KRW';
    case SGD = 'SGD';
    case HKD = 'HKD';
    case ILS = 'ILS';
    case AED = 'AED';
    case THB = 'THB';
    case UAH = 'UAH';
    case RSD = 'RSD';

    public function getLabel(): string
    {
        return $this->value.' ('.$this->symbol().')';
    }

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::USD, self::CAD, self::AUD, self::NZD, self::HKD, self::SGD, self::MXN => '$',
            self::GBP => '£',
            self::CHF => 'Fr.',
            self::JPY, self::CNY => '¥',
            self::INR => '₹',
            self::CZK => 'Kč',
            self::PLN => 'zł',
            self::HUF => 'Ft',
            self::SEK, self::DKK, self::NOK => 'kr',
            self::RON => 'lei',
            self::BGN => 'лв',
            self::TRY => '₺',
            self::BRL => 'R$',
            self::ZAR => 'R',
            self::KRW => '₩',
            self::ILS => '₪',
            self::AED => 'د.إ',
            self::THB => '฿',
            self::UAH => '₴',
            self::RSD => 'din.',
        };
    }

    public function symbolPosition(): string
    {
        return match ($this) {
            self::CZK, self::PLN, self::HUF, self::SEK, self::DKK, self::NOK,
            self::RON, self::BGN, self::TRY, self::RSD, self::UAH,
            self::CHF, self::AED, self::THB => 'suffix',
            default => 'prefix',
        };
    }

    /**
     * Approximate FX rate to EUR (base currency). Update periodically.
     */
    public function fxRate(): float
    {
        return match ($this) {
            self::EUR => 1.0,
            self::USD => 1.09,
            self::GBP => 0.86,
            self::CHF => 0.97,
            self::JPY => 163.0,
            self::CAD => 1.47,
            self::AUD => 1.66,
            self::NZD => 1.79,
            self::CNY => 7.85,
            self::INR => 90.50,
            self::CZK => 25.30,
            self::PLN => 4.32,
            self::HUF => 395.0,
            self::SEK => 11.20,
            self::DKK => 7.46,
            self::RON => 4.98,
            self::BGN => 1.96,
            self::NOK => 11.50,
            self::TRY => 35.0,
            self::BRL => 5.40,
            self::MXN => 18.80,
            self::ZAR => 19.80,
            self::KRW => 1430.0,
            self::SGD => 1.46,
            self::HKD => 8.50,
            self::ILS => 3.95,
            self::AED => 4.00,
            self::THB => 37.50,
            self::UAH => 44.50,
            self::RSD => 117.0,
        };
    }

    /**
     * Format a numeric amount with this currency's symbol.
     */
    public function format(float $amount): string
    {
        return Number::currency(
            $amount,
            in: $this->value,
            locale: app()->getLocale(),
            precision: 0,
        );
    }

    /**
     * Format amount with currency code as suffix (e.g. "150.00 CZK").
     */
    public function formatWithCode(float $amount): string
    {
        return Number::currency(
            $amount,
            in: $this->value,
            locale: app()->getLocale(),
            precision: 2,
        );
    }

    /**
     * Resolve the currency code from a Filament form field, falling back to user default.
     */
    public static function resolveFromForm(Get $get, string $field = 'currency'): string
    {
        $value = $get($field);

        if ($value instanceof self) {
            return $value->value;
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return self::userDefault()->value;
    }

    public static function userDefault(): self
    {
        $code = data_get(Filament::auth()->user(), 'default_currency', 'CZK');

        return self::tryFrom((string) $code) ?? self::CZK;
    }

    /**
     * @param  list<self>|null  $currencies
     * @return array<string, string>
     */
    public static function options(?array $currencies = null): array
    {
        $options = [];

        foreach ($currencies ?? self::cases() as $currency) {
            $options[$currency->value] = $currency->getLabel();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function dashboardOptions(): array
    {
        return self::options([
            self::CZK,
            self::EUR,
            self::USD,
        ]);
    }

    public static function convert(float $amount, self $from, self $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $amountInEur = $amount / $from->fxRate();

        return $amountInEur * $to->fxRate();
    }
}
