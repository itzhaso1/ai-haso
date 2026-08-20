<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutSession;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function index(): View
    {
        $workspace = $this->currentWorkspace();

        return view('workspace.subscriptions.index', [
            'workspace' => $workspace,
            'currentSubscription' => $this->subscriptionService->current($workspace),
            'subscriptions' => Subscription::query()->with('plan')->latest('id')->paginate(10),
            'availablePlans' => $this->subscriptionService->availablePlans($workspace->type),
            'checkoutSessions' => SubscriptionCheckoutSession::query()
                ->with(['plan', 'activatedSubscription'])
                ->latest('id')
                ->paginate(10, ['*'], 'checkouts'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_provider' => ['nullable', 'in:hyperpay,local'],
        ]);

        $plan = Plan::query()
            ->whereKey($validated['plan_id'])
            ->where('is_active', true)
            ->where('workspace_type', $workspace->type)
            ->firstOrFail();

        $checkoutSession = $this->subscriptionService->createCheckoutSession(
            workspace: $workspace,
            plan: $plan,
            paymentProvider: (string) ($validated['payment_provider'] ?? 'hyperpay'),
        );

        return redirect()
            ->route('workspace.subscriptions.checkout.show', $checkoutSession)
            ->with('success', 'تم تجهيز عملية الاشتراك. أكمل الدفع لتفعيل الباقة.');
    }

    public function showCheckout(SubscriptionCheckoutSession $checkoutSession): View
    {
        return view('workspace.subscriptions.checkout', [
            'checkoutSession' => $checkoutSession->load(['plan', 'activatedSubscription']),
        ]);
    }

    public function confirmCheckoutPayment(Request $request, SubscriptionCheckoutSession $checkoutSession): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $subscription = $this->subscriptionService->completeCheckoutAndActivate(
                workspace: $workspace,
                checkoutSession: $checkoutSession,
                paymentReference: $validated['payment_reference'] ?? null,
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('workspace.subscriptions.checkout.show', $checkoutSession)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('workspace.subscriptions.index')
            ->with('success', "تم تأكيد الدفع وتفعيل الباقة {$subscription->plan?->name} بنجاح.");
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);

        return redirect()->route('workspace.subscriptions.index')->with('success', 'تم إلغاء الاشتراك.');
    }
}
