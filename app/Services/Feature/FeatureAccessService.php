<?php

namespace App\Services\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;

class FeatureAccessService
{
    /**
     * Legacy compatibility: older plans only had "appointments".
     * Treat these newer website capabilities as part of appointments unless explicitly overridden.
     *
     * @var array<int, string>
     */
    private const APPOINTMENTS_COMPATIBILITY_FEATURES = [
        'website_builder',
        'custom_domains',
        'public_booking',
    ];

    public function hasFeature(User $user, Workspace $workspace, string $feature): bool
    {
        $membershipExists = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $membershipExists) {
            return false;
        }

        $override = WorkspaceFeatureFlag::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('feature_key', $feature)
            ->first();

        if ($override) {
            return $override->enabled;
        }

        $typeDefaults = config("workspace.features_by_type.{$workspace->type}", []);
        if (! in_array($feature, $typeDefaults, true)) {
            return false;
        }

        $subscription = $workspace->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (! $subscription) {
            return false;
        }

        $planFeatures = is_array($subscription->plan?->features) ? $subscription->plan->features : [];

        if (in_array($feature, self::APPOINTMENTS_COMPATIBILITY_FEATURES, true)
            && ! in_array($feature, $planFeatures, true)
            && in_array('appointments', $planFeatures, true)
        ) {
            return true;
        }

        return in_array($feature, $planFeatures, true);
    }
}
