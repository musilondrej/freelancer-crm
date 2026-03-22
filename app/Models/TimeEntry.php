<?php

namespace App\Models;

use App\Enums\BillingReportStatus;
use App\Models\Concerns\EnforcesOwner;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class TimeEntry extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'time_entries';

    protected static function newFactory(): TimeEntryFactory
    {
        return TimeEntryFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'project_id',
        'task_id',
        'description',
        'is_billable_override',
        'hourly_rate_override',
        'started_at',
        'ended_at',
        'minutes',
        'locked_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_billable_override' => 'boolean',
            'hourly_rate_override' => 'decimal:2',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'locked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $timeEntry): void {
            $projectId = $timeEntry->getAttribute('project_id');

            if ($projectId === null) {
                $resolvedTaskForProject = self::resolveTask($timeEntry);

                if ($resolvedTaskForProject instanceof Task) {
                    $timeEntry->project_id = $resolvedTaskForProject->project_id;
                    $projectId = $resolvedTaskForProject->project_id;
                }
            }

            if (! is_numeric($projectId)) {
                throw ValidationException::withMessages([
                    'project_id' => __('Project is required for time entries.'),
                ]);
            }

            $resolvedProject = self::resolveProject($timeEntry);

            if (! $resolvedProject instanceof Project) {
                throw ValidationException::withMessages([
                    'project_id' => __('Selected project does not exist.'),
                ]);
            }

            $resolvedTask = self::resolveTask($timeEntry);

            if ($resolvedTask instanceof Task) {
                if ((int) $resolvedTask->project_id !== (int) $timeEntry->project_id) {
                    throw ValidationException::withMessages([
                        'task_id' => __('Selected task does not belong to selected project.'),
                    ]);
                }

                if (! $resolvedTask->isHourly()) {
                    throw ValidationException::withMessages([
                        'task_id' => __('Time entries can only be added to hourly tasks.'),
                    ]);
                }
            }

            $shouldRefreshInheritedRate = $timeEntry->hourly_rate_override === null
                || ($timeEntry->isDirty('task_id') && ! $timeEntry->isDirty('hourly_rate_override'));

            if ($shouldRefreshInheritedRate) {
                $inheritedHourlyRate = self::resolveInheritedHourlyRate($timeEntry);

                if ($inheritedHourlyRate === null) {
                    throw ValidationException::withMessages([
                        'hourly_rate_override' => __('Unable to resolve inherited hourly rate. Set a default or custom hourly rate.'),
                    ]);
                }

                $timeEntry->hourly_rate_override = $inheritedHourlyRate;
            }

            if ($timeEntry->hourly_rate_override === null) {
                throw ValidationException::withMessages([
                    'hourly_rate_override' => __('Hourly rate is required for time entries.'),
                ]);
            }
        });
    }

    private static function resolveInheritedHourlyRate(self $timeEntry): ?float
    {
        $task = self::resolveTask($timeEntry);

        if ($task instanceof Task) {
            return $task->effectiveHourlyRate();
        }

        $project = self::resolveProject($timeEntry);

        if ($project instanceof Project) {
            return $project->effectiveHourlyRate();
        }

        return $timeEntry->owner?->defaultHourlyRateForCurrency();
    }

    private static function resolveTask(self $timeEntry): ?Task
    {
        $taskId = $timeEntry->task_id;

        if ($timeEntry->relationLoaded('task')) {
            $relationTask = $timeEntry->getRelation('task');

            return $relationTask instanceof Task ? $relationTask : null;
        }

        if (! is_numeric($taskId)) {
            return null;
        }

        return Task::query()
            ->with(['project.customer', 'owner'])
            ->find((int) $taskId);
    }

    private static function resolveProject(self $timeEntry): ?Project
    {
        $projectId = $timeEntry->getAttribute('project_id');

        if ($timeEntry->relationLoaded('project')) {
            $relationProject = $timeEntry->getRelation('project');

            return $relationProject instanceof Project ? $relationProject : null;
        }

        if (! is_numeric($projectId)) {
            return null;
        }

        return Project::query()
            ->with(['customer', 'owner'])
            ->find((int) $projectId);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsToMany<BillingReportLine, $this>
     */
    public function billingReportLines(): BelongsToMany
    {
        return $this->belongsToMany(
            BillingReportLine::class,
            'billing_report_line_time_entries',
            'time_entry_id',
            'billing_report_line_id',
        );
    }

    public function effectiveCurrency(): ?string
    {
        return $this->task?->effectiveCurrency()
            ?? $this->project?->effectiveCurrency()
            ?? $this->owner?->default_currency;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function locked(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->whereNotNull('locked_at')
                ->orWhereHas('billingReportLines');
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function running(Builder $query): Builder
    {
        return $query
            ->whereNull('ended_at')
            ->whereNotNull('started_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function finished(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function readyToInvoice(Builder $query, ?int $ownerId = null): Builder
    {
        $query->when($ownerId !== null, fn (Builder $builder): Builder => $builder->where('owner_id', $ownerId));
        $query->whereNotNull('started_at');
        $query->whereNotNull('ended_at');
        $query->whereDoesntHave('billingReportLines');
        $query->where(function (Builder $builder): void {
            $builder->where('is_billable_override', true)
                ->orWhere(function (Builder $nested): void {
                    $nested
                        ->whereNull('is_billable_override')
                        ->where(function (Builder $taskAware): void {
                            $taskAware
                                ->whereDoesntHave('task')
                                ->orWhereHas('task', fn (Builder $taskQuery): Builder => $taskQuery->where('is_billable', true));
                        });
                });
        });

        return $query;
    }

    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    public function resolvedMinutes(): int
    {
        if ($this->minutes !== null) {
            return max(0, (int) $this->minutes);
        }

        $rawStartedAt = $this->getAttribute('started_at');
        $rawEndedAt = $this->getAttribute('ended_at');

        $startedAt = $rawStartedAt instanceof CarbonInterface
            ? CarbonImmutable::instance($rawStartedAt)
            : ($rawStartedAt !== null ? CarbonImmutable::parse((string) $rawStartedAt) : null);
        $endedAt = $rawEndedAt instanceof CarbonInterface
            ? CarbonImmutable::instance($rawEndedAt)
            : ($rawEndedAt !== null ? CarbonImmutable::parse((string) $rawEndedAt) : null);

        if ($startedAt instanceof CarbonImmutable && $endedAt instanceof CarbonImmutable) {
            return max(0, (int) ceil($startedAt->diffInSeconds($endedAt) / 60));
        }

        return 0;
    }

    public function resolvedHours(): float
    {
        return round($this->resolvedMinutes() / 60, 2);
    }

    public function effectiveHourlyRate(?Task $task = null): ?float
    {
        if ($this->hourly_rate_override !== null) {
            return (float) $this->hourly_rate_override;
        }

        $resolvedTask = $task ?? $this->task;

        if (! $resolvedTask instanceof Task) {
            $resolvedProject = $this->project;

            if ($resolvedProject instanceof Project) {
                return $resolvedProject->effectiveHourlyRate();
            }

            return $this->owner?->defaultHourlyRateForCurrency();
        }

        $resolvedTaskRate = $resolvedTask->effectiveHourlyRate();

        if ($resolvedTaskRate !== null) {
            return $resolvedTaskRate;
        }

        return $resolvedTask->owner?->defaultHourlyRateForCurrency(
            $resolvedTask->effectiveCurrency(),
        );
    }

    public function calculatedAmount(?Task $task = null): ?float
    {
        $resolvedTask = $task ?? $this->task;
        $defaultBillable = $resolvedTask instanceof Task
            ? (bool) $resolvedTask->is_billable
            : true;

        if (! $this->effectiveBillable($defaultBillable)) {
            return 0.0;
        }

        $rate = $this->effectiveHourlyRate($resolvedTask);

        if ($rate === null) {
            return null;
        }

        return $rate * ((float) $this->resolvedMinutes() / 60);
    }

    public function effectiveBillable(?bool $taskIsBillable = true): bool
    {
        if ($this->is_billable_override !== null) {
            return (bool) $this->is_billable_override;
        }

        return $taskIsBillable ?? true;
    }

    public function isInvoiced(): bool
    {
        if ($this->relationLoaded('billingReportLines')) {
            return $this->billingReportLines->contains(
                fn (BillingReportLine $line): bool => $line->relationLoaded('billingReport')
                    && $line->billingReport?->isFinalized() === true
            );
        }

        return $this->billingReportLines()
            ->whereHas('billingReport', fn (Builder $q): Builder => $q->where('status', BillingReportStatus::Finalized))
            ->exists();
    }

    public function isLocked(): bool
    {
        if ($this->locked_at !== null) {
            return true;
        }

        if ($this->relationLoaded('billingReportLines')) {
            return $this->billingReportLines->isNotEmpty();
        }

        return $this->billingReportLines()->exists();
    }

    public function isReadyToInvoice(): bool
    {
        if ($this->isRunning() || $this->resolvedMinutes() <= 0) {
            return false;
        }

        if ($this->isLocked()) {
            return false;
        }

        return $this->effectiveBillable($this->task?->is_billable);
    }

    public function lock(): void
    {
        $this->forceFill([
            'locked_at' => now(),
        ])->save();
    }

    public function unlock(): void
    {
        $this->forceFill([
            'locked_at' => null,
        ])->save();
    }
}
