<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Concerns\EnforcesOwner;
use App\Support\Invoicing\InvoiceIssuer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
        'activity_id',
        'title',
        'description',
        'billing_model',
        'status',
        'priority',
        'is_billable',
        'track_time',
        'is_invoiced',
        'invoice_reference',
        'invoiced_at',
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
            'is_invoiced' => 'boolean',
            'invoiced_at' => 'datetime',
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

            $resolvedBillingModel = $task->resolvedBillingModelValue();

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

            $hasInvoiceReference = $task->invoice_reference !== null
                && trim((string) $task->invoice_reference) !== '';
            $hasInvoicedAt = $task->invoiced_at !== null;

            if ((bool) $task->is_invoiced || $hasInvoiceReference || $hasInvoicedAt) {
                $task->is_invoiced = true;

                if ($task->invoiced_at === null) {
                    $task->invoiced_at = now();
                }

                return;
            }

            $task->is_invoiced = false;
            $task->invoice_reference = null;
            $task->invoiced_at = null;
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
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
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

    /**
     * @return MorphMany<InvoiceItem, $this>
     */
    public function invoiceItems(): MorphMany
    {
        return $this->morphMany(InvoiceItem::class, 'invoiceable');
    }

    /**
     * @return MorphOne<InvoiceItem, $this>
     */
    public function currentInvoiceItem(): MorphOne
    {
        return $this->morphOne(InvoiceItem::class, 'invoiceable')->latestOfMany();
    }

    public function effectiveCurrency(): ?string
    {
        /** @var Project|null $project */
        $project = $this->project;

        return $this->currency ?? $project?->effectiveCurrency();
    }

    public function effectiveHourlyRate(): ?float
    {
        $billingModel = $this->resolvedBillingModelValue();

        if ($billingModel !== TaskBillingModel::Hourly->value) {
            return null;
        }

        /** @var Project|null $project */
        $project = $this->project;
        /** @var Activity|null $activity */
        $activity = $this->activity;
        $effectiveCurrency = $this->effectiveCurrency();

        if ($this->hourly_rate_override !== null) {
            return (float) $this->hourly_rate_override;
        }

        if ($activity?->default_hourly_rate !== null) {
            return (float) $activity->default_hourly_rate;
        }

        $effectiveHourlyRate = $project?->effectiveHourlyRate($effectiveCurrency);

        if ($effectiveHourlyRate !== null) {
            return (float) $effectiveHourlyRate;
        }

        return $this->owner?->defaultHourlyRateForCurrency($effectiveCurrency);
    }

    public function calculatedAmount(): ?float
    {
        $billingModel = $this->resolvedBillingModelValue();

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
    protected function readyToInvoice(Builder $query, ?int $ownerId = null): Builder
    {
        $query->when($ownerId !== null, fn (Builder $builder): Builder => $builder->where('owner_id', $ownerId));
        $query->where('is_billable', true);
        $query->whereIn('status', TaskStatus::doneValues());
        $query->whereDoesntHave('invoiceItems');
        $query->where('is_invoiced', false);
        $query->whereNull('invoice_reference');
        $query->whereNull('invoiced_at');

        return $query;
    }

    public function isInvoiced(): bool
    {
        if ($this->currentInvoiceItem()->exists()) {
            return true;
        }

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

        return $this->status->isDone() && $this->calculatedAmount() !== null;
    }

    public function markAsInvoiced(?string $invoiceReference = null, CarbonInterface|string|null $invoicedAt = null): void
    {
        if ($this->isInvoiced()) {
            return;
        }

        resolve(InvoiceIssuer::class)->issue([$this], $invoiceReference, $invoicedAt);

        $this->unsetRelation('currentInvoiceItem');
        $this->refresh();
    }

    public function resolvedInvoiceReference(): ?string
    {
        $invoice = $this->resolvedInvoice();

        if ($invoice?->reference !== null && trim((string) $invoice->reference) !== '') {
            return trim((string) $invoice->reference);
        }

        if ($this->invoice_reference !== null && trim((string) $this->invoice_reference) !== '') {
            return trim((string) $this->invoice_reference);
        }

        return null;
    }

    public function resolvedInvoicedAt(): ?CarbonInterface
    {
        $invoiceIssuedAt = $this->resolvedInvoice()?->getAttribute('issued_at');

        if ($invoiceIssuedAt instanceof CarbonInterface) {
            return $invoiceIssuedAt;
        }

        if (is_string($invoiceIssuedAt) && trim($invoiceIssuedAt) !== '') {
            return CarbonImmutable::parse($invoiceIssuedAt);
        }

        $rawInvoicedAt = $this->getAttribute('invoiced_at');

        if ($rawInvoicedAt instanceof CarbonInterface) {
            return $rawInvoicedAt;
        }

        if (is_string($rawInvoicedAt) && trim($rawInvoicedAt) !== '') {
            return CarbonImmutable::parse($rawInvoicedAt);
        }

        return null;
    }

    private function resolvedBillingModelValue(): string
    {
        $rawBillingModel = $this->getAttribute('billing_model');

        if ($rawBillingModel instanceof TaskBillingModel) {
            return $rawBillingModel->value;
        }

        return (string) $rawBillingModel;
    }

    private function resolvedInvoice(): ?Invoice
    {
        $invoiceItem = $this->relationLoaded('currentInvoiceItem')
            ? $this->getRelation('currentInvoiceItem')
            : $this->currentInvoiceItem()->with('invoice')->first();

        if (! $invoiceItem instanceof InvoiceItem) {
            return null;
        }

        return $invoiceItem->relationLoaded('invoice')
            ? $invoiceItem->getRelation('invoice')
            : $invoiceItem->invoice;
    }
}
