<?php

namespace App\Filament\Resources\LeadSources\Schemas;

use App\Models\LeadSource;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class LeadSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Lead Source Workspace')
                            ->tabs([
                                Tab::make('Profile')
                                    ->icon(Heroicon::OutlinedFunnel)
                                    ->schema([
                                        Section::make('Source Profile')
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
                                                    ->unique(LeadSource::class, 'slug', ignoreRecord: true)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Behavior')
                                    ->icon(Heroicon::OutlinedCog6Tooth)
                                    ->schema([
                                        Section::make('Behavior')
                                            ->schema([
                                                Toggle::make('is_active')
                                                    ->default(true),
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
                                    ->state(fn (?LeadSource $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?LeadSource $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?LeadSource $record): bool => ! $record instanceof LeadSource),
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
