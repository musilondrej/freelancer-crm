<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BillingReportStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Finalized => __('Finalized'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Finalized => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Finalized => Heroicon::OutlinedCheckCircle,
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isFinalized(): bool
    {
        return $this === self::Finalized;
    }
}
