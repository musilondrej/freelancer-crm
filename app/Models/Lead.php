<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Models\Concerns\EnforcesOwner;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'lead_source_id',
        'customer_id',
        'full_name',
        'company_name',
        'email',
        'phone',
        'website',
        'status',
        'priority',
        'currency',
        'estimated_value',
        'expected_close_date',
        'contacted_at',
        'last_activity_at',
        'summary',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'priority' => Priority::class,
            'estimated_value' => 'decimal:2',
            'expected_close_date' => 'date',
            'contacted_at' => 'datetime',
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
     * @return BelongsTo<LeadSource, $this>
     */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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

    protected function estimatedValueWithCurrency(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?string {
                $estimatedValue = $attributes['estimated_value'] ?? null;

                if ($estimatedValue === null) {
                    return null;
                }

                $currency = Currency::tryFrom(strtoupper((string) ($attributes['currency'] ?? '')));

                return $currency !== null
                    ? $currency->format((float) $estimatedValue)
                    : Currency::userDefault()->format((float) $estimatedValue);
            },
        );
    }
}
