<?php

declare(strict_types=1);

namespace App\Filament\Resources\Notes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Str;

final class NoteRepeater
{
    public static function make(?int $ownerId = null): Repeater
    {
        return Repeater::make('notes')
            ->relationship('notes')
            ->schema([
                Hidden::make('owner_id')
                    ->default($ownerId),

                Textarea::make('body')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                DateTimePicker::make('noted_at')
                    ->default(now())
                    ->readOnly(),

            ])
            ->columns(1)
            ->addActionLabel(__('Add note'))
            ->defaultItems(0)
            ->collapsed()
            ->reorderable(false)
            ->itemLabel(
                fn (array $state): string => Str::limit((string) ($state['body'] ?? 'Note'), 64)
            );
    }
}
