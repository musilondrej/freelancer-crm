<?php

namespace App\Models;

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
        'company_id',
        'vat_id',
        'dic',
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
        return $this->hasMany(ClientContact::class, 'client_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
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

    protected function hourlyRateWithCurrency(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $hourlyRate = $attributes['hourly_rate'] ?? null;

                if ($hourlyRate === null) {
                    return null;
                }

                $currency = $attributes['billing_currency'] ?? null;
                $formattedHourlyRate = number_format((float) $hourlyRate, 2, '.', '');

                return $currency !== null
                    ? sprintf('%s %s', $formattedHourlyRate, $currency)
                    : $formattedHourlyRate;
            },
        );
    }

    public function effectiveHourlyRate(): ?float
    {
        /** @var User|null $owner */
        $owner = $this->owner;

        return $this->hourly_rate ?? $owner?->default_hourly_rate;
    }

    public function effectiveCurrency(): ?string
    {
        /** @var User|null $owner */
        $owner = $this->owner;

        return $this->billing_currency ?? $owner?->default_currency;
    }
}
