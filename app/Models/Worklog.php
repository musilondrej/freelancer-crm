<?php

namespace App\Models;

use App\Enums\ProjectActivityStatus;
use App\Enums\ProjectActivityType;
use App\Models\Concerns\EnforcesOwner;
use Database\Factories\ProjectActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProjectActivityStatus $status
 * @property ProjectActivityType $type
 */
class Worklog extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<ProjectActivityFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'worklogs';

    protected static function newFactory(): ProjectActivityFactory
    {
        return ProjectActivityFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'project_id',
        'activity_id',
        'backlog_item_id',
        'title',
        'description',
        'type',
        'status',
        'is_running',
        'is_billable',
        'is_invoiced',
        'invoice_reference',
        'invoiced_at',
        'currency',
        'quantity',
        'unit_rate',
        'flat_amount',
        'tracked_minutes',
        'due_date',
        'started_at',
        'finished_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectActivityType::class,
            'status' => ProjectActivityStatus::class,
            'is_running' => 'boolean',
            'is_billable' => 'boolean',
            'is_invoiced' => 'boolean',
            'invoiced_at' => 'datetime',
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'flat_amount' => 'decimal:2',
            'tracked_minutes' => 'integer',
            'due_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $activity): void {
            $hasInvoiceReference = $activity->invoice_reference !== null
                && trim((string) $activity->invoice_reference) !== '';
            $hasInvoicedAt = $activity->invoiced_at !== null;

            if ((bool) $activity->is_invoiced || $hasInvoiceReference || $hasInvoicedAt) {
                $activity->is_invoiced = true;

                if ($activity->invoiced_at === null) {
                    $activity->invoiced_at = now();
                }

                return;
            }

            $activity->is_invoiced = false;
            $activity->invoice_reference = null;
            $activity->invoiced_at = null;
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
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<BacklogItem, $this>
     */
    public function backlogItem(): BelongsTo
    {
        return $this->belongsTo(BacklogItem::class);
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

    public function effectiveUnitRate(): ?float
    {
        $activityType = $this->resolvedTypeValue();

        if ($activityType !== ProjectActivityType::Hourly->value) {
            return $this->unit_rate !== null
                ? (float) $this->unit_rate
                : null;
        }

        /** @var Project|null $project */
        $project = $this->project;
        /** @var Activity|null $activity */
        $activity = $this->activity;

        if ($this->unit_rate !== null) {
            return (float) $this->unit_rate;
        }

        if ($activity?->default_hourly_rate !== null) {
            return (float) $activity->default_hourly_rate;
        }

        $effectiveHourlyRate = $project?->effectiveHourlyRate();

        return $effectiveHourlyRate !== null
            ? (float) $effectiveHourlyRate
            : null;
    }

    public function calculatedAmount(): ?float
    {
        $activityType = $this->resolvedTypeValue();

        if (! $this->is_billable) {
            return 0.0;
        }

        if ($activityType === ProjectActivityType::OneTime->value) {
            if ($this->flat_amount !== null) {
                return (float) $this->flat_amount;
            }

            if ($this->unit_rate !== null && $this->quantity !== null) {
                return (float) $this->unit_rate * (float) $this->quantity;
            }

            return null;
        }

        $rate = $this->effectiveUnitRate();

        if ($rate === null) {
            return null;
        }

        if ($this->quantity !== null) {
            return $rate * (float) $this->quantity;
        }

        if ($this->tracked_minutes !== null) {
            return $rate * ((float) $this->tracked_minutes / 60);
        }

        return null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function readyToInvoice(Builder $query, ?int $ownerId = null): Builder
    {
        return $query
            ->when($ownerId !== null, fn (Builder $builder): Builder => $builder->where('owner_id', $ownerId))
            ->where('is_billable', true)
            ->whereIn('status', ProjectActivityStatus::doneValues())
            ->where('is_invoiced', false)
            ->whereNull('invoice_reference')
            ->whereNull('invoiced_at');
    }

    public function isInvoiced(): bool
    {
        if ((bool) $this->is_invoiced) {
            return true;
        }

        if ($this->invoice_reference !== null && trim((string) $this->invoice_reference) !== '') {
            return true;
        }

        return $this->invoiced_at !== null;
    }

    public function isReadyToInvoice(): bool
    {
        if (! $this->is_billable || $this->isInvoiced()) {
            return false;
        }

        return $this->status->isDone();
    }

    private function resolvedTypeValue(): string
    {
        $rawType = $this->getAttribute('type');

        if ($rawType instanceof ProjectActivityType) {
            return $rawType->value;
        }

        return (string) $rawType;
    }
}
