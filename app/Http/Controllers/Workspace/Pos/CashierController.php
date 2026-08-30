<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\PosItemCategory;
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
            ->with('category:id,name')
            ->where('is_active', true)
            ->when($request->integer('category_id'), fn ($query, $categoryId) => $query->where('pos_item_category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'pos_item_category_id',
                'name',
                'price',
                'currency',
                'item_type',
                'size_label',
                'image_path',
            ]);

        return view('workspace.pos.cashier.index', [
            'items' => $items,
            'categories' => PosItemCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'tables' => DiningTable::query()->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function storeOrder(StorePosOrderRequest $request): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        try {
            $order = $this->posOrderService->createPosOrder(
                workspace: $this->currentWorkspace(),
                payload: $request->validated(),
                actor: $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if (! $order->dining_table_id) {
            try {
                $invoice = $this->posOrderService->createInvoiceFromOrder($order, (int) $request->user()?->id);
            } catch (RuntimeException $exception) {
                return back()->with('success', 'تم إنشاء طلب الكاشير.')->with('error', $exception->getMessage());
            }

            return redirect()->route('workspace.pos.invoices.print', $invoice)->with('success', 'تم إنشاء طلب مباشر بدون طاولة وتجهيز فاتورة الطباعة.');
        }

        return back()->with('success', 'تم إنشاء طلب POS بنجاح.');
    }
}
