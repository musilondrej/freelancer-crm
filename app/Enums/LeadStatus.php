<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum LeadStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Proposal => 'Proposal',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Contacted => 'info',
            self::Qualified => 'primary',
            self::Proposal => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Archived => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::New => Heroicon::OutlinedSparkles,
            self::Contacted => Heroicon::OutlinedPhone,
            self::Qualified => Heroicon::OutlinedCheckBadge,
            self::Proposal => Heroicon::OutlinedDocumentText,
            self::Won => Heroicon::OutlinedTrophy,
            self::Lost => Heroicon::OutlinedXCircle,
            self::Archived => Heroicon::OutlinedArchiveBox,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
