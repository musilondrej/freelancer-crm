<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectPipelineStage: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Proposal => 'Proposal',
            self::Negotiation => 'Negotiation',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Proposal => 'warning',
            self::Negotiation => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::New => Heroicon::OutlinedSparkles,
            self::Proposal => Heroicon::OutlinedDocumentText,
            self::Negotiation => Heroicon::OutlinedChatBubbleBottomCenterText,
            self::Won => Heroicon::OutlinedTrophy,
            self::Lost => Heroicon::OutlinedXCircle,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
