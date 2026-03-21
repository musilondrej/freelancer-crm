<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use App\Models\Concerns\FormatsHourlyRate;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Lead|null $lead
 */
class Customer extends Model
{
    use EnforcesOwner;
    use FormatsHourlyRate;

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
        'is_active',
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
            'is_active' => 'boolean',
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
     * @return HasOne<Lead, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'customer_id');
    }

    /**
     * @return HasMany<RecurringService, $this>
     */
    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class, 'customer_id');
    }

    protected function hourlyCurrencyColumn(): string
    {
        return 'billing_currency';
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
