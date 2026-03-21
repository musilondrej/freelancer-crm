<?php

namespace App\Filament\Resources\LeadSources\Schemas;

use App\Models\LeadSource;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\MetadataSection;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LeadSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Lead source details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default(FilteredByOwner::ownerId()),
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
                                    ->unique(LeadSource::class, 'slug', ignoreRecord: true)
                                    ->columnSpanFull(),
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
                        MetadataSection::make(LeadSource::class),
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
