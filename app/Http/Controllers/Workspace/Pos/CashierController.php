<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\PosMenuItem;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CashierController extends PosBaseController
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $items = PosMenuItem::query()
            ->where('is_active', true)
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('item_type', $type))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'price',
                'currency',
                'item_type',
                'image_path',
            ]);

        return view('workspace.pos.cashier.index', [
            'items' => $items,
            'types' => PosMenuItem::query()->select('item_type')->distinct()->orderBy('item_type')->pluck('item_type')->filter()->values(),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'tables' => DiningTable::query()->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function storeOrder(StorePosOrderRequest $request): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        try {
            $this->posOrderService->createPosOrder(
                workspace: $this->currentWorkspace(),
                payload: $request->validated(),
                actor: $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء طلب POS بنجاح.');
    }
}
