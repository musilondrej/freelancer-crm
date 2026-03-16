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

it('marks InProgress as open and running', function (): void {
    expect(ProjectActivityStatus::InProgress->isOpen())->toBeTrue()
        ->and(ProjectActivityStatus::Done->isOpen())->toBeFalse()
        ->and(ProjectActivityStatus::Cancelled->isOpen())->toBeFalse()
        ->and(ProjectActivityStatus::InProgress->isRunning())->toBeTrue()
        ->and(ProjectActivityStatus::Done->isRunning())->toBeFalse();
});

it('returns correct static collections', function (): void {
    expect(ProjectActivityStatus::doneValues())->toBe(['done'])
        ->and(ProjectActivityStatus::openValues())->toBe(['in_progress'])
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
