<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
use App\Models\Concerns\EnforcesOwner;
use App\Models\Concerns\FormatsHourlyRate;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProjectStatus $status
 */
class Project extends Model
{
    use EnforcesOwner;
    use FormatsHourlyRate;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'customer_id',
        'primary_contact_id',
        'name',
        'status',
        'pipeline_stage',
        'pricing_model',
        'priority',
        'start_date',
        'target_end_date',
        'closed_date',
        'currency',
        'hourly_rate',
        'fixed_price',
        'estimated_hours',
        'estimated_value',
        'actual_value',
        'description',
        'last_activity_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'pipeline_stage' => ProjectPipelineStage::class,
            'pricing_model' => ProjectPricingModel::class,
            'start_date' => 'date',
            'target_end_date' => 'date',
            'closed_date' => 'date',
            'hourly_rate' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'meta' => 'array',
        ];
    }

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
     * @return BelongsTo<ClientContact, $this>
     */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'primary_contact_id');
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
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return HasMany<RecurringService, $this>
     */
    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', ProjectStatus::openValues());
    }

    public function effectiveHourlyRate(?string $currency = null): ?float
    {
        if ($this->hourly_rate !== null) {
            return (float) $this->hourly_rate;
        }

        /** @var Customer|null $customer */
        $customer = $this->customer;
        $resolvedCurrency = $currency ?? $this->effectiveCurrency();

        return $customer?->effectiveHourlyRate($resolvedCurrency);
    }

    public function effectiveCurrency(): ?string
    {
        /** @var Customer|null $customer */
        $customer = $this->customer;

        return $this->currency ?? $customer?->effectiveCurrency();
    }
}
