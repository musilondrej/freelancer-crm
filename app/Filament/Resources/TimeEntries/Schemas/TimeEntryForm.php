<?php

namespace App\Filament\Resources\TimeEntries\Schemas;

use App\Enums\Currency;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TimeEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Time entry details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                Select::make('project_id')
                                    ->label(__('Project'))
                                    ->dehydrated(false)
                                    ->options(fn (): array => Project::query()
                                        ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->default(fn (?TimeEntry $record): ?int => $record?->task?->project_id)
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set): mixed => $set('task_id', null)),
                                Select::make('task_id')
                                    ->label(__('Task'))
                                    ->required()
                                    ->options(fn (Get $get): array => Task::query()
                                        ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
                                        ->when(is_numeric($get('project_id')), fn ($query) => $query->where('project_id', (int) $get('project_id')))
                                        ->orderBy('title')
                                        ->pluck('title', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload(),
                                Textarea::make('description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                TextInput::make('hourly_rate_override')
                                    ->label(__('Hourly rate override'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => self::rateCurrencySuffix($ownerId, $get('task_id'), $get('project_id')))
                                    ->helperText(__('Leave empty to use the task or customer hourly rate.')),
                                Select::make('is_billable_override')
                                    ->label(__('Billable'))
                                    ->options([
                                        'inherit' => __('Use task default'),
                                        '1' => __('Yes'),
                                        '0' => __('No'),
                                    ])
                                    ->default('inherit')
                                    ->formatStateUsing(fn ($state): string => $state === null ? 'inherit' : ((bool) $state ? '1' : '0'))
                                    ->dehydrateStateUsing(fn ($state): ?bool => match ((string) $state) {
                                        '1' => true,
                                        '0' => false,
                                        default => null,
                                    }),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpan(['lg' => 8]),
                Group::make()
                    ->schema([
                        Section::make(__('Timing'))
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->label(__('From'))
                                    ->required()
                                    ->seconds(false),
                                DateTimePicker::make('ended_at')
                                    ->label(__('To'))
                                    ->seconds(false),
                                TextInput::make('minutes')
                                    ->label(__('Tracked minutes'))
                                    ->numeric()
                                    ->minValue(0),
                                DateTimePicker::make('locked_at')
                                    ->label(__('Locked at'))
                                    ->seconds(false),
                            ])
                            ->columns(1),
                    ])
                    ->columnSpan(['lg' => 4]),
            ]);
    }

    private static function rateCurrencySuffix(?int $ownerId, mixed $taskId, mixed $projectId): string
    {
        if (is_numeric($taskId)) {
            $task = Task::query()
                ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
                ->with('project.customer')
                ->find((int) $taskId);

            if ($task instanceof Task) {
                return (string) ($task->effectiveCurrency() ?? Currency::userDefault()->value);
            }
        }

        if (is_numeric($projectId)) {
            $project = Project::query()
                ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
                ->with('customer')
                ->find((int) $projectId);

            if ($project instanceof Project) {
                return (string) ($project->effectiveCurrency() ?? Currency::userDefault()->value);
            }
        }

        return Currency::userDefault()->value;
    }
}
