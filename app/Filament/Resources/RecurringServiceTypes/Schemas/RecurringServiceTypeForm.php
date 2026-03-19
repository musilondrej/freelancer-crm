<?php

namespace App\Filament\Resources\RecurringServiceTypes\Schemas;

use App\Models\RecurringServiceType;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RecurringServiceTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Recurring service type details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default(Filament::auth()->id()),
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('slug', Str::slug((string) $state)))
                                    ->columnSpanFull(),
                                TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->required()
                                    ->maxLength(255)
                                    ->scopedUnique(RecurringServiceType::class, 'slug', ignoreRecord: true)
                                    ->columnSpanFull(),
                                TextInput::make('sort_order')
                                    ->label(__('Sort order'))
                                    ->numeric()
                                    ->default(10)
                                    ->required(),
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
                        Section::make(__('System'))
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('Created at'))
                                    ->state(fn (?RecurringServiceType $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label(__('Updated at'))
                                    ->state(fn (?RecurringServiceType $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?RecurringServiceType $record): bool => ! $record instanceof RecurringServiceType),
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
