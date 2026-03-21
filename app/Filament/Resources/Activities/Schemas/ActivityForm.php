<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Activity details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(160),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make(__('Settings'))
                            ->schema([
                                Toggle::make('is_billable')
                                    ->label(__('Is billable'))
                                    ->default(true),
                                Toggle::make('is_active')
                                    ->label(__('Is active'))
                                    ->default(true),
                            ])
                            ->columns(1),

                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('Created at'))
                                    ->state(fn (?Activity $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label(__('Updated at'))
                                    ->state(fn (?Activity $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Activity $record): bool => ! $record instanceof Activity),
                    ])
                    ->columnSpan([
                        'lg' => 4,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 12,
            ]);
    }
}
