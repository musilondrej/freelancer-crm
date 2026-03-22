<?php

namespace App\Models;

use Database\Factories\BillingReportLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BillingReportLine extends Model
{
    /** @use HasFactory<BillingReportLineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'billing_report_id',
        'task_id',
        'description',
        'quantity',
        'unit_price',
        'total_amount',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Always recompute total_amount from quantity × unit_price before saving.
        static::saving(function (self $line): void {
            $line->total_amount = round((float) $line->quantity * (float) $line->unit_price, 2);
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<BillingReport, $this>
     */
    public function billingReport(): BelongsTo
    {
        return $this->belongsTo(BillingReport::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsToMany<TimeEntry, $this>
     */
    public function timeEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            TimeEntry::class,
            'billing_report_line_time_entries',
            'billing_report_line_id',
            'time_entry_id',
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isFromTask(): bool
    {
        return $this->task_id !== null;
    }

    public function isCustom(): bool
    {
        return $this->task_id === null;
    }
}
