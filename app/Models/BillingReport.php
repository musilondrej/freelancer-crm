<?php

namespace App\Models;

use App\Enums\BillingReportStatus;
use App\Models\Concerns\EnforcesOwner;
use Carbon\CarbonImmutable;
use Database\Factories\BillingReportFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property BillingReportStatus $status
 * @property CarbonImmutable|null $period_from
 * @property CarbonImmutable|null $period_to
 * @property float|null $lines_sum_total_amount
 */
class BillingReport extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<BillingReportFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'customer_id',
        'title',
        'reference',
        'currency',
        'status',
        'notes',
        'finalized_at',
    ];

    protected static function newFactory(): BillingReportFactory
    {
        return BillingReportFactory::new();
    }

    protected static function booted(): void
    {
        // Automatically inherit the customer's effective currency at creation time
        // so the report carries a currency snapshot without manual input.
        static::creating(function (self $report): void {
            if (filled($report->currency)) {
                return;
            }

            $report->currency = $report->customer?->effectiveCurrency()
                ?? $report->owner->default_currency
                ?? 'EUR';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillingReportStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * Earliest started_at across all time entries attached to this report's lines.
     */
    protected function periodFrom(): Attribute
    {
        return Attribute::get(function (): ?CarbonImmutable {
            $min = $this->lines()
                ->join('billing_report_line_time_entries as pivot', 'billing_report_lines.id', '=', 'pivot.billing_report_line_id')
                ->join('time_entries', 'pivot.time_entry_id', '=', 'time_entries.id')
                ->whereNull('time_entries.deleted_at')
                ->min('time_entries.started_at');

            return $min ? CarbonImmutable::parse($min) : null;
        });
    }

    /**
     * Latest started_at across all time entries attached to this report's lines.
     */
    protected function periodTo(): Attribute
    {
        return Attribute::get(function (): ?CarbonImmutable {
            $max = $this->lines()
                ->join('billing_report_line_time_entries as pivot', 'billing_report_lines.id', '=', 'pivot.billing_report_line_id')
                ->join('time_entries', 'pivot.time_entry_id', '=', 'time_entries.id')
                ->whereNull('time_entries.deleted_at')
                ->max('time_entries.started_at');

            return $max ? CarbonImmutable::parse($max) : null;
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return HasMany<BillingReportLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BillingReportLine::class)->orderBy('sort_order');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function draft(Builder $query): Builder
    {
        return $query->where('status', BillingReportStatus::Draft);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function finalized(Builder $query): Builder
    {
        return $query->where('status', BillingReportStatus::Finalized);
    }

    // ─── Business logic ───────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status->isDraft();
    }

    public function isFinalized(): bool
    {
        return $this->status->isFinalized();
    }

    public function totalAmount(): float
    {
        return (float) $this->lines()->sum('total_amount');
    }

    /**
     * Add an hourly task to the report.
     * Attaches all unbilled time entries for that task.
     */
    public function addHourlyTask(Task $task): BillingReportLine
    {
        $timeEntries = TimeEntry::query()
            ->where('task_id', $task->id)
            ->whereDoesntHave('billingReportLines')
            ->get();

        $totalMinutes = $timeEntries->sum(fn (TimeEntry $e): int => $e->resolvedMinutes());
        $quantity = round($totalMinutes / 60, 2);
        $unitPrice = (float) ($task->effectiveHourlyRate() ?? 0);

        /** @var BillingReportLine $line */
        $line = $this->lines()->create([
            'task_id' => $task->id,
            'description' => $task->title,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 2),
            'sort_order' => $this->lines()->max('sort_order') + 1,
        ]);

        $line->timeEntries()->attach($timeEntries->pluck('id'));

        return $line;
    }

    /**
     * Add a fixed-price task to the report as a single line.
     */
    public function addFixedPriceTask(Task $task): BillingReportLine
    {
        $unitPrice = (float) ($task->fixed_price ?? 0);

        /** @var BillingReportLine $line */
        $line = $this->lines()->create([
            'task_id' => $task->id,
            'description' => $task->title,
            'quantity' => 1.00,
            'unit_price' => $unitPrice,
            'total_amount' => $unitPrice,
            'sort_order' => $this->lines()->max('sort_order') + 1,
        ]);

        return $line;
    }

    /**
     * Add a hand-picked set of time entries to the report.
     *
     * Entries are grouped by task; each unique task gets one line.
     * Untasked entries are aggregated into a single "Unbilled time" line.
     * Entries that are already invoiced or already attached to any billing
     * report line are silently skipped to avoid double-billing.
     *
     * @param  EloquentCollection<int, TimeEntry>  $timeEntries
     * @return int Number of entries that were actually attached
     */
    public function addSpecificEntries(EloquentCollection $timeEntries): int
    {
        $timeEntries->load('billingReportLines');

        $eligible = $timeEntries->filter(
            fn (TimeEntry $e): bool => ! $e->isInvoiced() && $e->billingReportLines->isEmpty()
        );

        if ($eligible->isEmpty()) {
            return 0;
        }

        $eligible->loadMissing('task');

        $attached = 0;

        $eligible
            ->groupBy(fn (TimeEntry $e): string => (string) ($e->task_id ?? ''))
            ->each(function (EloquentCollection $entries) use (&$attached): void {
                $task = $entries->first()?->task;
                $totalMinutes = $entries->sum(fn (TimeEntry $e): int => $e->resolvedMinutes());
                $quantity = round($totalMinutes / 60, 2);

                if ($task instanceof Task) {
                    $unitPrice = (float) ($task->effectiveHourlyRate() ?? 0);
                    $description = $task->title;
                } else {
                    $unitPrice = 0.0;
                    $description = __('Unbilled time');
                }

                /** @var BillingReportLine $line */
                $line = $this->lines()->create([
                    'task_id' => $task instanceof Task ? $task->id : null,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_amount' => round($quantity * $unitPrice, 2),
                    'sort_order' => $this->lines()->max('sort_order') + 1,
                ]);

                $line->timeEntries()->attach($entries->pluck('id'));
                $attached += $entries->count();
            });

        return $attached;
    }

    /**
     * Add a fully custom line (no task reference) — expenses, travel, etc.
     */
    public function addCustomLine(string $description, float $quantity, float $unitPrice): BillingReportLine
    {
        /** @var BillingReportLine $line */
        $line = $this->lines()->create([
            'task_id' => null,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 2),
            'sort_order' => $this->lines()->max('sort_order') + 1,
        ]);

        return $line;
    }

    /**
     * Finalize the report. Invoiced status of linked tasks and time entries
     * is derived from this report's status — no separate flags needed.
     */
    public function finalize(?string $reference = null): void
    {
        if ($this->isFinalized()) {
            return;
        }

        if ($reference !== null) {
            $this->reference = $reference;
        }

        $this->update([
            'reference' => $this->reference ?: null,
            'status' => BillingReportStatus::Finalized,
            'finalized_at' => CarbonImmutable::now(),
        ]);
    }
}
