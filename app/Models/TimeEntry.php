<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use App\Support\Invoicing\InvoiceIssuer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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
        $query->whereDoesntHave('invoiceItems');
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
                        ->whereHas('task', fn (Builder $taskQuery): Builder => $taskQuery->where('is_billable', true));
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
            return $this->owner?->default_hourly_rate !== null
                ? (float) $this->owner->default_hourly_rate
                : null;
        }

        if ($resolvedTask->hourly_rate_override !== null) {
            return (float) $resolvedTask->hourly_rate_override;
        }

        $customerRate = $resolvedTask->project?->customer?->effectiveHourlyRate();

        if ($customerRate !== null) {
            return (float) $customerRate;
        }

        return $resolvedTask->owner?->default_hourly_rate !== null
            ? (float) $resolvedTask->owner->default_hourly_rate
            : null;
    }

    public function calculatedAmount(?Task $task = null): ?float
    {
        $resolvedTask = $task ?? $this->task;

        if (! $resolvedTask instanceof Task) {
            return null;
        }

        if (! $this->effectiveBillable((bool) $resolvedTask->is_billable)) {
            return 0.0;
        }

        $rate = $this->effectiveHourlyRate($resolvedTask);

        if ($rate === null) {
            return null;
        }

        return $rate * ((float) $this->resolvedMinutes() / 60);
    }

    public function effectiveBillable(bool $taskIsBillable): bool
    {
        if ($this->is_billable_override !== null) {
            return (bool) $this->is_billable_override;
        }

        return $taskIsBillable;
    }

    public function isInvoiced(): bool
    {
        if ($this->currentInvoiceItem()->exists()) {
            return true;
        }

        if ((bool) $this->is_invoiced) {
            return true;
        }

        if (trim((string) $this->invoice_reference) !== '') {
            return true;
        }

        return $this->invoiced_at !== null;
    }

    public function isReadyToInvoice(): bool
    {
        if ($this->isRunning() || $this->isInvoiced() || $this->resolvedMinutes() <= 0) {
            return false;
        }

        return $this->effectiveBillable((bool) $this->task?->is_billable);
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
        $invoice = $this->resolvedInvoice();

        if ($invoice?->reference !== null && trim((string) $invoice->reference) !== '') {
            return trim((string) $invoice->reference);
        }

        if (trim((string) $this->invoice_reference) !== '') {
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
