<?php

use App\Enums\TaskStatus;

it('marks Done as done', function (): void {
    expect(TaskStatus::Done->isDone())->toBeTrue()
        ->and(TaskStatus::InProgress->isDone())->toBeFalse()
        ->and(TaskStatus::Cancelled->isDone())->toBeFalse();
});

it('marks Cancelled as cancelled', function (): void {
    expect(TaskStatus::Cancelled->isCancelled())->toBeTrue()
        ->and(TaskStatus::InProgress->isCancelled())->toBeFalse();
});

it('marks open statuses correctly', function (): void {
    expect(TaskStatus::Planned->isOpen())->toBeTrue()
        ->and(TaskStatus::InProgress->isOpen())->toBeTrue()
        ->and(TaskStatus::Blocked->isOpen())->toBeTrue()
        ->and(TaskStatus::Done->isOpen())->toBeFalse()
        ->and(TaskStatus::Cancelled->isOpen())->toBeFalse()
        ->and(TaskStatus::InProgress->isRunning())->toBeTrue()
        ->and(TaskStatus::Planned->isRunning())->toBeFalse()
        ->and(TaskStatus::Planned->isPlanned())->toBeTrue()
        ->and(TaskStatus::Blocked->isBlocked())->toBeTrue();
});

it('returns correct static collections', function (): void {
    expect(TaskStatus::doneValues())->toBe(['done'])
        ->and(TaskStatus::openValues())->toBe(['planned', 'in_progress', 'blocked'])
        ->and(TaskStatus::defaultCase())->toBe(TaskStatus::InProgress)
        ->and(TaskStatus::runningCase())->toBe(TaskStatus::InProgress);
});

it('implements Filament contracts', function (): void {
    foreach (TaskStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()
            ->and($case->getColor())->toBeString()
            ->and($case->getIcon())->not->toBeNull();
    }
});
