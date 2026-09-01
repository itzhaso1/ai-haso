<?php

namespace App\Http\Controllers\Api\Cashier\V1;

use App\Http\Controllers\Api\Cashier\CashierController;
use App\Http\Controllers\Api\Cashier\Concerns\AuthorizesCashier;
use App\Http\Controllers\Api\Cashier\Concerns\ResolvesCashierWorkspace;
use App\Models\Customer;
use App\Services\Feature\FeatureAccessService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends CashierController
{
    use AuthorizesCashier;
    use ResolvesCashierWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace);
        $this->ensurePos($workspace);

        $q = trim((string) $request->query('q', ''));
        $query = Customer::query()->latest('id');

        if ($q !== '') {
            $query->where(function ($inner) use ($q): void {
                $inner->where('name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%');
            });
        }

        $customers = $query->limit(30)->get(['id', 'name', 'phone']);

        return $this->ok([
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace);
        $this->ensurePos($workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:32',
                Rule::unique('customers', 'phone')->where(fn ($q) => $q->where('workspace_id', $workspace->id)),
            ],
        ]);

        $customer = Customer::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'orders_count' => 0,
            'total_purchases' => 0,
        ]);

        return $this->ok([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ], message: 'تم إنشاء العميل.', status: 201);
    }

    private function ensurePos(\App\Models\Workspace $workspace): void
    {
        if (! $this->featureAccessService->workspaceHasFeature($workspace, 'pos')) {
            throw new HttpResponseException(
                $this->fail('الكاشير غير متاح في باقتك الحالية', 403, meta: [
                    'pos_enabled' => false,
                    'plans_url' => url('/workspace/billing'),
                ])
            );
        }
    }
}
