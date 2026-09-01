<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\JsonResponse;
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
            'storeOrderUrl' => route('workspace.pos.orders.store'),
        ]);
    }

    public function storeOrder(StorePosOrderRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        try {
            $order = $this->posOrderService->createPosOrder(
                workspace: $this->currentWorkspace(),
                payload: $request->validated(),
                actor: $request->user()
            );
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

        $invoiceId = null;
        $printUrl = null;
        $invoiceError = null;

        if (! $order->dining_table_id) {
            try {
                $invoice = $this->posOrderService->createInvoiceFromOrder($order, (int) $request->user()?->id);
                $invoiceId = $invoice->id;
                $printUrl = route('workspace.pos.invoices.print', $invoice);
            } catch (RuntimeException $exception) {
                $invoiceError = $exception->getMessage();
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_id' => $invoiceId,
                'print_url' => $printUrl,
                'invoice_error' => $invoiceError,
            ], 201);
        }

        // Classic form: stay on cashier — printing is optional via flash print_url.
        $redirect = back()->with('success', 'تم إنشاء الطلب بنجاح.'.($order->order_number ? ' رقم الطلب: #'.$order->order_number : ''));

        if ($printUrl) {
            $redirect->with('print_url', $printUrl)->with('order_number', $order->order_number);
        }

        if ($invoiceError) {
            $redirect->with('error', $invoiceError);
        }

        return $redirect;
    }
}
