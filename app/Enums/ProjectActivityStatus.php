<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectActivityStatus: string implements HasColor, HasIcon, HasLabel
{
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedNoSymbol,
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isOpen(): bool
    {
        return $this === self::InProgress;
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
        return [self::InProgress->value];
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
