<?php

namespace App\Support;

class CustomerIdentityFields
{
    public static function registrationNumberLabel(): string
    {
        return 'Registration number';
    }

    public static function registrationNumberHelperText(): string
    {
        return 'Use the local business registration number, company number, EIN, ABN, ICO, or a similar identifier.';
    }

    public static function primaryTaxIdLabel(): string
    {
        return 'Tax ID / VAT number';
    }

    public static function primaryTaxIdHelperText(): string
    {
        return 'Use the primary tax identifier used on invoices, such as VAT, GST, sales tax registration, or tax ID.';
    }
}
