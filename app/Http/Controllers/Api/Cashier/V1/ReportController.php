<?php

namespace App\Http\Controllers\Api\Cashier\V1;

use App\Http\Controllers\Api\Cashier\CashierController;
use App\Http\Controllers\Api\Cashier\Concerns\AuthorizesCashier;
use App\Http\Controllers\Api\Cashier\Concerns\ResolvesCashierWorkspace;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCashierInvoice;
use App\Services\Feature\FeatureAccessService;
use App\Services\Pos\PosOrderStatsService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends CashierController
{
    use AuthorizesCashier;
    use ResolvesCashierWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FeatureAccessService $featureAccessService,
        private readonly PosOrderStatsService $posOrderStatsService,
    ) {}

    public function daily(Request $request): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);
        $this->authorizeCashier($request, $workspace, 'reports.view');
        $this->ensurePos($workspace);

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $cashierInvoices = PosCashierInvoice::query()
            ->with(['table:id,name', 'closer:id,name'])
            ->whereDate('closed_at', $date)
            ->latest('id')
            ->get();

        $orders = Order::query()
            ->with(['customer:id,name,phone', 'table:id,name'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->whereDate('placed_at', $date)
            ->latest('id')
            ->get();

        $closedOrderIds = $orders->whereNotNull('pos_cashier_invoice_id')->pluck('id')->all();
        $lineBaseQuery = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.id', $closedOrderIds === [] ? [0] : $closedOrderIds);

        $topItems = (clone $lineBaseQuery)
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.total_amount) as sales')
            ->groupBy('order_items.product_name')
            ->orderByDesc('quantity')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity,
                'sales' => (float) $row->sales,
            ])
            ->values();

        $channelStats = $this->posOrderStatsService->channelCounts(
            \Illuminate\Support\Carbon::parse($date)->startOfDay()
        );

        return $this->ok([
            'date' => $date,
            'summary' => [
                'invoices_count' => $cashierInvoices->count(),
                'invoices_total' => (float) $cashierInvoices->sum('total_amount'),
                'orders_count' => $orders->count(),
                'orders_total' => (float) $orders->sum('total_amount'),
            ],
            'channel_stats' => $channelStats,
            'top_items' => $topItems,
            'invoices' => $cashierInvoices->map(fn (PosCashierInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => (float) $invoice->total_amount,
                'currency' => $invoice->currency,
                'closed_at' => optional($invoice->closed_at)?->toIso8601String(),
                'table' => $invoice->table ? ['id' => $invoice->table->id, 'name' => $invoice->table->name] : null,
                'closer' => $invoice->closer ? ['id' => $invoice->closer->id, 'name' => $invoice->closer->name] : null,
            ])->values(),
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
