<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Activity Workspace')
                            ->tabs([
                                Tab::make('Definition')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->schema([
                                        Section::make('Activity')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(160),
                                                Textarea::make('description')
                                                    ->rows(4)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Billing')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->schema([
                                        Section::make('Billing & Behavior')
                                            ->schema([
                                                TextInput::make('default_hourly_rate')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix(fn (): string => (string) (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))),
                                                Toggle::make('is_billable')
                                                    ->default(true),
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
                                    ->state(fn (?Activity $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Activity $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Activity $record): bool => ! $record instanceof Activity),
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
