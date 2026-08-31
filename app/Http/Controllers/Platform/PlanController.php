<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->latest('id')->paginate(15);

        return view('platform.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('platform.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        Plan::query()->create($payload);

        return redirect()->route('platform.plans.index')->with('success', 'تم إنشاء الخطة.');
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.edit', [
            'plan' => $plan,
            'featuresJson' => json_encode($plan->features ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'permissionsJson' => json_encode($plan->permissions ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'limitsJson' => json_encode($plan->limits ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validatePayload($request, $plan));

        return redirect()->route('platform.plans.index')->with('success', 'تم تحديث الخطة.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('platform.plans.index')->with('success', 'تم حذف الخطة.');
    }

    private function validatePayload(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['nullable', 'string', 'max:32'],
            'workspace_type' => ['required', 'in:individual,company,store'],
            'billing_period' => ['required', 'in:monthly,yearly,lifetime'],
            'currency' => ['required', 'string', 'size:3'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
            'features_json' => ['nullable', 'string'],
            'permissions_json' => ['nullable', 'string'],
            'limits_json' => ['nullable', 'string'],
        ]);

        $featuresFromJson = json_decode((string) $request->input('features_json', '[]'), true);
        $featuresFromCheckboxes = $request->input('features', []);
        $features = is_array($featuresFromJson) && count($featuresFromJson) > 0 ? $featuresFromJson : $featuresFromCheckboxes;

        $permissionsFromJson = json_decode((string) $request->input('permissions_json', '[]'), true);
        $permissionsFromCheckboxes = $request->input('permissions', []);
        $permissions = is_array($permissionsFromJson) && count($permissionsFromJson) > 0 ? $permissionsFromJson : $permissionsFromCheckboxes;

        $limits = json_decode((string) $request->input('limits_json', '{}'), true);

        return [
            'code' => $data['code'] ?: Str::slug($data['workspace_type'].'-'.$data['name']),
            'name' => $data['name'],
            'tier' => $data['tier'] ?? null,
            'workspace_type' => $data['workspace_type'],
            'billing_period' => $data['billing_period'],
            'currency' => strtoupper($data['currency']),
            'price' => $data['price'],
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
            'features' => is_array($features) ? $features : [],
            'permissions' => is_array($permissions) ? $permissions : [],
            'limits' => is_array($limits) ? $limits : [],
        ];
    }
}
