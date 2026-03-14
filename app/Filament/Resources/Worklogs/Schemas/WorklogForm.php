<?php

namespace App\Filament\Resources\Worklogs\Schemas;

use App\Enums\ProjectActivityType;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Activity;
use App\Models\BacklogItem;
use App\Models\Worklog;
use App\Support\Filament\Currency;
use App\Support\Filament\WorklogStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

class WorklogForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();
        $requestedProjectId = request()->query('project_id');
        $requestedActivityId = request()->query('activity_id');
        $requestedBacklogItemId = request()->query('backlog_item_id');
        $defaultProjectId = is_numeric($requestedProjectId) ? (int) $requestedProjectId : null;
        $defaultActivityId = is_numeric($requestedActivityId) ? (int) $requestedActivityId : null;
        $defaultBacklogItemId = is_numeric($requestedBacklogItemId) ? (int) $requestedBacklogItemId : null;

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Worklog')
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                Select::make('project_id')
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
                                        $set('backlog_item_id', null);
                                    }),
                                Select::make('activity_id')
                                    ->label('Activity template')
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
                                Select::make('backlog_item_id')
                                    ->label('Backlog item')
                                    ->default($defaultBacklogItemId)
                                    ->options(fn (Get $get): array => self::backlogOptions($ownerId, $get('project_id')))
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id')))
                                    ->live()
                                    ->afterStateHydrated(function (Get $get, Set $set, mixed $state) use ($ownerId): void {
                                        self::syncFromBacklog($ownerId, $get, $set, $state);
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($ownerId): void {
                                        self::syncFromBacklog($ownerId, $get, $set, $state);
                                    })
                                    ->helperText('Optional link to planned work from backlog.'),
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->readOnly(fn (Get $get): bool => is_numeric($get('activity_id')))
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->rows(7)
                                    ->columnSpanFull(),
                                Select::make('type')
                                    ->options(ProjectActivityType::class)
                                    ->default(ProjectActivityType::Hourly)
                                    ->required()
                                    ->live(),
                            ])
                            ->columns(1),

                        Section::make('Billing')
                            ->schema([
                                Toggle::make('is_billable')
                                    ->default(true),
                                Toggle::make('is_invoiced')
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
                                Select::make('currency')
                                    ->options([
                                        'CZK' => 'CZK (Kč)',
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ]),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('unit_rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolve($get)),
                                TextInput::make('flat_amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolve($get))
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::OneTime->value),
                                TextInput::make('invoice_reference')
                                    ->maxLength(64)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                                DateTimePicker::make('invoiced_at')
                                    ->seconds(false)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                            ])
                            ->columns(1),

                        Section::make('Quick Notes')
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
                                    ->options(fn (): array => WorklogStatus::options($ownerId))
                                    ->default(fn (): string => WorklogStatus::defaultCode($ownerId))
                                    ->required(),
                            ]),

                        Section::make('Tags')
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),

                        Section::make('Time')
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->seconds(false),
                                DateTimePicker::make('finished_at')
                                    ->seconds(false),
                                TextInput::make('tracked_minutes')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('min')
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::Hourly->value),
                                DatePicker::make('due_date'),
                            ])
                            ->columns(1),

                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?Worklog $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Worklog $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Worklog $record): bool => ! $record instanceof Worklog),
                        Section::make('Technical Metadata')
                            ->schema([
                                KeyValue::make('meta')
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
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
            ->where(function (Builder $query) use ($projectId): void {
                $query->whereNull('project_id')
                    ->orWhere('project_id', (int) $projectId);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function backlogOptions(?int $ownerId, mixed $projectId): array
    {
        if ($ownerId === null || ! is_numeric($projectId)) {
            return [];
        }

        return BacklogItem::query()
            ->where('owner_id', $ownerId)
            ->where('project_id', (int) $projectId)
            ->orderByDesc('priority')
            ->oldest('due_date')
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    private static function syncFromBacklog(?int $ownerId, Get $get, Set $set, mixed $state): void
    {
        if ($ownerId === null || ! is_numeric($state)) {
            return;
        }

        $backlogItem = BacklogItem::query()
            ->where('owner_id', $ownerId)
            ->find((int) $state);

        if (! $backlogItem instanceof BacklogItem) {
            return;
        }

        $set('project_id', $backlogItem->project_id);
        $set('activity_id', $backlogItem->activity_id);

        if (! is_string($get('title')) || trim($get('title')) === '') {
            $set('title', $backlogItem->title);
        }

        if ((! is_string($get('description')) || trim($get('description')) === '') && $backlogItem->description !== null) {
            $set('description', $backlogItem->description);
        }

        if ($get('due_date') === null && $backlogItem->due_date !== null) {
            $set('due_date', Date::parse((string) $backlogItem->due_date)->toDateString());
        }
    }

    private static function resolveActivityTypeValue(mixed $value): string
    {
        if ($value instanceof ProjectActivityType) {
            return $value->value;
        }

        return (string) $value;
    }
}
