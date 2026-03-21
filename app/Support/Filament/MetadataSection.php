<?php

namespace App\Support\Filament;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

final class MetadataSection
{
    public static function make(string $modelClass): Section
    {
        return Section::make(__('System'))
            ->schema([
                TextEntry::make('created_at')
                    ->label(__('Created at'))
                    ->state(fn ($record): ?string => $record?->created_at?->diffForHumans()),
                TextEntry::make('updated_at')
                    ->label(__('Updated at'))
                    ->state(fn ($record): ?string => $record?->updated_at?->diffForHumans()),
            ])
            ->hidden(fn ($record): bool => ! $record instanceof $modelClass);
    }
}
