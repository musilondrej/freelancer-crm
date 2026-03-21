<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'is_invoiced',
        'invoice_reference',
        'invoiced_at',
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
            'is_invoiced' => 'boolean',
            'invoiced_at' => 'datetime',
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

            $normalizedInvoiceReference = trim((string) ($timeEntry->invoice_reference ?? ''));
            $hasInvoiceReference = $normalizedInvoiceReference !== '';
            $hasInvoicedAt = $timeEntry->invoiced_at !== null;

            if ((bool) $timeEntry->is_invoiced || $hasInvoiceReference || $hasInvoicedAt) {
                if (! $hasInvoiceReference) {
                    throw ValidationException::withMessages([
                        'invoice_reference' => __('Invoice reference is required for invoiced time entries.'),
                    ]);
                }

                $timeEntry->is_invoiced = true;
                $timeEntry->invoice_reference = $normalizedInvoiceReference;

                if ($timeEntry->invoiced_at === null) {
                    $timeEntry->invoiced_at = now();
                }

                return;
            }

            $timeEntry->is_invoiced = false;
            $timeEntry->invoice_reference = '';
            $timeEntry->invoiced_at = null;
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
    protected function billed(Builder $query): Builder
    {
        return $query
            ->where('is_invoiced', true)
            ->whereNotNull('invoice_reference')
            ->where('invoice_reference', '!=', '');
    }

    /**
     * Locked entries are those assigned to a timesheet (have an invoice reference).
     * This mirrors the isLocked() instance method for use in query context.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function locked(Builder $query): Builder
    {
        return $query
            ->where('is_invoiced', true)
            ->whereNotNull('invoice_reference')
            ->where('invoice_reference', '!=', '');
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
        $query->where('is_invoiced', false);
        $query->where(fn (Builder $builder): Builder => $builder
            ->whereNull('invoice_reference')
            ->orWhere('invoice_reference', ''));
        $query->whereNull('invoiced_at');
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
        if ((bool) $this->is_invoiced) {
            return true;
        }

        if (trim((string) $this->invoice_reference) !== '') {
            return true;
        }

        return $this->invoiced_at !== null;
    }

    /**
     * A time entry is locked once it has been assigned to a timesheet (has an invoice reference).
     * This is derived state — no separate field needed.
     */
    public function isLocked(): bool
    {
        return (bool) $this->is_invoiced && $this->resolvedInvoiceReference() !== null;
    }

    public function isReadyToInvoice(): bool
    {
        if ($this->isRunning() || $this->isInvoiced() || $this->resolvedMinutes() <= 0) {
            return false;
        }

        return $this->effectiveBillable($this->task?->is_billable);
    }

    public function markAsInvoiced(?string $invoiceReference = null, CarbonInterface|string|null $invoicedAt = null): void
    {
        if ($this->isInvoiced()) {
            return;
        }

        $resolvedAt = $invoicedAt instanceof CarbonInterface
            ? $invoicedAt
            : ($invoicedAt !== null ? CarbonImmutable::parse($invoicedAt) : now());

        $this->update([
            'is_invoiced' => true,
            'invoice_reference' => $invoiceReference ?? '',
            'invoiced_at' => $resolvedAt,
        ]);

        $this->refresh();
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

    public function resolvedInvoiceReference(): ?string
    {
        $ref = trim((string) $this->invoice_reference);

        return $ref !== '' ? $ref : null;
    }

    public function resolvedInvoicedAt(): ?CarbonInterface
    {
        $rawInvoicedAt = $this->getAttribute('invoiced_at');

        if ($rawInvoicedAt instanceof CarbonInterface) {
            return $rawInvoicedAt;
        }

        if (is_string($rawInvoicedAt) && trim($rawInvoicedAt) !== '') {
            return CarbonImmutable::parse($rawInvoicedAt);
        }

        return null;
    }
}
