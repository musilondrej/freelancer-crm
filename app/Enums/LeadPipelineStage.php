<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum LeadPipelineStage: string implements HasColor, HasIcon, HasLabel
{
    case Inbox = 'inbox';
    case Discovery = 'discovery';
    case Qualification = 'qualification';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inbox => __('Inbox'),
            self::Discovery => __('Discovery'),
            self::Qualification => __('Qualification'),
            self::Proposal => __('Proposal'),
            self::Negotiation => __('Negotiation'),
            self::Closed => __('Closed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Inbox => 'gray',
            self::Discovery => 'info',
            self::Qualification => 'primary',
            self::Proposal => 'warning',
            self::Negotiation => 'warning',
            self::Closed => 'success',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Inbox => Heroicon::OutlinedInbox,
            self::Discovery => Heroicon::OutlinedMagnifyingGlass,
            self::Qualification => Heroicon::OutlinedShieldCheck,
            self::Proposal => Heroicon::OutlinedDocumentText,
            self::Negotiation => Heroicon::OutlinedChatBubbleLeftRight,
            self::Closed => Heroicon::OutlinedLockClosed,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
