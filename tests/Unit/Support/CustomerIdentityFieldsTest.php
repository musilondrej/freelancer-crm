<?php

use App\Support\CustomerIdentityFields;

it('uses globally understandable labels for customer business identifiers', function (): void {
    expect(CustomerIdentityFields::registrationNumberLabel())->toBe('Registration number')
        ->and(CustomerIdentityFields::primaryTaxIdLabel())->toBe('Tax ID / VAT number');
});

it('provides global helper text examples for customer business identifiers', function (): void {
    expect(CustomerIdentityFields::registrationNumberHelperText())->toContain('company number')
        ->and(CustomerIdentityFields::registrationNumberHelperText())->toContain('EIN')
        ->and(CustomerIdentityFields::registrationNumberHelperText())->toContain('ABN')
        ->and(CustomerIdentityFields::primaryTaxIdHelperText())->toContain('VAT')
        ->and(CustomerIdentityFields::primaryTaxIdHelperText())->toContain('GST');
});
