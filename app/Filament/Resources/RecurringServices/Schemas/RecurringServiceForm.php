<?php

namespace App\Filament\Resources\RecurringServices\Schemas;

use App\Enums\Currency;
use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\RecurringServiceType as RecurringServiceTypeModel;
use App\Support\EnumValue;
use App\Support\Filament\FilteredByOwner;
use Filament\Forms\Components\CheckboxList;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RecurringServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('customer_id')
                                    ->relationship(
                                        name: 'customer',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->searchable()
                                    ->preload(),
                                Select::make('project_id')
                                    ->label(__('Related project'))
                                    ->relationship(
                                        name: 'project',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->searchable()
                                    ->preload(),
                                Select::make('service_type_id')
                                    ->label(__('Service'))
                                    ->relationship(
                                        name: 'serviceType',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                            ? $query
                                                ->where('owner_id', $ownerId)
                                                ->orderBy('sort_order')
                                                ->orderBy('name')
                                            : $query,
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label(__('Name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('slug', Str::slug((string) $state))),
                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255),
                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data) use ($ownerId): int {
                                        $resolvedOwnerId = (int) ($ownerId ?? FilteredByOwner::ownerId());

                                        $serviceType = RecurringServiceTypeModel::query()->firstOrCreate(
                                            [
                                                'owner_id' => $resolvedOwnerId,
                                                'slug' => (string) ($data['slug'] ?? ''),
                                            ],
                                            [
                                                'name' => (string) ($data['name'] ?? ''),
                                                'is_active' => (bool) ($data['is_active'] ?? true),
                                                'sort_order' => (int) ($data['sort_order']
                                                    ?? (RecurringServiceTypeModel::query()
                                                        ->where('owner_id', $resolvedOwnerId)
                                                        ->max('sort_order') + 10)),
                                            ],
                                        );

                                        return $serviceType->id;
                                    })
                                    ->required(),
                                Textarea::make('notes')
                                    ->rows(7)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make(__('Schedule'))

                            ->schema([
                                Select::make('cadence_unit')
                                    ->label(__('Cadence unit'))
                                    ->options(RecurringServiceCadenceUnit::class)
                                    ->default(RecurringServiceCadenceUnit::Month)
                                    ->required(),
                                TextInput::make('cadence_interval')
                                    ->label(__('Cadence interval'))
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('Cadence is the source of truth for schedule progression.')
                                    ->required(),
                                DatePicker::make('starts_on')
                                    ->label(__('Starts on'))
                                    ->required(),
                                DatePicker::make('ends_on')
                                    ->label(__('Ends on')),
                                Select::make('status')
                                    ->label(__('Status'))
                                    ->options(RecurringServiceStatus::class)
                                    ->default(RecurringServiceStatus::Active)
                                    ->required(),
                                Toggle::make('auto_renew')
                                    ->label(__('Auto-renew'))
                                    ->default(true),
                                CheckboxList::make('remind_days_before')
                                    ->label(__('Remind before'))
                                    ->options([
                                        1 => '1 day',
                                        3 => '3 days',
                                        7 => '7 days',
                                        14 => '14 days',
                                        30 => '30 days',
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ]),

                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make(__('Tags'))
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),

                        Section::make(__('Billing details'))
                            ->schema([
                                Select::make('billing_model')
                                    ->label(__('Billing model'))
                                    ->options(RecurringServiceBillingModel::class)
                                    ->default(RecurringServiceBillingModel::Fixed)
                                    ->required()
                                    ->live(),
                                Select::make('currency')
                                    ->label(__('Currency'))
                                    ->options(Currency::class)
                                    ->live(),
                                TextInput::make('fixed_amount')
                                    ->label(__('Fixed amount'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => EnumValue::from($get('billing_model')) === RecurringServiceBillingModel::Fixed->value),
                                TextInput::make('hourly_rate')
                                    ->label(__('Hourly rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => EnumValue::from($get('billing_model')) === RecurringServiceBillingModel::Hourly->value),
                                TextInput::make('included_quantity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => EnumValue::from($get('billing_model')) === RecurringServiceBillingModel::Hourly->value),
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
