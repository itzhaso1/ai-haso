<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'workspace_type',
    'billing_period',
    'currency',
    'price',
    'is_active',
    'features',
    'permissions',
    'limits',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'features' => 'array',
            'permissions' => 'array',
            'limits' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
