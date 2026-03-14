<?php

namespace App\Models;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Models\Concerns\EnforcesOwner;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringServiceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringService extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<RecurringServiceFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'customer_id',
        'project_id',
        'name',
        'service_type_id',
        'billing_model',
        'currency',
        'fixed_amount',
        'hourly_rate',
        'included_quantity',
        'cadence_unit',
        'cadence_interval',
        'starts_on',
        'next_due_on',
        'last_reminded_for_due_on',
        'last_reminded_at',
        'last_invoiced_on',
        'ends_on',
        'auto_renew',
        'status',
        'remind_days_before',
        'notes',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type_id' => 'integer',
            'billing_model' => RecurringServiceBillingModel::class,
            'cadence_unit' => RecurringServiceCadenceUnit::class,
            'status' => RecurringServiceStatus::class,
            'fixed_amount' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'included_quantity' => 'decimal:2',
            'cadence_interval' => 'integer',
            'starts_on' => 'date',
            'next_due_on' => 'date',
            'last_reminded_for_due_on' => 'date',
            'last_reminded_at' => 'datetime',
            'last_invoiced_on' => 'date',
            'ends_on' => 'date',
            'auto_renew' => 'boolean',
            'remind_days_before' => 'array',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            $service->cadence_interval = max(1, (int) $service->cadence_interval);

            if (! $service->shouldRecalculateNextDueOn()) {
                return;
            }

            $resolvedNextDueOn = $service->resolveNextDueOnFromCadence();

            if ($resolvedNextDueOn instanceof CarbonImmutable) {
                $service->next_due_on = $resolvedNextDueOn->toDateString();
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<RecurringServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(RecurringServiceType::class, 'service_type_id');
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function quickNotes(): MorphMany
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

    public function effectiveCurrency(): ?string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        /** @var Project|null $project */
        $project = $this->project;

        if ($project !== null) {
            return $project->effectiveCurrency();
        }

        /** @var Customer|null $customer */
        $customer = $this->customer;

        if ($customer !== null) {
            return $customer->effectiveCurrency();
        }

        /** @var User|null $owner */
        $owner = $this->owner;

        return $owner?->default_currency;
    }

    private function shouldRecalculateNextDueOn(): bool
    {
        if ($this->next_due_on === null) {
            return true;
        }

        if ($this->isDirty('next_due_on')) {
            return false;
        }

        if ($this->isDirty('cadence_unit')) {
            return true;
        }

        if ($this->isDirty('cadence_interval')) {
            return true;
        }

        if ($this->isDirty('starts_on')) {
            return true;
        }

        return $this->isDirty('last_invoiced_on');
    }

    private function resolveNextDueOnFromCadence(): ?CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $startsOn = $this->toImmutableDate($this->starts_on);
        $lastInvoicedOn = $this->toImmutableDate($this->last_invoiced_on);

        if (! $startsOn instanceof CarbonImmutable) {
            return null;
        }

        $candidate = $lastInvoicedOn instanceof CarbonImmutable
            ? $this->advanceByCadence($lastInvoicedOn)
            : $startsOn;

        while ($candidate->lessThan($today)) {
            $candidate = $this->advanceByCadence($candidate);
        }

        return $candidate;
    }

    private function advanceByCadence(CarbonImmutable $date): CarbonImmutable
    {
        $interval = max(1, (int) $this->cadence_interval);
        $cadenceUnit = $this->resolvedCadenceUnitValue();

        return match ($cadenceUnit) {
            RecurringServiceCadenceUnit::Week->value => $date->addWeeks($interval),
            RecurringServiceCadenceUnit::Month->value => $date->addMonthsNoOverflow($interval),
            RecurringServiceCadenceUnit::Quarter->value => $date->addMonthsNoOverflow(3 * $interval),
            RecurringServiceCadenceUnit::Year->value => $date->addYearsNoOverflow($interval),
            default => $date->addMonthsNoOverflow($interval),
        };
    }

    private function resolvedCadenceUnitValue(): string
    {
        $cadenceUnit = $this->getAttribute('cadence_unit');

        if ($cadenceUnit instanceof RecurringServiceCadenceUnit) {
            return $cadenceUnit->value;
        }

        return (string) $cadenceUnit;
    }

    private function toImmutableDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
