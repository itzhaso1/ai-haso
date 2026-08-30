<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoiceService;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceService $invoiceService,
        private readonly FinanceBootstrapService $financeBootstrapService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createPosOrder(Workspace $workspace, array $payload, ?User $actor): Order
    {
        $table = null;
        $session = null;

        if (! empty($payload['dining_table_id'])) {
            $table = DiningTable::query()->whereKey((int) $payload['dining_table_id'])->first();
            if (! $table) {
                throw new RuntimeException('الطاولة المحددة غير صالحة.');
            }

            $session = $this->ensureOpenSession($table);
            $table->update(['status' => 'occupied']);
        }

        $items = $this->normalizeItemsForMenu(
            workspaceId: $workspace->id,
            items: $payload['items'] ?? [],
            requireOnlineOrdering: false,
            requireVisibleInMenu: false
        );

        return $this->orderService->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $payload['customer_id'] ?? null,
            'dining_table_id' => $table?->id,
            'table_session_id' => $session?->id,
            'source' => 'pos',
            'pos_status' => 'new',
            'status' => 'confirmed',
            'currency' => $payload['currency'] ?? 'USD',
            'discount_amount' => $payload['discount_amount'] ?? 0,
            'shipping_amount' => 0,
            'notes' => $payload['notes'] ?? null,
            'metadata' => [
                'channel' => 'cashier',
            ],
            'items' => $items,
        ], $actor);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createQrMenuOrder(Workspace $workspace, ?DiningTable $table, array $payload): Order
    {
        return DB::transaction(function () use ($workspace, $table, $payload): Order {
            $session = null;
            if ($table) {
                $session = $this->ensureOpenSession($table);
                $table->update(['status' => 'occupied']);
            }

            $customerId = $this->resolveWalkInCustomerId($workspace, $payload);
            $items = $this->normalizeItemsForMenu(
                workspaceId: $workspace->id,
                items: $payload['items'] ?? [],
                requireOnlineOrdering: true,
                requireVisibleInMenu: true
            );

            return $this->orderService->create([
                'workspace_id' => $workspace->id,
                'customer_id' => $customerId,
                'dining_table_id' => $table?->id,
                'table_session_id' => $session?->id,
                'source' => 'qr_menu',
                'pos_status' => 'new',
                'status' => 'confirmed',
                'currency' => 'USD',
                'discount_amount' => 0,
                'shipping_amount' => 0,
                'notes' => $payload['notes'] ?? null,
                'metadata' => [
                    'channel' => 'qr_menu',
                    'customer_name' => $payload['customer_name'] ?? null,
                    'customer_phone' => $payload['customer_phone'] ?? null,
                ],
                'items' => $items,
            ]);
        });
    }

    public function updatePosStatus(Order $order, string $status, ?User $actor): Order
    {
        if ($status === 'cancelled') {
            return $this->orderService->cancel($order->load('items'), $actor);
        }

        $attributes = ['pos_status' => $status];
        if (in_array($status, ['accepted', 'preparing', 'ready'], true)) {
            $attributes['fulfillment_status'] = 'processing';
            $attributes['status'] = 'confirmed';
        }

        if ($status === 'completed') {
            $attributes['fulfillment_status'] = 'fulfilled';
            $attributes['status'] = 'completed';
        }

        $order->update($attributes);

        return $order->fresh(['items', 'customer', 'table', 'tableSession']);
    }

    public function openSession(DiningTable $table): TableSession
    {
        $session = $this->ensureOpenSession($table);
        $table->update(['status' => 'occupied']);

        return $session;
    }

    public function closeSession(TableSession $session): TableSession
    {
        if ($session->status === 'closed') {
            return $session;
        }

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $table = $session->table;
        if ($table) {
            $hasOpenSessions = $table->sessions()->where('status', 'open')->where('id', '!=', $session->id)->exists();
            $table->update(['status' => $hasOpenSessions ? 'occupied' : 'available']);
        }

        return $session->fresh();
    }

    public function createInvoiceFromOrder(Order $order, int $actorUserId): FinanceInvoice
    {
        if ($order->finance_invoice_id) {
            $invoice = FinanceInvoice::withoutGlobalScopes()->whereKey($order->finance_invoice_id)->first();
            if ($invoice) {
                return $invoice;
            }
        }

        $workspace = $order->workspace;
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $items = $order->items->map(function ($item): array {
            $product = $item->product;

            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?: ($product?->name ?? 'Item'),
                'description' => $item->variant_name ? 'Variant: '.$item->variant_name : null,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount_amount,
                'tax_rate' => (float) ($product?->vat_rate ?? 0),
            ];
        })->values()->all();

        $invoice = $this->invoiceService->create($workspace, [
            'type' => 'sales',
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer_id ? null : 'Walk-in POS',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'currency' => $order->currency ?: 'USD',
            'invoice_status' => 'issued',
            'notes' => 'Invoice generated from POS order '.$order->order_number,
            'items' => $items,
        ], $actorUserId);

        $order->update(['finance_invoice_id' => $invoice->id]);

        return $invoice;
    }

    private function ensureOpenSession(DiningTable $table): TableSession
    {
        $session = TableSession::query()
            ->where('dining_table_id', $table->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($session) {
            return $session;
        }

        return TableSession::query()->create([
            'workspace_id' => $table->workspace_id,
            'dining_table_id' => $table->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    /**
     * @param  array<int,mixed>  $items
     * @return array<int,array<string,mixed>>
     */
    private function normalizeItemsForMenu(
        int $workspaceId,
        array $items,
        bool $requireOnlineOrdering,
        bool $requireVisibleInMenu
    ): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $product = Product::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereKey((int) ($item['product_id'] ?? 0))
                ->first();

            if (! $product || $product->status !== 'active') {
                throw new RuntimeException('أحد المنتجات غير متاح في المنيو.');
            }

            if ($requireVisibleInMenu && ! $product->show_in_menu) {
                throw new RuntimeException('أحد المنتجات مخفي عن المنيو.');
            }

            if ($requireOnlineOrdering && ! $product->allow_online_ordering) {
                throw new RuntimeException('أحد المنتجات غير متاح للطلب عبر المنيو.');
            }

            $normalized[] = [
                'product_id' => $product->id,
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('لا يمكن إنشاء طلب بدون عناصر.');
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveWalkInCustomerId(Workspace $workspace, array $payload): ?int
    {
        $name = trim((string) ($payload['customer_name'] ?? ''));
        $phone = trim((string) ($payload['customer_phone'] ?? ''));

        if ($name === '' && $phone === '') {
            return null;
        }

        $existing = Customer::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->when($phone !== '', fn ($query) => $query->where('phone', $phone))
            ->when($phone === '' && $name !== '', fn ($query) => $query->where('name', $name))
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => $name !== '' ? $name : 'Walk-in Customer',
            'phone' => $phone !== '' ? $phone : null,
            'orders_count' => 0,
            'total_purchases' => 0,
        ]);

        return $customer->id;
    }
}
