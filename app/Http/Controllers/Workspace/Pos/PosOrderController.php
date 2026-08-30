<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\UpdatePosOrderStatusRequest;
use App\Http\Requests\Pos\UpdateTableOrderRequest;
use App\Models\Order;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PosOrderController extends PosBaseController
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
    ) {}

    public function running(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $orders = Order::query()
            ->with(['table:id,name', 'tableSession:id,dining_table_id,opened_at,status', 'items'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.pos.orders.running', [
            'orders' => $orders,
            'posStatuses' => $this->posStatusLabels(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $this->authorizePos($request, 'invoices.view');

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $orders = Order::query()
            ->with(['table:id,name', 'financeInvoice'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->where('pos_status', 'completed')
            ->whereNotNull('finance_invoice_id')
            ->whereDate('placed_at', $date)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.pos.invoices.index', [
            'orders' => $orders,
            'date' => $date,
        ]);
    }

    public function updateStatus(UpdatePosOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $this->posOrderService->updatePosStatus($order, $request->string('pos_status')->toString(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function createInvoice(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'invoices.create');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $invoice = $this->posOrderService->createInvoiceFromOrder($order->load(['workspace', 'items']), (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم إنشاء فاتورة بنجاح.');
    }

    public function updateItems(UpdateTableOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $this->posOrderService->updateOrderItems($order, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تعديل الفاتورة داخل الجلسة.');
    }

    public function printInvoice(Request $request, Order $order): View
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('view', $order);
        $this->ensurePosOrder($order);

        return view('workspace.pos.orders.print', [
            'order' => $order->load(['items', 'table', 'tableSession', 'customer', 'financeInvoice']),
        ]);
    }

    private function ensurePosOrder(Order $order): void
    {
        abort_unless(in_array($order->source, ['pos', 'qr_menu'], true), 404);
    }

    /**
     * @return array<string,string>
     */
    private function posStatusLabels(): array
    {
        return [
            'new' => 'NEW',
            'accepted' => 'ACCEPTED',
            'preparing' => 'PREPARING',
            'ready' => 'READY',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
        ];
    }
}
