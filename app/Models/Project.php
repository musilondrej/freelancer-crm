<?php

namespace App\Models;

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Models\Concerns\EnforcesOwner;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'client_id',
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
            'priority' => 'integer',
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
        return $this->belongsTo(Customer::class, 'client_id');
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
     * @return HasMany<Worklog, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function trackingActivities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<BacklogItem, $this>
     */
    public function backlogItems(): HasMany
    {
        return $this->hasMany(BacklogItem::class);
    }

    /**
     * @return HasMany<RecurringService, $this>
     */
    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class);
    }

    protected function hourlyRateWithCurrency(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $hourlyRate = $attributes['hourly_rate'] ?? null;

                if ($hourlyRate === null) {
                    return null;
                }

                $currency = $attributes['currency'] ?? null;
                $formattedHourlyRate = number_format((float) $hourlyRate, 2, '.', '');

                return $currency !== null
                    ? sprintf('%s %s', $formattedHourlyRate, $currency)
                    : $formattedHourlyRate;
            },
        );
    }

    public function effectiveHourlyRate(): ?float
    {
        /** @var Customer|null $customer */
        $customer = $this->customer;

        return $this->hourly_rate ?? $customer?->effectiveHourlyRate();
    }

    public function effectiveCurrency(): ?string
    {
        /** @var Customer|null $customer */
        $customer = $this->customer;

        return $this->currency ?? $customer?->effectiveCurrency();
    }

    public function resolvedStatusCode(): string
    {
        return (string) $this->getRawOriginal('status');
    }

    public function resolvedStatusLabel(): string
    {
        return ProjectStatusOption::labelFor($this->owner_id, $this->resolvedStatusCode());
    }

    public function resolvedStatusColor(): string
    {
        return ProjectStatusOption::colorFor($this->owner_id, $this->resolvedStatusCode());
    }
}
