<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'name',
    'phone',
    'whatsapp',
    'email',
    'orders_count',
    'total_purchases',
    'last_order_at',
    'last_conversation_at',
    'notes',
    'metadata',
])]
class Customer extends WorkspaceScopedModel
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_purchases' => 'decimal:2',
            'last_order_at' => 'datetime',
            'last_conversation_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
