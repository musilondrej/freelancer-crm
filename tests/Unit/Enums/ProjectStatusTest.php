<?php

use App\Enums\ProjectStatus;

it('identifies open statuses', function (): void {
    expect(ProjectStatus::Planned->isOpen())->toBeTrue()
        ->and(ProjectStatus::InProgress->isOpen())->toBeTrue()
        ->and(ProjectStatus::Blocked->isOpen())->toBeTrue()
        ->and(ProjectStatus::Completed->isOpen())->toBeFalse()
        ->and(ProjectStatus::Cancelled->isOpen())->toBeFalse();
});

it('identifies trackable statuses', function (): void {
    expect(ProjectStatus::Planned->isTrackable())->toBeTrue()
        ->and(ProjectStatus::InProgress->isTrackable())->toBeTrue()
        ->and(ProjectStatus::Blocked->isTrackable())->toBeTrue()
        ->and(ProjectStatus::Completed->isTrackable())->toBeTrue()
        ->and(ProjectStatus::Cancelled->isTrackable())->toBeFalse();
});

it('returns correct static collections', function (): void {
    expect(ProjectStatus::openValues())->toBe(['planned', 'in_progress', 'blocked'])
        ->and(ProjectStatus::trackableValues())->toBe(['planned', 'in_progress', 'blocked', 'completed'])
        ->and(ProjectStatus::defaultCase())->toBe(ProjectStatus::Planned);
});

it('implements Filament contracts', function (): void {
    foreach (ProjectStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getColor())->toBeString()
            ->and($case->getIcon())->not->toBeNull();
    }
});
