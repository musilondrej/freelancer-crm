<?php

use App\Enums\ProjectActivityStatus;

it('marks Done as done', function (): void {
    expect(ProjectActivityStatus::Done->isDone())->toBeTrue()
        ->and(ProjectActivityStatus::InProgress->isDone())->toBeFalse()
        ->and(ProjectActivityStatus::Cancelled->isDone())->toBeFalse();
});

it('marks Cancelled as cancelled', function (): void {
    expect(ProjectActivityStatus::Cancelled->isCancelled())->toBeTrue()
        ->and(ProjectActivityStatus::InProgress->isCancelled())->toBeFalse();
});

it('marks open statuses correctly', function (): void {
    expect(ProjectActivityStatus::Planned->isOpen())->toBeTrue()
        ->and(ProjectActivityStatus::InProgress->isOpen())->toBeTrue()
        ->and(ProjectActivityStatus::Blocked->isOpen())->toBeTrue()
        ->and(ProjectActivityStatus::Done->isOpen())->toBeFalse()
        ->and(ProjectActivityStatus::Cancelled->isOpen())->toBeFalse()
        ->and(ProjectActivityStatus::InProgress->isRunning())->toBeTrue()
        ->and(ProjectActivityStatus::Planned->isRunning())->toBeFalse()
        ->and(ProjectActivityStatus::Planned->isPlanned())->toBeTrue()
        ->and(ProjectActivityStatus::Blocked->isBlocked())->toBeTrue();
});

it('returns correct static collections', function (): void {
    expect(ProjectActivityStatus::doneValues())->toBe(['done'])
        ->and(ProjectActivityStatus::openValues())->toBe(['planned', 'in_progress', 'blocked'])
        ->and(ProjectActivityStatus::defaultCase())->toBe(ProjectActivityStatus::InProgress)
        ->and(ProjectActivityStatus::runningCase())->toBe(ProjectActivityStatus::InProgress);
});

it('implements Filament contracts', function (): void {
    foreach (ProjectActivityStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getColor())->toBeString()
            ->and($case->getIcon())->not->toBeNull();
    }
});
