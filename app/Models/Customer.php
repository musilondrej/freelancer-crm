<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\CustomerStatus;
use App\Models\Concerns\EnforcesOwner;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'clients';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'legal_name',
        'registration_number',
        'vat_id',
        'email',
        'phone',
        'website',
        'timezone',
        'billing_currency',
        'hourly_rate',
        'status',
        'source',
        'last_contacted_at',
        'next_follow_up_at',
        'internal_summary',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'hourly_rate' => 'decimal:2',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
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
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'customer_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'customer_id');
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
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'customer_id');
    }

    /**
     * @return HasMany<RecurringService, $this>
     */
    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class, 'customer_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    protected function hourlyRateWithCurrency(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $hourlyRate = $attributes['hourly_rate'] ?? null;

                if ($hourlyRate === null) {
                    return null;
                }

                $currency = Currency::tryFrom((string) ($attributes['billing_currency'] ?? ''));

                return $currency !== null
                    ? $currency->formatWithCode((float) $hourlyRate)
                    : Currency::userDefault()->formatWithCode((float) $hourlyRate);
            },
        );
    }

    public function effectiveHourlyRate(?string $currency = null): ?float
    {
        if ($this->hourly_rate !== null) {
            return (float) $this->hourly_rate;
        }

        /** @var User|null $owner */
        $owner = $this->owner;
        $resolvedCurrency = $currency ?? $this->effectiveCurrency();

        return $owner?->defaultHourlyRateForCurrency($resolvedCurrency);
    }

    public function effectiveCurrency(): ?string
    {
        /** @var User|null $owner */
        $owner = $this->owner;

        return $this->billing_currency ?? $owner?->default_currency;
    }
}
