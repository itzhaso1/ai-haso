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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $quantityByType = (clone $lineBaseQuery)
            ->selectRaw("COALESCE(order_items.item_type, 'عام') as item_type, SUM(order_items.quantity) as quantity, SUM(order_items.total_amount) as sales")
            ->groupBy(DB::raw("COALESCE(order_items.item_type, 'عام')"))
            ->orderByDesc('quantity')
            ->get()
            ->map(fn ($row) => [
                'item_type' => $row->item_type,
                'quantity' => (int) $row->quantity,
                'sales' => (float) $row->sales,
            ])
            ->values();

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

        $salesByHour = $this->buildSalesByHour($orders->whereNotNull('pos_cashier_invoice_id'));
        $customerSummary = $this->buildCustomerSummary($orders);

        return $this->ok([
            'date' => $date,
            'summary' => [
                'invoices_count' => $cashierInvoices->count(),
                'invoices_total' => (float) $cashierInvoices->sum('total_amount'),
                'invoice_sales_total' => (float) $cashierInvoices->sum('total_amount'),
                'orders_count' => $orders->count(),
                'orders_total' => (float) $orders->sum('total_amount'),
                'total_quantity' => (int) $quantityByType->sum('quantity'),
                'paid_orders_count' => $orders->where('payment_status', 'paid')->count(),
                'unpaid_orders_count' => $orders->where('payment_status', '!=', 'paid')->count(),
                'open_orders_count' => $orders
                    ->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready', 'delivered'])
                    ->where('payment_status', '!=', 'paid')
                    ->whereNull('pos_cashier_invoice_id')
                    ->count(),
                'completed_orders_count' => $orders->where('pos_status', 'completed')->count(),
                'cancelled_orders_count' => $orders->where('pos_status', 'cancelled')->count(),
                'discount_total' => (float) $orders->sum('discount_amount'),
                'tax_total' => (float) $orders->sum('tax_amount'),
                'table_orders_count' => (int) ($channelStats['table'] ?? 0),
                'takeaway_orders_count' => (int) ($channelStats['takeaway'] ?? 0),
                'delivery_orders_count' => (int) ($channelStats['delivery'] ?? 0),
            ],
            'channel_stats' => $channelStats,
            'payment_methods' => $orders
                ->map(fn (Order $order) => data_get($order->metadata, 'payment_method') ?: (
                    $order->payment_status === 'paid' ? 'paid' : 'pending'
                ))
                ->countBy()
                ->map(fn ($count, $method) => [
                    'method' => (string) $method,
                    'orders_count' => (int) $count,
                ])
                ->values(),
            'quantity_by_type' => $quantityByType,
            'top_items' => $topItems,
            'sales_by_hour' => $salesByHour,
            'customer_summary' => $customerSummary,
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

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array<string, mixed>>
     */
    private function buildCustomerSummary(Collection $orders): array
    {
        return $orders
            ->filter(fn (Order $order): bool => $order->pos_cashier_invoice_id !== null)
            ->groupBy(function (Order $order): string {
                $name = $order->customer?->name
                    ?: data_get($order->metadata, 'customer_name')
                    ?: 'Walk-in';
                $phone = $order->customer?->phone ?: data_get($order->metadata, 'customer_phone');

                return trim($name.'|'.($phone ?: ''));
            })
            ->map(function (Collection $group, string $key): array {
                [$name, $phone] = array_pad(explode('|', $key, 2), 2, '');

                return [
                    'customer_name' => $name !== '' ? $name : 'Walk-in',
                    'customer_phone' => $phone !== '' ? $phone : '—',
                    'orders_count' => $group->count(),
                    'total_sales' => (float) $group->sum(fn (Order $order) => (float) $order->total_amount),
                    'last_order_at' => optional($group->max('placed_at'))?->toIso8601String(),
                ];
            })
            ->sortByDesc('total_sales')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<array<string, mixed>>
     */
    private function buildSalesByHour(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (Order $order): string => $order->placed_at?->format('H:00') ?: '00:00')
            ->map(fn (Collection $group, string $hour): array => [
                'hour' => $hour,
                'orders_count' => $group->count(),
                'total_sales' => (float) $group->sum(fn (Order $order) => (float) $order->total_amount),
            ])
            ->sortKeys()
            ->values()
            ->all();
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
