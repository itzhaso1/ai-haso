<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Http\Requests\Pos\UpdatePosOrderStatusRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
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

        $products = Product::query()
            ->with('category:id,name')
            ->where('status', 'active')
            ->orderBy('menu_sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'description',
                'price',
                'sale_price',
                'currency',
                'stock',
                'show_in_menu',
                'allow_online_ordering',
            ]);

        $orders = Order::query()
            ->with(['table:id,name', 'tableSession:id,dining_table_id,opened_at,status', 'items'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.pos.cashier.index', [
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'tables' => DiningTable::query()->orderBy('name')->get(['id', 'name', 'status']),
            'orders' => $orders,
            'posStatuses' => $this->posStatusLabels(),
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

    public function updateOrderStatus(UpdatePosOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);

        try {
            $this->posOrderService->updatePosStatus(
                $order,
                $request->string('pos_status')->toString(),
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function createInvoice(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'invoices.create');
        $this->authorize('update', $order);

        try {
            $invoice = $this->posOrderService->createInvoiceFromOrder($order->load(['workspace', 'items.product']), (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('workspace.finance.invoices.show', $invoice)
            ->with('success', 'تم إنشاء فاتورة من الطلب.');
    }

    /**
     * @return array<string,string>
     */
    private function posStatusLabels(): array
    {
        return [
            'new' => 'جديد',
            'accepted' => 'تم القبول',
            'preparing' => 'قيد التحضير',
            'ready' => 'جاهز',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
    }
}
