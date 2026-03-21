<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectStatus: string implements HasColor, HasIcon, HasLabel
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planning => __('Planning'),
            self::InProgress => __('In Progress'),
            self::Blocked => __('Blocked'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::InProgress => 'warning',
            self::Blocked => 'danger',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Planning => Heroicon::PencilSquare,
            self::InProgress => Heroicon::ArrowPath,
            self::Blocked => Heroicon::Pause,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::XMark,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Planning, self::InProgress, self::Blocked], true);
    }

    public function isTrackable(): bool
    {
        return $this !== self::Cancelled;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isOpen()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function trackableValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isTrackable()),
        ));
    }

    public static function defaultCase(): self
    {
        return self::Planning;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
