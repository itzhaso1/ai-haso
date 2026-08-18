<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function index(Request $request): View
    {
        $movements = InventoryMovement::query()
            ->with(['product', 'variant', 'user'])
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where('reference_type', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.inventory.index', compact('movements'));
    }

    public function create(): View
    {
        return view('workspace.inventory.create', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'stock']),
            'variants' => ProductVariant::query()->orderBy('name')->get(['id', 'product_id', 'name', 'stock']),
        ]);
    }

    public function store(AdjustInventoryRequest $request): RedirectResponse
    {
        $this->inventoryService->adjustStock(
            productId: $request->integer('product_id'),
            variantId: $request->integer('product_variant_id') ?: null,
            type: $request->string('type')->toString(),
            quantity: $request->integer('quantity'),
            actor: $request->user(),
            referenceType: $request->string('reference_type')->toString() ?: null,
            referenceId: $request->integer('reference_id') ?: null,
            notes: $request->string('notes')->toString() ?: null,
        );

        return redirect()->route('workspace.inventory.index')->with('success', 'تم تسجيل حركة المخزون.');
    }
}
