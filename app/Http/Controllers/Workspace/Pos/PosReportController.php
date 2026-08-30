<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosMenuItem;
use App\Models\TableSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosReportController extends PosBaseController
{
    public function daily(Request $request): View
    {
        $this->authorizePos($request, 'reports.view');
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $closedOrders = Order::query()
            ->with(['table:id,name', 'financeInvoice'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->whereDate('placed_at', $date)
            ->where('pos_status', 'completed')
            ->whereNotNull('finance_invoice_id')
            ->latest('id')
            ->get();

        $invoiceIds = $closedOrders->pluck('finance_invoice_id')->filter()->unique()->values();
        $invoiceSalesTotal = $invoiceIds->isEmpty()
            ? 0.0
            : (float) FinanceInvoice::query()->whereIn('id', $invoiceIds)->sum('total');

        $lineBaseQuery = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.workspace_id', $this->currentWorkspace()->id)
            ->whereIn('orders.source', ['pos', 'qr_menu'])
            ->whereDate('orders.placed_at', $date)
            ->where('orders.pos_status', 'completed');

        $quantityByType = (clone $lineBaseQuery)
            ->selectRaw("COALESCE(order_items.item_type, 'عام') as item_type, SUM(order_items.quantity) as quantity, SUM(order_items.total_amount) as sales")
            ->groupBy(DB::raw("COALESCE(order_items.item_type, 'عام')"))
            ->orderByDesc('quantity')
            ->get();

        $topItems = (clone $lineBaseQuery)
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.total_amount) as sales')
            ->groupBy('order_items.product_name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get();

        $recentOperations = AuditLog::query()
            ->with('user:id,name')
            ->whereDate('occurred_at', $date)
            ->whereIn('entity_type', [
                Order::class,
                OrderItem::class,
                DiningTable::class,
                TableSession::class,
                PosMenuItem::class,
                FinanceInvoice::class,
            ])
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        return view('workspace.pos.reports.daily', [
            'date' => $date,
            'summary' => [
                'invoice_sales_total' => $invoiceSalesTotal,
                'invoices_count' => $invoiceIds->count(),
                'total_quantity' => (int) $quantityByType->sum('quantity'),
            ],
            'quantityByType' => $quantityByType,
            'topTypes' => $quantityByType->take(10),
            'topItems' => $topItems,
            'recentOperations' => $recentOperations,
            'closedOrders' => $closedOrders,
        ]);
    }
}
