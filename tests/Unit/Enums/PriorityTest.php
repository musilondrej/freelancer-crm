<?php

use App\Enums\Priority;

it('returns correct static collections', function (): void {
    expect(Priority::values())->toBe([1, 2, 3, 4, 5])
        ->and(Priority::defaultCase())->toBe(Priority::Normal);
});

it('implements Filament contracts', function (): void {
    foreach (Priority::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getColor())->toBeString();
    }
});
