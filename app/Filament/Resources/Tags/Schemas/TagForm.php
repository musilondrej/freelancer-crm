<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Models\Tag;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\MetadataSection;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Tag details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default(FilteredByOwner::ownerId()),

                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),

                                ColorPicker::make('color')
                                    ->label(__('Color'))
                                    ->required()
                                    ->default('#f59e0b'),
                            ])
                            ->columns(1),

                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        MetadataSection::make(Tag::class),
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
