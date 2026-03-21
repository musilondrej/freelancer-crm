<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Actions\ResolveInheritedFinancials;
use App\Enums\Currency;
use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Task;
use App\Support\EnumValue;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\HourlyRateCurrencyFields;
use App\Support\TimeDuration;
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

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();
        $financials = new ResolveInheritedFinancials($ownerId);
        $requestedProjectId = request()->query('project_id');
        $defaultProjectId = is_numeric($requestedProjectId) ? (int) $requestedProjectId : null;

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Task details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                Select::make('project_id')
                                    ->label(__('Project'))
                                    ->relationship(
                                        name: 'project',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->default($defaultProjectId)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($financials): void {
                                        if (! (bool) $get('use_custom_financials')) {
                                            return;
                                        }

                                        $defaults = $financials->fromProject($state);
                                        $set('currency', $defaults->currency);
                                        $set('hourly_rate_override', $defaults->hourlyRate);
                                    }),
                                Select::make('billing_model')
                                    ->label(__('Billing model'))
                                    ->options(TaskBillingModel::class)
                                    ->default(TaskBillingModel::Hourly)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        $resolvedBillingModel = EnumValue::from($state);

                                        if ($resolvedBillingModel === TaskBillingModel::Hourly->value) {
                                            $set('fixed_price', null);

                                            return;
                                        }

                                        if ($resolvedBillingModel === TaskBillingModel::FixedPrice->value) {
                                            $set('hourly_rate_override', null);
                                        }
                                    }),
                                TextInput::make('title')
                                    ->label(__('Title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->rows(7)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make(__('Billing details'))
                            ->schema([
                                Toggle::make('use_custom_financials')
                                    ->label(__('Use custom currency and hourly rate for this task'))
                                    ->default(fn (?Task $record): bool => $record instanceof Task && ($record->currency !== null || $record->hourly_rate_override !== null))
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateHydrated(function (Set $set, mixed $state): void {
                                        if ((bool) $state) {
                                            return;
                                        }

                                        $set('currency', null);
                                        $set('hourly_rate_override', null);
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($financials): void {
                                        if (! (bool) $state) {
                                            $set('currency', null);
                                            $set('hourly_rate_override', null);

                                            return;
                                        }

                                        $defaults = $financials->fromProject($get('project_id'));

                                        if (blank($get('currency'))) {
                                            $set('currency', $defaults->currency);
                                        }

                                        if (blank($get('hourly_rate_override'))) {
                                            $set('hourly_rate_override', $defaults->hourlyRate);
                                        }
                                    })
                                    ->helperText(__('When turned off, currency and hourly rate are inherited automatically from the project.')),
                                ...HourlyRateCurrencyFields::make(
                                    currencyField: 'currency',
                                    rateField: 'hourly_rate_override',
                                    rateLabel: 'Hourly rate',
                                    currencyRequired: fn (Get $get): bool => (bool) $get('use_custom_financials'),
                                    rateRequired: fn (Get $get): bool => (bool) $get('use_custom_financials')
                                        && EnumValue::from($get('billing_model')) === TaskBillingModel::Hourly->value,
                                    currencyVisible: fn (Get $get): bool => (bool) $get('use_custom_financials'),
                                    rateVisible: fn (Get $get): bool => (bool) $get('use_custom_financials')
                                        && EnumValue::from($get('billing_model')) === TaskBillingModel::Hourly->value,
                                ),
                                TextInput::make('fixed_price')
                                    ->label(__('Fixed price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => EnumValue::from($get('billing_model')) === TaskBillingModel::FixedPrice->value),
                                Toggle::make('is_billable')
                                    ->label(__('Is billable'))
                                    ->default(true),
                            ])
                            ->columns(1),

                        Section::make(__('Notes'))
                            ->schema([
                                NoteRepeater::make($ownerId),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),

                Group::make()
                    ->schema([
                        Section::make(__('Classification'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('Status'))
                                    ->options(TaskStatus::class)
                                    ->default(TaskStatus::defaultCase())
                                    ->required(),
                                Select::make('priority')
                                    ->label(__('Priority'))
                                    ->options(Priority::class)
                                    ->default(Priority::Normal)
                                    ->required(),
                            ]),

                        Section::make(__('Tags'))
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),

                        Section::make(__('Planning'))
                            ->schema([
                                TextInput::make('estimated_minutes')
                                    ->label(__('Estimate'))
                                    ->placeholder('e.g. 2h 30m, 1d, 45m')
                                    ->formatStateUsing(fn (?int $state): ?string => TimeDuration::format($state))
                                    ->dehydrateStateUsing(fn (?string $state): ?int => $state !== null ? TimeDuration::toMinutes($state) : null),
                                DatePicker::make('due_date')
                                    ->label(__('Due date')),
                            ])
                            ->columns(1),

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
