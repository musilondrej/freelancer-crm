<?php

namespace App\Filament\Resources\Worklogs\Schemas;

use App\Enums\Currency;
use App\Enums\ProjectActivityStatus;
use App\Enums\ProjectActivityType;
use App\Enums\WorklogPriority;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Activity;
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

class WorklogForm
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
                        Section::make(__('Worklog details'))
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
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('activity_id', null);
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
                                            $set('unit_rate', $activity->default_hourly_rate);
                                        }
                                    }),
                                Select::make('type')
                                    ->options(ProjectActivityType::class)
                                    ->default(ProjectActivityType::Hourly)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        $resolvedType = self::resolveActivityTypeValue($state);

                                        if ($resolvedType === ProjectActivityType::Hourly->value) {
                                            $set('flat_amount', null);

                                            return;
                                        }

                                        if ($resolvedType === ProjectActivityType::OneTime->value) {
                                            $set('quantity', null);
                                            $set('unit_rate', null);
                                            $set('tracked_minutes', null);
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

                        Section::make(__('Financial details'))
                            ->schema([
                                Select::make('currency')
                                    ->label(__('Currency'))
                                    ->options(Currency::class),
                                TextInput::make('quantity')
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::Hourly->value),
                                TextInput::make('unit_rate')
                                    ->label(__('Unit rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::Hourly->value),
                                TextInput::make('flat_amount')
                                    ->label(__('Flat amount'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::OneTime->value),
                                TextInput::make('invoice_reference')
                                    ->label(__('Invoice reference'))
                                    ->maxLength(64)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                                DateTimePicker::make('invoiced_at')
                                    ->label(__('Invoiced at'))
                                    ->seconds(false)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                                Toggle::make('is_billable')
                                    ->label(__('Is billable'))
                                    ->default(true),
                                Toggle::make('is_invoiced')
                                    ->label(__('Is invoiced'))
                                    ->default(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (! (bool) $state) {
                                            $set('invoice_reference', null);
                                            $set('invoiced_at', null);

                                            return;
                                        }

                                        $set('invoiced_at', now());
                                    }),
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
                                    ->options(ProjectActivityStatus::class)
                                    ->default(ProjectActivityStatus::defaultCase())
                                    ->required(),
                                Select::make('priority')
                                    ->options(WorklogPriority::class),
                            ]),

                        Section::make(__('Tags'))
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),

                        Section::make(__('Time'))
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->label(__('Started at'))
                                    ->seconds(false),
                                DateTimePicker::make('finished_at')
                                    ->label(__('Finished at'))
                                    ->seconds(false),
                                TextInput::make('tracked_minutes')
                                    ->label(__('Tracked time'))
                                    ->placeholder('e.g. 2h 30m, 1d, 45m')
                                    ->formatStateUsing(fn (?int $state): ?string => TimeDuration::format($state))
                                    ->dehydrateStateUsing(fn (?string $state): ?int => $state !== null ? TimeDuration::toMinutes($state) : null)
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::Hourly->value),
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

    private static function resolveActivityTypeValue(mixed $value): string
    {
        if ($value instanceof ProjectActivityType) {
            return $value->value;
        }

        return (string) $value;
    }
}
