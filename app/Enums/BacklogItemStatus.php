<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BacklogItemStatus: string implements HasColor, HasIcon, HasLabel
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'Todo',
            self::InProgress => 'In Progress',
            self::Blocked => 'Blocked',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'warning',
            self::Blocked => 'danger',
            self::Done => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Todo => Heroicon::OutlinedClipboardDocumentList,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Blocked => Heroicon::OutlinedNoSymbol,
            self::Done => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Todo, self::InProgress, self::Blocked], true);
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [
            self::Todo->value,
            self::InProgress->value,
            self::Blocked->value,
        ];
    }
}
