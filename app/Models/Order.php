<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'customer_id',
    'order_number',
    'status',
    'payment_status',
    'fulfillment_status',
    'shipping_status',
    'currency',
    'subtotal',
    'discount_amount',
    'shipping_amount',
    'total_amount',
    'payment_link',
    'notes',
    'placed_at',
    'cancelled_at',
])]
class Order extends WorkspaceScopedModel
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
