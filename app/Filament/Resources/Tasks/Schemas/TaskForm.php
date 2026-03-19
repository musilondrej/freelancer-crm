<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\Currency;
use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Activity;
use App\Models\Project;
use App\Support\Filament\HourlyRateCurrencyFields;
use App\Support\TimeDuration;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();
        $requestedProjectId = request()->query('project_id');
        $requestedActivityId = request()->query('activity_id');
        $defaultProjectId = is_numeric($requestedProjectId) ? (int) $requestedProjectId : null;
        $defaultActivityId = is_numeric($requestedActivityId) ? (int) $requestedActivityId : null;

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
                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                            ? $query->where('owner_id', $ownerId)
                                            : $query,
                                    )
                                    ->default($defaultProjectId)
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($ownerId): void {
                                        $set('activity_id', null);

                                        if (self::resolveTaskBillingModelValue($get('billing_model')) !== TaskBillingModel::Hourly->value) {
                                            return;
                                        }

                                        if (filled($get('hourly_rate_override'))) {
                                            return;
                                        }

                                        $set('hourly_rate_override', self::resolveInheritedHourlyRate($ownerId, $state, $get('currency')));
                                    }),
                                Select::make('activity_id')
                                    ->label(__('Activity template'))
                                    ->default($defaultActivityId)
                                    ->required()
                                    ->options(fn (Get $get): array => self::activityOptions($ownerId, $get('project_id')))
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id')))
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (! is_numeric($state)) {
                                            return;
                                        }

                                        $activity = Activity::query()->find((int) $state);

                                        if (! $activity instanceof Activity) {
                                            return;
                                        }

                                        $set('title', $activity->name);
                                        $set('is_billable', $activity->is_billable);

                                        if ($activity->default_hourly_rate !== null) {
                                            $set('hourly_rate_override', $activity->default_hourly_rate);
                                        }
                                    }),
                                Select::make('billing_model')
                                    ->label(__('Billing model'))
                                    ->options(TaskBillingModel::class)
                                    ->default(TaskBillingModel::Hourly)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($ownerId): void {
                                        $resolvedBillingModel = self::resolveTaskBillingModelValue($state);

                                        if ($resolvedBillingModel === TaskBillingModel::Hourly->value) {
                                            $set('fixed_price', null);

                                            if (blank($get('hourly_rate_override'))) {
                                                $set('hourly_rate_override', self::resolveInheritedHourlyRate($ownerId, $get('project_id'), $get('currency')));
                                            }

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
                                    ->readOnly(fn (Get $get): bool => is_numeric($get('activity_id')))
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->rows(7)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make(__('Billing details'))
                            ->schema([
                                ...HourlyRateCurrencyFields::make(
                                    currencyField: 'currency',
                                    rateField: 'hourly_rate_override',
                                    rateLabel: 'Hourly rate',
                                    rateRequired: fn (Get $get): bool => self::resolveTaskBillingModelValue($get('billing_model')) === TaskBillingModel::Hourly->value,
                                    inheritedRateResolver: fn (Get $get, mixed $currency): ?float => self::resolveInheritedHourlyRate($ownerId, $get('project_id'), $currency),
                                    rateVisible: fn (Get $get): bool => self::resolveTaskBillingModelValue($get('billing_model')) === TaskBillingModel::Hourly->value,
                                ),
                                TextInput::make('fixed_price')
                                    ->label(__('Fixed price'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => self::resolveTaskBillingModelValue($get('billing_model')) === TaskBillingModel::FixedPrice->value),
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
                        Section::make('Workflow')
                            ->schema([
                                Select::make('status')
                                    ->options(TaskStatus::class)
                                    ->default(TaskStatus::defaultCase())
                                    ->required(),
                                Select::make('priority')
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
                                DateTimePicker::make('completed_at')
                                    ->label(__('Completed at'))
                                    ->seconds(false),
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

    /**
     * @return array<int, string>
     */
    private static function activityOptions(?int $ownerId, mixed $projectId): array
    {
        if ($ownerId === null || ! is_numeric($projectId)) {
            return [];
        }

        return Activity::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function resolveTaskBillingModelValue(mixed $value): string
    {
        if ($value instanceof TaskBillingModel) {
            return $value->value;
        }

        return (string) $value;
    }

    private static function resolveInheritedHourlyRate(?int $ownerId, mixed $projectId, mixed $currency): ?float
    {
        if (is_numeric($projectId)) {
            $project = Project::query()
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->with(['customer.owner'])
                ->find((int) $projectId);

            if ($project instanceof Project) {
                return $project->effectiveHourlyRate(is_string($currency) ? $currency : null);
            }
        }

        return Filament::auth()->user()?->defaultHourlyRateForCurrency(is_string($currency) ? $currency : null);
    }
}
