<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\ClientContact;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Project Brief')
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('client_id')
                                    ->label('Customer')
                                    ->relationship(
                                        name: 'customer',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                            ? $query->where('owner_id', $ownerId)
                                            : $query,
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('primary_contact_id')
                                    ->label('Primary contact')
                                    ->options(function (Get $get) use ($ownerId): array {
                                        $customerId = $get('client_id');

                                        return ClientContact::query()
                                            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                                            ->when($customerId !== null, fn (Builder $query): Builder => $query->where('client_id', $customerId))
                                            ->orderBy('full_name')
                                            ->pluck('full_name', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload(),
                                Textarea::make('description')
                                    ->rows(7)
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Pricing')
                            ->schema([
                                Select::make('pricing_model')
                                    ->options(ProjectPricingModel::class)
                                    ->default(ProjectPricingModel::Fixed)
                                    ->required()
                                    ->live(),
                                Select::make('currency')
                                    ->options([
                                        'CZK' => 'CZK (Kč)',
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ]),
                                TextInput::make('hourly_rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => in_array(self::resolvePricingModelValue($get('pricing_model')), [ProjectPricingModel::Hourly->value, ProjectPricingModel::Retainer->value], true)),
                                TextInput::make('fixed_price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => self::resolvePricingModelValue($get('pricing_model')) === ProjectPricingModel::Fixed->value),
                                TextInput::make('estimated_hours')
                                    ->numeric()
                                    ->suffix('hrs')
                                    ->minValue(0),
                            ]),

                        Section::make('Quick Notes')
                            ->schema([
                                NoteRepeater::make($ownerId),
                            ]),

                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),

                // Sidebar
                Group::make()
                    ->schema([
                        Section::make('Tags')
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),
                        Section::make('Execution')
                            ->schema([
                                Select::make('status')
                                    ->options(ProjectStatus::class)
                                    ->default(ProjectStatus::defaultCase())
                                    ->required(),
                                DatePicker::make('start_date'),
                                DatePicker::make('target_end_date'),
                            ]),
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

    private static function resolvePricingModelValue(mixed $value): string
    {
        if ($value instanceof ProjectPricingModel) {
            return $value->value;
        }

        return (string) $value;
    }
}
