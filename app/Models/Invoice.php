<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'customer_id',
        'project_id',
        'reference',
        'issued_at',
        'currency',
        'notes',
        'meta',
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function totalAmount(): float
    {
        if ($this->relationLoaded('items')) {
            return (float) $this->items->sum(fn (InvoiceItem $item): float => (float) ($item->amount ?? 0));
        }

        return (float) $this->items()->sum('amount');
    }
}
