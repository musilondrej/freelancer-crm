<?php

namespace App\Models;

use App\Enums\BillingReportStatus;
use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Concerns\EnforcesOwner;
use App\Support\EnumValue;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * @property TaskStatus $status
 * @property TaskBillingModel $billing_model
 * @property Priority|null $priority
 */
class Task extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'tasks';

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'project_id',
        'title',
        'description',
        'billing_model',
        'status',
        'priority',
        'is_billable',
        'track_time',
        'currency',
        'quantity',
        'hourly_rate_override',
        'fixed_price',
        'estimated_minutes',
        'due_date',
        'completed_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_model' => TaskBillingModel::class,
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'estimated_minutes' => 'integer',
            'is_billable' => 'boolean',
            'track_time' => 'boolean',
            'quantity' => 'decimal:2',
            'hourly_rate_override' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if ($task->getAttribute('priority') === null) {
                $task->priority = Priority::Normal;
            }

            $resolvedBillingModel = EnumValue::from($task->getAttribute('billing_model'));

            if ($resolvedBillingModel === TaskBillingModel::Hourly->value) {
                $task->track_time = true;
                $task->quantity = null;
            }

            if ($resolvedBillingModel === TaskBillingModel::FixedPrice->value) {
                $hasTimeEntries = $task->exists && $task->timeEntries()->exists();

                if ($hasTimeEntries) {
                    throw ValidationException::withMessages([
                        'billing_model' => __('Cannot switch task to fixed price while time entries exist.'),
                    ]);
                }

                $task->track_time = false;
                $task->quantity = null;
                $task->hourly_rate_override = null;
            }
        });
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
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return HasOne<BillingReportLine, $this>
     */
    public function billingReportLine(): HasOne
    {
        return $this->hasOne(BillingReportLine::class);
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function effectiveCurrency(): ?string
    {
        /** @var Project|null $project */
        $project = $this->project;

        return $this->currency ?? $project?->effectiveCurrency();
    }

    public function effectiveHourlyRate(): ?float
    {
        $billingModel = EnumValue::from($this->getAttribute('billing_model'));

        if ($billingModel !== TaskBillingModel::Hourly->value) {
            return null;
        }

        /** @var Project|null $project */
        $project = $this->project;
        $effectiveCurrency = $this->effectiveCurrency();

        if ($this->hourly_rate_override !== null) {
            return (float) $this->hourly_rate_override;
        }

        $effectiveHourlyRate = $project?->effectiveHourlyRate($effectiveCurrency);

        if ($effectiveHourlyRate !== null) {
            return (float) $effectiveHourlyRate;
        }

        return $this->owner?->defaultHourlyRateForCurrency($effectiveCurrency);
    }

    public function calculatedAmount(): ?float
    {
        $billingModel = EnumValue::from($this->getAttribute('billing_model'));

        if (! $this->is_billable) {
            return 0.0;
        }

        if ($billingModel === TaskBillingModel::FixedPrice->value) {
            return $this->fixed_price !== null
                ? (float) $this->fixed_price
                : null;
        }

        $rate = $this->effectiveHourlyRate();

        $timeEntries = $this->relationLoaded('timeEntries')
            ? $this->timeEntries
            : $this->timeEntries()->get();

        if ($timeEntries->isNotEmpty()) {
            $hasBillableEntries = false;
            $amount = 0.0;

            foreach ($timeEntries as $timeEntry) {
                if (! $timeEntry->effectiveBillable((bool) $this->is_billable)) {
                    continue;
                }

                $hasBillableEntries = true;
                $timeEntryAmount = $timeEntry->calculatedAmount($this);

                if ($timeEntryAmount === null) {
                    return null;
                }

                $amount += $timeEntryAmount;
            }

            if ($hasBillableEntries) {
                return $amount;
            }
        }

        if ($rate === null) {
            return null;
        }

        $billableTrackedMinutes = $this->billableTrackedMinutes();

        if ($billableTrackedMinutes > 0) {
            return $rate * ((float) $billableTrackedMinutes / 60);
        }

        return null;
    }

    public function totalTrackedMinutes(): int
    {
        if ($this->relationLoaded('timeEntries')) {
            /** @var Collection<int, TimeEntry> $entries */
            $entries = $this->getRelation('timeEntries');

            return (int) $entries->sum(fn (TimeEntry $entry): int => $entry->resolvedMinutes());
        }

        return (int) $this->timeEntries()->sum('minutes');
    }

    public function billableTrackedMinutes(): int
    {
        if ($this->relationLoaded('timeEntries')) {
            /** @var Collection<int, TimeEntry> $entries */
            $entries = $this->getRelation('timeEntries');

            return (int) $entries
                ->filter(fn (TimeEntry $entry): bool => $entry->effectiveBillable((bool) $this->is_billable))
                ->sum(fn (TimeEntry $entry): int => $entry->resolvedMinutes());
        }

        return (int) $this->timeEntries()
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('is_billable_override')
                ->orWhere('is_billable_override', true))
            ->sum('minutes');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::openValues());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function done(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::doneValues());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function billable(Builder $query): Builder
    {
        return $query->where('is_billable', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function hourly(Builder $query): Builder
    {
        return $query->where('billing_model', TaskBillingModel::Hourly);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function readyToInvoice(Builder $query, ?int $ownerId = null): Builder
    {
        $query->when($ownerId !== null, fn (Builder $builder): Builder => $builder->where('owner_id', $ownerId));
        $query->where('is_billable', true);
        $query->whereIn('status', TaskStatus::doneValues());
        $query->whereDoesntHave('billingReportLine');

        return $query;
    }

    public function isHourly(): bool
    {
        return $this->billing_model === TaskBillingModel::Hourly;
    }

    public function isFixedPrice(): bool
    {
        return $this->billing_model === TaskBillingModel::FixedPrice;
    }

    /**
     * A task is invoiced once its billing report line belongs to a finalized report.
     */
    public function isInvoiced(): bool
    {
        if ($this->relationLoaded('billingReportLine')) {
            $line = $this->getRelation('billingReportLine');

            if (! $line instanceof BillingReportLine) {
                return false;
            }

            return $line->relationLoaded('billingReport')
                ? $line->billingReport?->isFinalized() === true
                : $line->billingReport()->finalized()->exists();
        }

        return $this->billingReportLine()
            ->whereHas('billingReport', fn (Builder $q): Builder => $q->where('status', BillingReportStatus::Finalized))
            ->exists();
    }

    public function isReadyToInvoice(): bool
    {
        if (! $this->is_billable) {
            return false;
        }

        if ($this->billingReportLine()->exists()) {
            return false;
        }

        return $this->status->isDone() && $this->calculatedAmount() !== null;
    }
}
