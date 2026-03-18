<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'invoice_id',
        'description',
        'quantity',
        'unit_rate',
        'amount',
        'currency',
        'line_order',
        'meta',
    ];

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'amount' => 'decimal:2',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }
}
