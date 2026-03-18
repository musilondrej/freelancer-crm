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
            $hasInvoiceReference = $timeEntry->invoice_reference !== null
                && trim((string) $timeEntry->invoice_reference) !== '';
            $hasInvoicedAt = $timeEntry->invoiced_at !== null;

            if ((bool) $timeEntry->is_invoiced || $hasInvoiceReference || $hasInvoicedAt) {
                $timeEntry->is_invoiced = true;

                if ($timeEntry->invoiced_at === null) {
                    $timeEntry->invoiced_at = now();
                }

                return;
            }

            $timeEntry->is_invoiced = false;
            $timeEntry->invoice_reference = null;
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
        $query->whereNull('invoice_reference');
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
        if ($this->isRunning() || $this->isInvoiced() || $this->resolvedMinutes() <= 0) {
            return false;
        }

        return $this->effectiveBillable((bool) $this->task?->is_billable);
    }

    public function markAsInvoiced(?string $invoiceReference = null, CarbonInterface|string|null $invoicedAt = null): void
    {
        $normalizedInvoiceReference = is_string($invoiceReference)
            ? trim($invoiceReference)
            : null;

        if ($normalizedInvoiceReference === '') {
            $normalizedInvoiceReference = null;
        }

        $resolvedInvoicedAt = match (true) {
            $invoicedAt instanceof CarbonInterface => $invoicedAt,
            is_string($invoicedAt) && trim($invoicedAt) !== '' => CarbonImmutable::parse($invoicedAt),
            default => $this->invoiced_at ?? now(),
        };

        $this->fill([
            'is_invoiced' => true,
            'invoice_reference' => $normalizedInvoiceReference,
            'invoiced_at' => $resolvedInvoicedAt,
        ])->save();
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
