<?php

namespace App\Services\Feature;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Models\WorkspaceUsageMeter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class FeatureAccessService
{
    /**
     * Legacy compatibility: older plans only had "appointments".
     *
     * @var array<int, string>
     */
    private const APPOINTMENTS_COMPATIBILITY_FEATURES = [
        'website_builder',
        'custom_domains',
        'public_booking',
    ];

    /**
     * Map commercial tier aliases onto seeded plan codes.
     *
     * @var array<string, array<int, string>>
     */
    public const TIER_PLAN_CODES = [
        'starter' => ['starter', 'company_starter', 'store_starter', 'company_basic', 'store_basic', 'individual_free'],
        'pro' => ['pro', 'company_pro', 'store_pro', 'individual_pro'],
        'business' => ['business', 'company_business', 'store_business'],
        'enterprise' => ['enterprise', 'company_enterprise', 'store_enterprise'],
    ];

    public function hasFeature(User $user, Workspace $workspace, string $feature): bool
    {
        if (! $this->isActiveMember($user, $workspace)) {
            return false;
        }

        return $this->workspaceHasFeature($workspace, $feature);
    }

    public function workspaceHasFeature(Workspace $workspace, string $feature): bool
    {
        $override = WorkspaceFeatureFlag::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('feature_key', $feature)
            ->first();

        if ($override) {
            return (bool) $override->enabled;
        }

        $typeDefaults = config("workspace.features_by_type.{$workspace->type}", []);
        if (! in_array($feature, $typeDefaults, true)) {
            return false;
        }

        $plan = $this->plan($workspace);
        if (! $plan) {
            return false;
        }

        $planFeatures = is_array($plan->features) ? $plan->features : [];

        if (in_array($feature, self::APPOINTMENTS_COMPATIBILITY_FEATURES, true)
            && ! in_array($feature, $planFeatures, true)
            && in_array('appointments', $planFeatures, true)
        ) {
            // Curated modern plans (e.g. starter) may include website_builder but omit
            // custom_domains on purpose — do not re-grant via legacy appointments compat.
            if ($feature === 'custom_domains') {
                $explicitTier = $plan->tier;
                $isStarterCode = in_array((string) $plan->code, self::TIER_PLAN_CODES['starter'], true);
                $hasWebsiteBuilder = in_array('website_builder', $planFeatures, true);

                if ($hasWebsiteBuilder || $explicitTier === 'starter' || $isStarterCode) {
                    return false;
                }
            }

            return true;
        }

        return in_array($feature, $planFeatures, true);
    }

    public function canUse(User $user, Workspace $workspace, string $feature, ?string $meter = null, int|float $amount = 1): bool
    {
        if (! $this->hasFeature($user, $workspace, $feature)) {
            return false;
        }

        if ($meter === null) {
            return true;
        }

        return ! $this->wouldExceedLimit($workspace, $meter, $amount);
    }

    public function assertFeature(User $user, Workspace $workspace, string $feature): void
    {
        if (! $this->hasFeature($user, $workspace, $feature)) {
            throw new FeatureNotAvailableException(
                feature: $feature,
                requiredPlan: $this->suggestedPlanForFeature($feature),
                message: __('هذه الميزة (:feature) غير متاحة في باقتك الحالية. قم بالترقية للمتابعة.', ['feature' => $feature]),
            );
        }
    }

    public function assertCanUse(User $user, Workspace $workspace, string $feature, ?string $meter = null, int|float $amount = 1): void
    {
        $this->assertFeature($user, $workspace, $feature);

        if ($meter !== null) {
            $this->assertWithinLimit($workspace, $meter, $amount);
        }
    }

    /**
     * @return array{limit:int|float|null,used:int|float,remaining:int|float|null,reset_at:?string,overage:string}
     */
    public function checkLimit(Workspace $workspace, string $meter): array
    {
        $limit = $this->limitValue($workspace, $meter);
        $used = $this->currentUsage($workspace, $meter);
        $resetAt = $this->periodEnd($workspace);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'reset_at' => $resetAt?->toIso8601String(),
            'overage' => $this->overageBehavior($workspace, $meter),
        ];
    }

    public function remaining(Workspace $workspace, string $meter): int|float|null
    {
        return $this->checkLimit($workspace, $meter)['remaining'];
    }

    public function consumeUsage(Workspace $workspace, string $meter, int|float $amount = 1, bool $enforce = true): WorkspaceUsageMeter
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Usage amount cannot be negative.');
        }

        return DB::transaction(function () use ($workspace, $meter, $amount, $enforce): WorkspaceUsageMeter {
            if ($enforce) {
                $this->assertWithinLimit($workspace, $meter, $amount);
            }

            $periodStart = $this->periodStart($workspace) ?? now()->startOfMonth();
            $periodEnd = $this->periodEnd($workspace) ?? now()->endOfMonth();
            $periodStartDate = $periodStart->toDateString();
            $periodEndDate = $periodEnd->toDateString();

            /** @var WorkspaceUsageMeter|null $row */
            $row = WorkspaceUsageMeter::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('meter_key', $meter)
                ->whereDate('period_start', $periodStartDate)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                try {
                    $row = WorkspaceUsageMeter::withoutGlobalScopes()->create([
                        'workspace_id' => $workspace->id,
                        'meter_key' => $meter,
                        'period_start' => $periodStartDate,
                        'period_end' => $periodEndDate,
                        'used' => 0,
                        'metadata' => [],
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    $row = WorkspaceUsageMeter::withoutGlobalScopes()
                        ->where('workspace_id', $workspace->id)
                        ->where('meter_key', $meter)
                        ->whereDate('period_start', $periodStartDate)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $row = WorkspaceUsageMeter::withoutGlobalScopes()
                    ->whereKey($row->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $row->forceFill([
                'used' => (float) $row->used + (float) $amount,
            ])->save();

            return WorkspaceUsageMeter::withoutGlobalScopes()->findOrFail($row->id);
        });
    }

    public function plan(Workspace $workspace): ?Plan
    {
        $subscription = $this->subscription($workspace);

        return $subscription?->plan;
    }

    public function subscription(Workspace $workspace): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, bool>
     */
    public function overrides(Workspace $workspace): array
    {
        return WorkspaceFeatureFlag::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->get()
            ->mapWithKeys(fn (WorkspaceFeatureFlag $flag): array => [
                $flag->feature_key => (bool) $flag->enabled,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function entitlementsSnapshot(Workspace $workspace): array
    {
        $plan = $this->plan($workspace);
        $features = is_array($plan?->features) ? $plan->features : [];
        $limits = is_array($plan?->limits) ? $plan->limits : [];
        $meters = [];

        foreach (array_keys(config('plans.meters', [])) as $meter) {
            $meters[$meter] = $this->checkLimit($workspace, $meter);
        }

        return [
            'plan' => $plan ? [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'tier' => $plan->tier ?? $this->inferTier($plan->code),
                'billing_period' => $plan->billing_period,
                'price' => $plan->price,
                'currency' => $plan->currency,
            ] : null,
            'features' => $features,
            'limits' => $limits,
            'overrides' => $this->overrides($workspace),
            'meters' => $meters,
            'subscription_status' => $this->subscription($workspace)?->status,
        ];
    }

    public function assertWithinLimit(Workspace $workspace, string $meter, int|float $amount = 1): void
    {
        if ($this->wouldExceedLimit($workspace, $meter, $amount)) {
            $check = $this->checkLimit($workspace, $meter);
            $behavior = $check['overage'];

            if ($behavior === 'pay_as_you_go' || $behavior === 'extra_credits') {
                // Soft allow — callers may bill separately; still record intent.
                return;
            }

            throw new UsageLimitExceededException(
                meter: $meter,
                limit: (float) ($check['limit'] ?? 0),
                used: (float) $check['used'],
                overageBehavior: $behavior,
            );
        }
    }

    public function wouldExceedLimit(Workspace $workspace, string $meter, int|float $amount = 1): bool
    {
        $limit = $this->limitValue($workspace, $meter);
        if ($limit === null) {
            return false;
        }

        $used = $this->currentUsage($workspace, $meter);

        return ($used + $amount) > $limit;
    }

    public function limitValue(Workspace $workspace, string $meter): int|float|null
    {
        $plan = $this->plan($workspace);
        $limits = is_array($plan?->limits) ? $plan->limits : [];

        if (! array_key_exists($meter, $limits)) {
            // Fall back to alias keys used by older seeds.
            $aliases = config("plans.meter_aliases.{$meter}", []);
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $limits)) {
                    $value = $limits[$alias];

                    return $value === null || $value === '' ? null : (float) $value;
                }
            }

            return null;
        }

        $value = $limits[$meter];
        if ($value === null || $value === '' || $value === -1) {
            return null;
        }

        return (float) $value;
    }

    public function currentUsage(Workspace $workspace, string $meter): float
    {
        $periodStart = $this->periodStart($workspace);
        if (! $periodStart) {
            $periodStart = now()->startOfMonth();
        }

        $row = WorkspaceUsageMeter::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('meter_key', $meter)
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();

        if ($row) {
            return (float) $row->used;
        }

        // Live counters for resource-based meters that are not period-resetting inventory.
        return match ($meter) {
            'products' => (float) $workspace->products()->count(),
            'team_members', 'users' => (float) $workspace->users()->wherePivot('status', 'active')->count(),
            'domains' => (float) DB::table('website_domains')->where('workspace_id', $workspace->id)->whereNull('deleted_at')->count(),
            'websites' => (float) DB::table('websites')->where('workspace_id', $workspace->id)->whereNull('deleted_at')->count(),
            'customers' => (float) $workspace->customers()->count(),
            default => 0.0,
        };
    }

    public function overageBehavior(Workspace $workspace, string $meter): string
    {
        $plan = $this->plan($workspace);
        $rules = is_array($plan?->overage_rules) ? $plan->overage_rules : [];

        return (string) ($rules[$meter] ?? config("plans.meters.{$meter}.overage", 'hard_block'));
    }

    public function periodStart(Workspace $workspace): ?CarbonInterface
    {
        $subscription = $this->subscription($workspace);

        return $subscription?->current_period_start?->copy()->startOfDay()
            ?? now()->startOfMonth();
    }

    public function periodEnd(Workspace $workspace): ?CarbonInterface
    {
        $subscription = $this->subscription($workspace);

        return $subscription?->current_period_end
            ?? now()->endOfMonth();
    }

    public function suggestedPlanForFeature(string $feature): ?string
    {
        $matrix = config('plans.feature_matrix', []);
        foreach (['starter', 'pro', 'business', 'enterprise'] as $tier) {
            $features = $matrix[$tier]['features'] ?? [];
            if (in_array($feature, $features, true)) {
                return strtoupper($tier);
            }
        }

        return 'PRO';
    }

    private function isActiveMember(User $user, Workspace $workspace): bool
    {
        return $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    private function inferTier(string $code): string
    {
        foreach (self::TIER_PLAN_CODES as $tier => $codes) {
            if (in_array($code, $codes, true) || str_contains($code, $tier)) {
                return $tier;
            }
        }

        return 'starter';
    }
}
