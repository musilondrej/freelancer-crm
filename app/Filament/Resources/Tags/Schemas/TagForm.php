<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Tag Workspace')
                            ->tabs([
                                Tab::make('Profile')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->schema([
                                        Section::make('Tag Profile')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default(Filament::auth()->id()),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('slug', Str::slug((string) $state)))
                                                    ->columnSpanFull(),
                                                TextInput::make('slug')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(Tag::class, 'slug', ignoreRecord: true)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Visual')
                                    ->icon(Heroicon::OutlinedSwatch)
                                    ->schema([
                                        Section::make('Visual Settings')
                                            ->schema([
                                                ColorPicker::make('color')
                                                    ->required()
                                                    ->default('#f59e0b'),
                                                TextInput::make('sort_order')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0),
                                            ])
                                            ->columns(1),
                                    ]),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?Tag $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Tag $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Tag $record): bool => ! $record instanceof Tag),
                        Section::make('Technical Metadata')
                            ->schema([
                                KeyValue::make('meta')
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
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
