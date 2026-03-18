<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContact extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'customer_id',
        'full_name',
        'job_title',
        'email',
        'phone',
        'is_primary',
        'is_billing_contact',
        'last_contacted_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_billing_contact' => 'boolean',
            'last_contacted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

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

    public function primaryProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'primary_contact_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
