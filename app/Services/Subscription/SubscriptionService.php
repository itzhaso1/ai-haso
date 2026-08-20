<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutSession;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        return DB::transaction(function () use ($workspace, $plan): Subscription {
            $this->cancelRunningSubscriptions($workspace);

            return Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $this->nextPeriodEnd($plan->billing_period),
            ]);
        });
    }

    public function createCheckoutSession(Workspace $workspace, Plan $plan, string $paymentProvider = 'hyperpay'): SubscriptionCheckoutSession
    {
        return SubscriptionCheckoutSession::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'checkout_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subscription_status' => 'pending_activation',
            'payment_provider' => $paymentProvider,
            'provider_checkout_id' => 'subchk_'.Str::lower(Str::random(24)),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'expires_at' => now()->addHours(12),
            'metadata' => [
                'integration_mode' => 'frontend-placeholder',
                'note' => 'Ready for HyperPay API checkout integration',
            ],
        ]);
    }

    public function completeCheckoutAndActivate(
        Workspace $workspace,
        SubscriptionCheckoutSession $checkoutSession,
        ?string $paymentReference = null
    ): Subscription {
        return DB::transaction(function () use ($workspace, $checkoutSession, $paymentReference): Subscription {
            /** @var SubscriptionCheckoutSession|null $fresh */
            $fresh = SubscriptionCheckoutSession::withoutGlobalScopes()
                ->where('id', $checkoutSession->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                throw new \RuntimeException('Checkout session was not found.');
            }

            if (
                $fresh->payment_status === 'paid'
                && $fresh->subscription_status === 'activated'
                && $fresh->activated_subscription_id
            ) {
                return Subscription::withoutGlobalScopes()->findOrFail($fresh->activated_subscription_id);
            }

            if ($fresh->checkout_status === 'cancelled' || $fresh->checkout_status === 'expired') {
                throw new \RuntimeException('Checkout session is not payable anymore.');
            }

            $this->cancelRunningSubscriptions($workspace);

            $subscription = Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $fresh->plan_id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $this->nextPeriodEnd($fresh->plan->billing_period),
                'provider' => $fresh->payment_provider,
                'provider_subscription_id' => 'sub_'.Str::lower(Str::random(24)),
                'metadata' => [
                    'checkout_session_id' => $fresh->id,
                    'payment_reference' => $paymentReference,
                    'activated_via' => 'frontend-confirmation-flow',
                ],
            ]);

            $fresh->update([
                'checkout_status' => 'completed',
                'payment_status' => 'paid',
                'subscription_status' => 'activated',
                'paid_at' => now(),
                'payment_reference' => $paymentReference ?: 'pay_'.Str::lower(Str::random(12)),
                'activated_subscription_id' => $subscription->id,
            ]);

            return $subscription;
        });
    }

    public function availablePlans(?string $workspaceType = null): Collection
    {
        return Plan::query()
            ->when($workspaceType, fn ($query) => $query->where('workspace_type', $workspaceType))
            ->where('is_active', true)
            ->orderBy('price')
            ->get();
    }

    private function cancelRunningSubscriptions(Workspace $workspace): void
    {
        Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }

    private function nextPeriodEnd(string $billingPeriod): \Illuminate\Support\Carbon
    {
        return match ($billingPeriod) {
            'yearly' => now()->addYear(),
            'lifetime' => now()->addYears(100),
            default => now()->addMonth(),
        };
    }
}
