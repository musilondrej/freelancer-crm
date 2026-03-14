<?php

namespace App\Filament\Resources\ProjectActivityStatusOptions\Schemas;

use App\Models\ProjectActivityStatusOption;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectActivityStatusOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Status Definition')
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, mixed $state, callable $set): void {
                                        if ($operation !== 'create' || ! is_string($state)) {
                                            return;
                                        }

                                        $set('code', str($state)->slug('_')->toString());
                                    }),
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(64)
                                    ->helperText('Internal key used by timer and metrics.')
                                    ->rule('regex:/^[a-z0-9_]+$/'),
                                Select::make('color')
                                    ->options([
                                        'gray' => 'Gray',
                                        'primary' => 'Primary',
                                        'info' => 'Info',
                                        'success' => 'Success',
                                        'warning' => 'Warning',
                                        'danger' => 'Danger',
                                    ])
                                    ->default('gray')
                                    ->required(),
                                TextInput::make('icon')
                                    ->maxLength(120)
                                    ->placeholder('heroicon-o-clock'),
                                TextInput::make('sort_order')
                                    ->required()
                                    ->integer()
                                    ->default(10)
                                    ->minValue(0),
                            ])
                            ->columns(2),
                        Section::make('Behavior Flags')
                            ->schema([
                                Toggle::make('is_default')
                                    ->label('Default status')
                                    ->inline(false),
                                Toggle::make('is_open')
                                    ->label('Counts as open')
                                    ->default(true)
                                    ->inline(false),
                                Toggle::make('is_done')
                                    ->label('Counts as done')
                                    ->inline(false),
                                Toggle::make('is_cancelled')
                                    ->label('Counts as cancelled')
                                    ->inline(false),
                                Toggle::make('is_running')
                                    ->label('Running timer status')
                                    ->inline(false),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Preview')
                            ->schema([
                                Placeholder::make('preview_label')
                                    ->label('Label')
                                    ->content(fn (Get $get): string => (string) ($get('label') ?: 'Not set')),
                                Placeholder::make('preview_code')
                                    ->label('Code')
                                    ->content(fn (Get $get): string => (string) ($get('code') ?: 'not_set')),
                                Placeholder::make('preview_color')
                                    ->label('Color')
                                    ->content(fn (Get $get): string => (string) ($get('color') ?: 'gray')),
                            ]),
                        Section::make('System')
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label('Created')
                                    ->content(fn (?ProjectActivityStatusOption $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                                Placeholder::make('updated_at')
                                    ->label('Last modified')
                                    ->content(fn (?ProjectActivityStatusOption $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                            ])
                            ->hidden(fn (?ProjectActivityStatusOption $record): bool => ! $record instanceof ProjectActivityStatusOption),
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
