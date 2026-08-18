<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'plan_id',
    'status',
    'starts_at',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'ends_at',
    'cancelled_at',
    'provider',
    'provider_customer_id',
    'provider_subscription_id',
    'metadata',
])]
class Subscription extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
