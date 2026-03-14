<?php

namespace App\Livewire;

use App\Enums\ProjectActivityType;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectActivityStatusOption;
use App\Models\ProjectStatusOption;
use App\Models\Worklog;
use App\Support\TimeTrackingRounding;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Livewire\Component;

class TopbarTimeTracker extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private ?Worklog $cachedActiveSession = null;

    private bool $hasCachedActiveSession = false;

    public function trackTimeAction(): Action
    {
        return Action::make('trackTime')
            ->label('Track time')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('gray')
            ->modalHeading('Create Time Entry')
            ->modalIcon(Heroicon::OutlinedClock)
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Save')
            ->modalCancelActionLabel('Close')
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
                                    ->label('From')
                                    ->required()
                                    ->seconds(false)
                                    ->native(false),
                                DateTimePicker::make('finished_at')
                                    ->label('To')
                                    ->seconds(false)
                                    ->helperText('Leave empty to start a running timer.'),
                            ]),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->options(fn (): array => $this->customerOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('project_id', null)),
                        Select::make('project_id')
                            ->label('Project')
                            ->required()
                            ->options(fn (Get $get): array => $this->projectOptions($get('customer_id')))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->projectOptionLabel($value))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('activity_id', null)),
                        Select::make('activity_id')
                            ->label('Activity template')
                            ->required()
                            ->options(fn (Get $get): array => $this->activityOptions($get('project_id')))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->activityOptionLabel($value))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id')))
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                $activity = $this->resolveActivityById($state);

                                if (! $activity instanceof Activity) {
                                    return;
                                }

                                $set('is_billable', $activity->is_billable);
                                $set('unit_rate', $activity->default_hourly_rate);
                            }),
                        Textarea::make('description')
                            ->rows(4),
                    ])
                    ->columns(1),
                Section::make('Advanced settings')
                    ->schema([
                        TextInput::make('unit_rate')
                            ->label('Hourly rate override')
                            ->numeric()
                            ->minValue(0)
                            ->suffix(fn (Get $get): string => $this->unitRateCurrencySuffix($get('project_id')))
                            ->helperText(fn (Get $get): string => $this->rateHelperText($get('project_id'), $get('activity_id')))
                            ->columnSpanFull(),
                        Toggle::make('is_billable')
                            ->label('Billable')
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
                    ->title('End date and time must be after start')
                    ->danger()
                    ->send();

                return;
            }

            if ($finishedAt->greaterThan(CarbonImmutable::now()->addMinute())) {
                Notification::make()
                    ->title('End date and time cannot be in the future')
                    ->danger()
                    ->send();

                return;
            }
        }

        if (! $isManualEntry && $startedAt->greaterThan(CarbonImmutable::now()->addMinute())) {
            Notification::make()
                ->title('Start date and time cannot be in the future')
                ->danger()
                ->send();

            return;
        }

        $project = Project::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', $this->trackableProjectStatuses())
            ->when(
                isset($data['customer_id']) && is_numeric($data['customer_id']),
                fn (Builder $query) => $query->where('client_id', (int) $data['customer_id']),
            )
            ->find((int) $data['project_id']);

        if ($project === null) {
            Notification::make()
                ->title('Project is not available')
                ->danger()
                ->send();

            return;
        }

        $activity = $this->resolveSelectableActivity(
            ownerId: $ownerId,
            projectId: $project->id,
            activityId: $data['activity_id'] ?? null,
        );

        if (! $activity instanceof Activity) {
            Notification::make()
                ->title('Activity is not available')
                ->danger()
                ->send();

            return;
        }

        $unitRateOverride = isset($data['unit_rate']) && is_numeric($data['unit_rate'])
            ? (float) $data['unit_rate']
            : null;
        $activityDefaultRate = $activity->default_hourly_rate !== null
            ? (float) $activity->default_hourly_rate
            : null;

        $basePayload = [
            'owner_id' => $ownerId,
            'project_id' => $project->id,
            'activity_id' => $activity->id,
            'title' => $activity->name,
            'description' => $this->normalizedDescription($data['description'] ?? null),
            'type' => ProjectActivityType::Hourly,
            'is_running' => false,
            'is_billable' => (bool) ($data['is_billable'] ?? true),
            'unit_rate' => $unitRateOverride ?? $activityDefaultRate,
            'currency' => $project->effectiveCurrency(),
            'started_at' => $startedAt,
            'meta' => [
                'source' => $isManualEntry ? 'topbar_timer_manual' : 'topbar_timer',
                'activity_name' => $activity->name,
            ],
        ];

        if ($isManualEntry) {
            $trackedMinutes = TimeTrackingRounding::roundMinutes(
                (int) ceil($startedAt->diffInSeconds($finishedAt) / 60),
                $ownerId,
            );

            Worklog::query()->create([
                ...$basePayload,
                'status' => $this->doneActivityStatusCode(),
                'is_running' => false,
                'finished_at' => $finishedAt,
                'tracked_minutes' => $trackedMinutes,
                'quantity' => round($trackedMinutes / 60, 2),
            ]);

            $project->update([
                'last_activity_at' => $finishedAt,
            ]);

            Notification::make()
                ->title('Time entry saved')
                ->body(sprintf('Logged %s.', $this->formatTrackedTime($trackedMinutes)))
                ->success()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        $sessionStarted = $this->startRunningSession($basePayload, $project);

        if (! $sessionStarted) {
            Notification::make()
                ->title('A timer is already running')
                ->body('Stop the current timer first.')
                ->warning()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        Notification::make()
            ->title('Tracker started')
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

        $runningSessions = Worklog::query()
            ->where('owner_id', $ownerId)
            ->where('type', ProjectActivityType::Hourly->value)
            ->where('is_running', true)
            ->whereNull('finished_at')
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->get();

        if ($runningSessions->isEmpty()) {
            Notification::make()
                ->title('No running timer')
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
                'status' => $this->doneActivityStatusCode(),
                'is_running' => false,
                'finished_at' => $finishedAt,
                'tracked_minutes' => $trackedMinutes,
                'quantity' => round($trackedMinutes / 60, 2),
            ])->save();

            $runningSession->project?->update([
                'last_activity_at' => $finishedAt,
            ]);
        }

        if ($totalTrackedMinutes === 0) {
            Notification::make()
                ->title('No valid running timer found')
                ->warning()
                ->send();

            $this->clearActiveSessionCache();

            return;
        }

        Notification::make()
            ->title('Time logged')
            ->body(sprintf('Logged %s.', $this->formatTrackedTime($totalTrackedMinutes)))
            ->success()
            ->send();
        $this->clearActiveSessionCache();
    }

    public function render(): View
    {
        return view('livewire.topbar-time-tracker', [
            'hasActiveSession' => $this->activeSession() instanceof Worklog,
        ]);
    }

    /**
     * @return array{started_at: string, finished_at: null, unit_rate: float|null, customer_id: int|null, project_id: int|null, activity_id: int|null, description: string|null, is_billable: bool}
     */
    private function defaultFormData(): array
    {
        $now = CarbonImmutable::now();
        $activeSession = $this->activeSession();

        if (! $activeSession instanceof Worklog) {
            return [
                'started_at' => $now->format('Y-m-d H:i'),
                'finished_at' => null,
                'unit_rate' => null,
                'customer_id' => null,
                'project_id' => null,
                'activity_id' => null,
                'description' => null,
                'is_billable' => true,
            ];
        }

        $startedAt = CarbonImmutable::make($activeSession->started_at)?->format('Y-m-d H:i') ?? $now->format('Y-m-d H:i');

        return [
            'started_at' => $startedAt,
            'finished_at' => null,
            'unit_rate' => null,
            'customer_id' => $activeSession->project?->client_id,
            'project_id' => $activeSession->project_id,
            'activity_id' => $activeSession->activity_id,
            'description' => $activeSession->description,
            'is_billable' => (bool) $activeSession->is_billable,
        ];
    }

    private function normalizedDescription(mixed $description): ?string
    {
        $normalizedDescription = is_string($description) ? trim($description) : null;

        return $normalizedDescription !== '' ? $normalizedDescription : null;
    }

    private function activeSession(): ?Worklog
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

        $this->cachedActiveSession = Worklog::query()
            ->where('owner_id', $ownerId)
            ->where('type', ProjectActivityType::Hourly->value)
            ->where('is_running', true)
            ->whereNull('finished_at')
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->with('project:id,name,client_id', 'activity:id,name,default_hourly_rate,is_billable,project_id')
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
                fn (Builder $query) => $query->where('client_id', (int) $customerId),
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
    private function activityOptions(mixed $projectId): array
    {
        if (! is_numeric($projectId)) {
            return [];
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
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

    private function activityOptionLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return $this->resolveActivityById($value)?->name;
    }

    private function rateHelperText(mixed $projectId, mixed $activityId): string
    {
        $activity = $this->resolveActivityById($activityId);

        if ($activity instanceof Activity && $activity->default_hourly_rate !== null) {
            return sprintf('Default activity rate: %s / h', Number::format((float) $activity->default_hourly_rate, precision: 2));
        }

        if (! is_numeric($projectId)) {
            return 'Leave empty to use the project hourly rate.';
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return 'Leave empty to use the project hourly rate.';
        }

        $project = Project::query()
            ->where('owner_id', $ownerId)
            ->find((int) $projectId);

        if (! $project instanceof Project) {
            return 'Leave empty to use the project hourly rate.';
        }

        $projectHourlyRate = $project->effectiveHourlyRate();
        $currency = $project->effectiveCurrency();

        if ($projectHourlyRate === null) {
            return sprintf('No default project rate set (%s).', $currency);
        }

        return sprintf(
            'Default project rate: %s %s / h',
            Number::format($projectHourlyRate, precision: 2),
            $currency,
        );
    }

    private function unitRateCurrencySuffix(mixed $projectId): string
    {
        $ownerDefaultCurrency = (string) (data_get(Filament::auth()->user(), 'default_currency', 'CZK'));

        if (! is_numeric($projectId)) {
            return $ownerDefaultCurrency;
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return $ownerDefaultCurrency;
        }

        $currency = Project::query()
            ->where('owner_id', $ownerId)
            ->whereKey((int) $projectId)
            ->first()?->effectiveCurrency();

        return (string) ($currency ?? $ownerDefaultCurrency);
    }

    private function resolveActivityById(mixed $activityId): ?Activity
    {
        if (! is_numeric($activityId)) {
            return null;
        }

        $ownerId = Filament::auth()->id();

        if ($ownerId === null) {
            return null;
        }

        return Activity::query()
            ->where('owner_id', $ownerId)
            ->whereKey((int) $activityId)
            ->first();
    }

    private function resolveSelectableActivity(int $ownerId, int $projectId, mixed $activityId): ?Activity
    {
        if (! is_numeric($activityId)) {
            return null;
        }

        return Activity::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->whereKey((int) $activityId)
            ->where(function (Builder $query) use ($projectId): void {
                $query->whereNull('project_id')
                    ->orWhere('project_id', $projectId);
            })
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

                $hasRunningSession = Worklog::query()
                    ->where('owner_id', $ownerId)
                    ->where('type', ProjectActivityType::Hourly->value)
                    ->where('is_running', true)
                    ->whereNull('finished_at')
                    ->whereNotNull('started_at')
                    ->lockForUpdate()
                    ->exists();

                if ($hasRunningSession) {
                    return false;
                }

                Worklog::query()->create([
                    ...$basePayload,
                    'status' => $this->runningActivityStatusCode(),
                    'is_running' => true,
                ]);

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
            && str_contains($exception->getMessage(), 'worklogs_owner_running_hourly_unique');
    }

    private function formatTrackedTime(int $trackedMinutes): string
    {
        $hours = intdiv($trackedMinutes, 60);
        $minutes = $trackedMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dm', $minutes);
    }

    private function stopActionLabel(): string
    {
        $activeSession = $this->activeSession();

        if (! $activeSession instanceof Worklog) {
            return 'Stop';
        }

        return sprintf('Stop %s', $this->elapsedClockLabel($activeSession));
    }

    private function elapsedClockLabel(Worklog $activeSession): string
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
     * @return array<int, string>
     */
    private function trackableProjectStatuses(): array
    {
        return ProjectStatusOption::trackableCodesForOwner(Filament::auth()->id());
    }

    private function runningActivityStatusCode(): string
    {
        return ProjectActivityStatusOption::runningCodeForOwner(Filament::auth()->id());
    }

    private function doneActivityStatusCode(): string
    {
        $doneCodes = ProjectActivityStatusOption::doneCodesForOwner(Filament::auth()->id());

        return $doneCodes[0] ?? ProjectActivityStatusOption::defaultCodeForOwner(Filament::auth()->id());
    }
}
