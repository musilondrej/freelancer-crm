<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Actions\ResolveInheritedFinancials;
use App\Enums\Currency;
use App\Enums\Priority;
use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Project;
use App\Support\EnumValue;
use App\Support\Filament\FilteredByOwner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();
        $financials = new ResolveInheritedFinancials($ownerId);

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Overview'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('customer_id')
                                    ->label(__('Customer'))
                                    ->relationship(
                                        name: 'customer',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($financials): void {
                                        if (! (bool) $get('use_custom_financials')) {
                                            return;
                                        }

                                        $defaults = $financials->fromCustomer($state);
                                        $set('hourly_rate', $defaults->hourlyRate);
                                    }),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->rows(7)
                                    ->columnSpanFull(),
                            ]),

                        Section::make(__('Financial details'))
                            ->schema([
                                Toggle::make('use_custom_financials')
                                    ->label(__('Use custom hourly rate for this project'))
                                    ->default(fn (?Project $record): bool => $record instanceof Project && $record->hourly_rate !== null)
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateHydrated(function (Set $set, mixed $state): void {
                                        if ((bool) $state) {
                                            return;
                                        }

                                        $set('hourly_rate', null);
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($financials): void {
                                        if (! (bool) $state) {
                                            $set('hourly_rate', null);

                                            return;
                                        }

                                        $defaults = $financials->fromCustomer($get('customer_id'));

                                        if (blank($get('hourly_rate'))) {
                                            $set('hourly_rate', $defaults->hourlyRate);
                                        }
                                    }),
                                Select::make('pricing_model')
                                    ->label(__('Pricing model'))
                                    ->options(ProjectPricingModel::class)
                                    ->default(ProjectPricingModel::Fixed)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        $resolvedPricingModel = EnumValue::from($state);

                                        if (in_array($resolvedPricingModel, [ProjectPricingModel::Hourly->value, ProjectPricingModel::Retainer->value], true)) {
                                            return;
                                        }

                                        $set('hourly_rate', null);
                                    }),

                                Hidden::make('currency')
                                    ->dehydrateStateUsing(fn (): null => null),

                                TextInput::make('hourly_rate')
                                    ->label(__('Hourly rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(fn (Get $get): bool => (bool) $get('use_custom_financials')
                                        && in_array(EnumValue::from($get('pricing_model')), [ProjectPricingModel::Hourly->value, ProjectPricingModel::Retainer->value], true))
                                    ->visible(fn (Get $get): bool => (bool) $get('use_custom_financials')
                                        && in_array(EnumValue::from($get('pricing_model')), [ProjectPricingModel::Hourly->value, ProjectPricingModel::Retainer->value], true))
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get)),

                                TextInput::make('fixed_price')
                                    ->label(__('Fixed price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => EnumValue::from($get('pricing_model')) === ProjectPricingModel::Fixed->value),
                                TextInput::make('estimated_hours')
                                    ->label(__('Estimated hours'))
                                    ->numeric()
                                    ->suffix(__('hrs'))
                                    ->minValue(0),
                            ]),

                        Section::make(__('Notes'))
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
                        Section::make(__('Tags'))
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),
                        Section::make(__('Other details'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('Status'))
                                    ->options(ProjectStatus::class)
                                    ->default(ProjectStatus::defaultCase())
                                    ->required(),
                                Select::make('priority')
                                    ->label(__('Priority'))
                                    ->options(Priority::class)
                                    ->default(Priority::Normal)
                                    ->required(),
                                DatePicker::make('start_date')
                                    ->label(__('Start date')),
                                DatePicker::make('target_end_date')
                                    ->label(__('Target end date')),

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
}
