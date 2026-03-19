<?php

namespace App\Livewire;

use App\Enums\ProjectStatus;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\TimeDuration;
use App\Support\TimeTrackingRounding;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TopbarTimeTracker extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private ?TimeEntry $cachedActiveSession = null;

    private bool $hasCachedActiveSession = false;

    public function trackTimeAction(): Action
    {
        return Action::make('trackTime')
            ->label(__('Track time'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('gray')
            ->modalHeading(__('Create time entry'))
            ->modalIcon(Heroicon::OutlinedClock)
            ->modalWidth('5xl')
            ->modalSubmitActionLabel(__('Save'))
            ->modalCancelActionLabel(__('Close'))
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->fillForm(fn (): array => $this->defaultFormData())
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->label(__('From'))
                                    ->required()
                                    ->seconds(false)
                                    ->native(false),
                                DateTimePicker::make('finished_at')
                                    ->label(__('To'))
                                    ->seconds(false)
                                    ->helperText(__('Leave empty to start a running timer.')),
                            ]),
                        Select::make('customer_id')
                            ->label(__('Customer'))
                            ->options(fn (): array => $this->customerOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('project_id', null)),
                        Select::make('project_id')
                            ->label(__('Project'))
                            ->required()
                            ->options(fn (Get $get): array => $this->projectOptions($get('customer_id')))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->projectOptionLabel($value))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('task_id', null)),
                        Select::make('task_id')
                            ->label(__('Task'))
                            ->options(fn (Get $get): array => $this->taskOptions($get('project_id')))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->taskOptionLabel($value))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id')))
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $task = $this->resolveTaskById($state);

                                if (! $task instanceof Task) {
                                    return;
                                }

                                $set('is_billable', (bool) $task->is_billable);
                            }),
                        Textarea::make('description')
                            ->rows(4),
                    ])
                    ->columns(1),
                Section::make(__('Advanced settings'))
                    ->schema([
                        Toggle::make('is_billable')
                            ->label(__('Billable'))
                            ->default(true)
                            ->formatStateUsing(fn (mixed $state): bool => $state === null ? true : (bool) $state)
                            ->inline(false),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->action(function (array $data): void {
                $this->createTimeEntry($data);
            });
    }

    public function stopTimerAction(): Action
    {
        return Action::make('stopTimer')
            ->label(fn (): string => $this->stopActionLabel())
            ->icon(Heroicon::OutlinedStopCircle)
            ->color('danger')
            ->action(function (): void {
                $this->stopTimer();
            });
    }

    public function createTimeEntry(array $data): void
    {
        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return;
        }

        $finishedAtInput = $data['finished_at'] ?? null;
        $isManualEntry = $finishedAtInput !== null && (string) $finishedAtInput !== '';

        $startedAt = CarbonImmutable::parse((string) $data['started_at'], config('app.timezone'));

        $finishedAt = null;

        if ($isManualEntry) {
            $finishedAt = CarbonImmutable::parse((string) $finishedAtInput, config('app.timezone'));

            if ($finishedAt->lessThanOrEqualTo($startedAt)) {
                Notification::make()
                    ->title(__('End date and time must be after start'))
                    ->danger()
                    ->send();

                return;
            }

            if ($finishedAt->greaterThan(CarbonImmutable::now()->addMinute())) {
                Notification::make()
                    ->title(__('End date and time cannot be in the future'))
                    ->danger()
                    ->send();

                return;
            }
        }

        if (! $isManualEntry && $startedAt->greaterThan(CarbonImmutable::now()->addMinute())) {
            Notification::make()
                ->title(__('Start date and time cannot be in the future'))
                ->danger()
                ->send();

            return;
        }

        $project = Project::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', $this->trackableProjectStatuses())
            ->when(
                isset($data['customer_id']) && is_numeric($data['customer_id']),
                fn (Builder $query) => $query->where('customer_id', (int) $data['customer_id']),
            )
            ->find((int) $data['project_id']);

        if ($project === null) {
            Notification::make()
                ->title(__('Project is not available'))
                ->danger()
                ->send();

            return;
        }

        $task = $this->resolveSelectableTask(
            ownerId: $ownerId,
            projectId: $project->id,
            taskId: $data['task_id'] ?? null,
        );

        if (($data['task_id'] ?? null) !== null && ! $task instanceof Task) {
            Notification::make()
                ->title(__('Task is not available'))
                ->danger()
                ->send();

            return;
        }

        $taskBillableDefault = $task instanceof Task
            ? (bool) $task->is_billable
            : true;

        $isBillable = (bool) ($data['is_billable'] ?? $taskBillableDefault);
        $billableOverride = $isBillable === $taskBillableDefault
            ? null
            : $isBillable;

        $basePayload = [
            'owner_id' => $ownerId,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'description' => $this->normalizedDescription($data['description'] ?? null),
            'is_billable_override' => $billableOverride,
            'started_at' => $startedAt,
            'meta' => [
                'source' => $isManualEntry ? 'topbar_timer_manual' : 'topbar_timer',
                'task_title' => $task?->title,
            ],
        ];

        if ($isManualEntry) {
            $trackedMinutes = TimeTrackingRounding::roundMinutes(
                (int) ceil($startedAt->diffInSeconds($finishedAt) / 60),
                $ownerId,
            );

            TimeEntry::query()->create([
                ...$basePayload,
                'ended_at' => $finishedAt,
                'minutes' => $trackedMinutes,
            ]);

            $project->update([
                'last_activity_at' => $finishedAt,
            ]);

            Notification::make()
                ->title(__('Time entry saved'))
                ->body(__('Logged :duration.', ['duration' => TimeDuration::format($trackedMinutes)]))
                ->success()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        $sessionStarted = $this->startRunningSession($basePayload, $project);

        if (! $sessionStarted) {
            Notification::make()
                ->title(__('A timer is already running'))
                ->body(__('Stop the current timer first.'))
                ->warning()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        Notification::make()
            ->title(__('Tracker started'))
            ->success()
            ->send();
        $this->clearActiveSessionCache();
    }

    public function stopTimer(): void
    {
        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return;
        }

        /** @var Collection<int, TimeEntry> $runningSessions */
        $runningSessions = TimeEntry::query()
            ->where('owner_id', $ownerId)
            ->running()
            ->latest('started_at')
            ->with('task.project')
            ->get();

        if ($runningSessions->isEmpty()) {
            Notification::make()
                ->title(__('No running timer'))
                ->warning()
                ->send();

            return;
        }

        $finishedAt = CarbonImmutable::now();
        $totalTrackedMinutes = 0;

        foreach ($runningSessions as $runningSession) {
            $startedAt = CarbonImmutable::make($runningSession->started_at);

            if (! $startedAt instanceof CarbonImmutable) {
                continue;
            }

            $trackedMinutes = TimeTrackingRounding::roundMinutes(
                (int) ceil($startedAt->diffInSeconds($finishedAt) / 60),
                $ownerId,
            );
            $totalTrackedMinutes += $trackedMinutes;

            $runningSession->forceFill([
                'ended_at' => $finishedAt,
                'minutes' => $trackedMinutes,
            ])->save();

            $runningSession->project?->update([
                'last_activity_at' => $finishedAt,
            ]);
        }

        if ($totalTrackedMinutes === 0) {
            Notification::make()
                ->title(__('No valid running timer found'))
                ->warning()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        Notification::make()
            ->title(__('Time logged'))
            ->body(__('Logged :duration.', ['duration' => TimeDuration::format($totalTrackedMinutes)]))
            ->success()
            ->send();
        $this->clearActiveSessionCache();
    }

    public function render(): View
    {
        return view('livewire.topbar-time-tracker', [
            'hasActiveSession' => $this->activeSession() instanceof TimeEntry,
        ]);
    }

    /**
     * @return array{started_at: string, finished_at: null, customer_id: int|null, project_id: int|null, task_id: int|null, description: string|null, is_billable: bool}
     */
    private function defaultFormData(): array
    {
        $now = CarbonImmutable::now();
        $activeSession = $this->activeSession();

        if (! $activeSession instanceof TimeEntry) {
            return [
                'started_at' => $now->format('Y-m-d H:i'),
                'finished_at' => null,
                'customer_id' => null,
                'project_id' => null,
                'task_id' => null,
                'description' => null,
                'is_billable' => true,
            ];
        }

        $startedAt = CarbonImmutable::make($activeSession->started_at)?->format('Y-m-d H:i') ?? $now->format('Y-m-d H:i');
        $project = $activeSession->project;
        $task = $activeSession->task;

        return [
            'started_at' => $startedAt,
            'finished_at' => null,
            'customer_id' => $project?->customer_id,
            'project_id' => $project?->id,
            'task_id' => $activeSession->task_id,
            'description' => $activeSession->description,
            'is_billable' => $task instanceof Task
                ? $activeSession->effectiveBillable((bool) $task->is_billable)
                : true,
        ];
    }

    private function normalizedDescription(mixed $description): ?string
    {
        $normalizedDescription = is_string($description) ? trim($description) : null;

        return $normalizedDescription !== '' ? $normalizedDescription : null;
    }

    private function activeSession(): ?TimeEntry
    {
        if ($this->hasCachedActiveSession) {
            return $this->cachedActiveSession;
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            $this->cachedActiveSession = null;
            $this->hasCachedActiveSession = true;

            return null;
        }

        $this->cachedActiveSession = TimeEntry::query()
            ->where('owner_id', $ownerId)
            ->running()
            ->latest('started_at')
            ->with(
                'project:id,name,customer_id',
                'task:id,title,project_id,is_billable,billing_model',
            )
            ->first();
        $this->hasCachedActiveSession = true;

        return $this->cachedActiveSession;
    }

    /**
     * @return array<int, string>
     */
    private function customerOptions(): array
    {
        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return [];
        }

        return Customer::query()
            ->where('owner_id', $ownerId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function projectOptions(mixed $customerId): array
    {
        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return [];
        }

        return Project::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', $this->trackableProjectStatuses())
            ->when(
                is_numeric($customerId),
                fn (Builder $query) => $query->where('customer_id', (int) $customerId),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function projectOptionLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return null;
        }

        return Project::query()
            ->where('owner_id', $ownerId)
            ->whereKey((int) $value)
            ->value('name');
    }

    /**
     * @return array<int, string>
     */
    private function taskOptions(mixed $projectId): array
    {
        if (! is_numeric($projectId)) {
            return [];
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return [];
        }

        return Task::query()
            ->where('owner_id', $ownerId)
            ->where('project_id', (int) $projectId)
            ->where('billing_model', TaskBillingModel::Hourly->value)
            ->whereIn('status', TaskStatus::openValues())
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    private function taskOptionLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return $this->resolveTaskById($value)?->title;
    }

    private function resolveTaskById(mixed $taskId): ?Task
    {
        if (! is_numeric($taskId)) {
            return null;
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return null;
        }

        return Task::query()
            ->where('owner_id', $ownerId)
            ->whereKey((int) $taskId)
            ->first();
    }

    private function resolveSelectableTask(int $ownerId, int $projectId, mixed $taskId): ?Task
    {
        if (! is_numeric($taskId)) {
            return null;
        }

        return Task::query()
            ->where('owner_id', $ownerId)
            ->where('project_id', $projectId)
            ->where('billing_model', TaskBillingModel::Hourly->value)
            ->whereIn('status', TaskStatus::openValues())
            ->whereKey((int) $taskId)
            ->first();
    }

    private function clearActiveSessionCache(): void
    {
        $this->hasCachedActiveSession = false;
        $this->cachedActiveSession = null;
    }

    /**
     * @param  array<string, mixed>  $basePayload
     */
    private function startRunningSession(array $basePayload, Project $project): bool
    {
        try {
            return DB::transaction(function () use ($basePayload, $project): bool {
                $ownerId = (int) $basePayload['owner_id'];

                $hasRunningSession = TimeEntry::query()
                    ->where('owner_id', $ownerId)
                    ->running()
                    ->lockForUpdate()
                    ->exists();

                if ($hasRunningSession) {
                    return false;
                }

                TimeEntry::query()->create($basePayload);

                $project->update([
                    'last_activity_at' => now(),
                ]);

                return true;
            }, 3);
        } catch (QueryException $queryException) {
            if ($this->isRunningSessionUniqueViolation($queryException)) {
                return false;
            }

            throw $queryException;
        }
    }

    private function isRunningSessionUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'time_entries_owner_running_unique');
    }

    private function stopActionLabel(): string
    {
        $activeSession = $this->activeSession();

        if (! $activeSession instanceof TimeEntry) {
            return __('Stop');
        }

        return __('Stop :elapsed', ['elapsed' => $this->elapsedClockLabel($activeSession)]);
    }

    private function elapsedClockLabel(TimeEntry $activeSession): string
    {
        $startedAt = CarbonImmutable::make($activeSession->started_at);

        if (! $startedAt instanceof CarbonImmutable) {
            return '00:00';
        }

        $elapsedMinutes = max(0, $startedAt->diffInMinutes(CarbonImmutable::now()));
        $hours = intdiv($elapsedMinutes, 60);
        $minutes = $elapsedMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * @return list<string>
     */
    private function trackableProjectStatuses(): array
    {
        return ProjectStatus::trackableValues();
    }
}
