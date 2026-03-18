<?php

use App\Enums\LeadStatus;

it('returns correct open lead statuses', function (): void {
    expect(LeadStatus::openValues())->toBe([
        LeadStatus::New->value,
        LeadStatus::Contacted->value,
        LeadStatus::Qualified->value,
        LeadStatus::Proposal->value,
    ]);
});

it('implements Filament contracts', function (): void {
    foreach (LeadStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getColor())->toBeString()
            ->and($case->getIcon())->not->toBeNull();
    }
});
