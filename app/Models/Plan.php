<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'description',
    'tier',
    'workspace_type',
    'billing_period',
    'trial_days',
    'currency',
    'price',
    'is_active',
    'is_public',
    'sort_order',
    'features',
    'permissions',
    'limits',
    'overage_rules',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
            'features' => 'array',
            'permissions' => 'array',
            'limits' => 'array',
            'overage_rules' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
