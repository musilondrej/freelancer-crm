<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectActivityStatus: string implements HasColor, HasIcon, HasLabel
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::InProgress => __('In Progress'),
            self::Blocked => __('Blocked'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'warning',
            self::Blocked => 'danger',
            self::Done => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Planned => Heroicon::OutlinedClipboardDocumentList,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Blocked => Heroicon::OutlinedNoSymbol,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isPlanned(): bool
    {
        return $this === self::Planned;
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
        return in_array($this, [self::Planned, self::InProgress, self::Blocked], true);
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
        return [self::Planned->value, self::InProgress->value, self::Blocked->value];
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
