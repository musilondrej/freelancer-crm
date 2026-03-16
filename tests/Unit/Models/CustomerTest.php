<?php

use App\Models\Customer;

it('allows mass assignment for registration number instead of the old company id field', function (): void {
    $customer = new Customer;

    expect($customer->isFillable('registration_number'))->toBeTrue()
        ->and($customer->isFillable('company_id'))->toBeFalse();
});
