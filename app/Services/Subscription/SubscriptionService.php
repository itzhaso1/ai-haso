<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionService
{
    public function current(Workspace $workspace): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latest('id')
            ->first();
    }

    public function activatePlan(Workspace $workspace, Plan $plan): Subscription
    {
        Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        return Subscription::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function availablePlans(?string $workspaceType = null): Collection
    {
        return Plan::query()
            ->when($workspaceType, fn ($query) => $query->where('workspace_type', $workspaceType))
            ->where('is_active', true)
            ->orderBy('price')
            ->get();
    }
}
