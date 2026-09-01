<?php

namespace App\Http\Controllers\Api\Cashier\V1;

use App\Http\Controllers\Api\Cashier\CashierController;
use App\Http\Controllers\Api\Cashier\Concerns\AuthorizesCashier;
use App\Http\Controllers\Api\Cashier\Concerns\ResolvesCashierWorkspace;
use App\Http\Resources\Cashier\MenuItemResource;
use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use App\Services\Feature\FeatureAccessService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends CashierController
{
    use AuthorizesCashier;
    use ResolvesCashierWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    public function categories(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace);
        $this->ensurePos($workspace);

        $categories = PosItemCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'is_active']);

        return $this->ok([
            'categories' => $categories->map(fn (PosItemCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => (int) $category->sort_order,
                'is_active' => (bool) $category->is_active,
            ])->values(),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace);
        $this->ensurePos($workspace);

        $search = trim((string) $request->query('q', ''));
        $barcode = trim((string) $request->query('barcode', ''));
        $sku = trim((string) $request->query('sku', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $activeOnly = $request->boolean('active_only', true);

        $query = PosMenuItem::query()
            ->with(['category:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($categoryId) {
            $query->where('pos_item_category_id', $categoryId);
        }

        if ($barcode !== '') {
            $query->where('barcode', $barcode);
        }

        if ($sku !== '') {
            $query->where('sku', $sku);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhere('item_type', 'like', '%'.$search.'%');
            });
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $items = $query->paginate($perPage);

        return $this->ok([
            'items' => MenuItemResource::collection($items->getCollection()),
        ], meta: [
            'current_page' => $items->currentPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'last_page' => $items->lastPage(),
        ]);
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
