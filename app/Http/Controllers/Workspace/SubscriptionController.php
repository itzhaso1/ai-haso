<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Plan;
use App\Models\Subscription;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()
            ->whereKey($validated['plan_id'])
            ->where('workspace_type', $workspace->type)
            ->firstOrFail();

        $this->subscriptionService->activatePlan($workspace, $plan);

        return redirect()->route('workspace.subscriptions.index')->with('success', 'تم تفعيل الخطة الجديدة.');
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
