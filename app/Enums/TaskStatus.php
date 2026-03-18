<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case InReview = 'in_review';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => __('Backlog'),
            self::Todo => __('To Do'),
            self::InProgress => __('In Progress'),
            self::Blocked => __('Blocked'),
            self::InReview => __('In Review'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog => 'gray',
            self::Todo => 'info',
            self::InProgress => 'warning',
            self::InReview => 'primary',
            self::Done => 'success',
            self::Blocked => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Backlog => Heroicon::InboxStack,
            self::Todo => Heroicon::QueueList,
            self::InProgress => Heroicon::ArrowPath,
            self::Blocked => Heroicon::OutlinedNoSymbol,
            self::InReview => Heroicon::Eye,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isPlanned(): bool
    {
        return $this === self::Todo;
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Todo, self::InProgress, self::Blocked, self::InReview], true);
    }

    public function isRunning(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * @return list<string>
     */
    public static function doneValues(): array
    {
        return [self::Done->value];
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Todo->value, self::InProgress->value, self::Blocked->value, self::InReview->value];
    }

    public static function defaultCase(): self
    {
        return self::InProgress;
    }

    public static function runningCase(): self
    {
        return self::InProgress;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
