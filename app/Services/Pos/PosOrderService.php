<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosMenuItem;
use App\Models\TableSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoiceService;
use App\Services\Order\OrderService;
use Illuminate\Support\Collection;
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
        return DB::transaction(function () use ($workspace, $payload, $actor): Order {
            [$table, $session] = $this->resolveTableAndSession($workspace->id, $payload['dining_table_id'] ?? null);
            $items = $this->normalizePosItems(
                workspaceId: $workspace->id,
                items: $payload['items'] ?? [],
                requireActive: true
            );

            $order = $this->createOrderWithSnapshots(
                workspace: $workspace,
                customerId: isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
                source: 'pos',
                items: $items,
                table: $table,
                session: $session,
                discountAmount: (float) ($payload['discount_amount'] ?? 0),
                notes: $payload['notes'] ?? null,
                metadata: [
                    'channel' => 'cashier',
                    'created_by_user_id' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ],
                currency: (string) ($payload['currency'] ?? 'USD'),
            );

            event(new \App\Events\OrderCreated($order));

            return $order->fresh(['items', 'customer', 'table', 'tableSession']);
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createQrMenuOrder(Workspace $workspace, ?DiningTable $table, array $payload): Order
    {
        return DB::transaction(function () use ($workspace, $table, $payload): Order {
            $session = $table ? $this->ensureOpenSession($table) : null;
            $customerId = $this->resolveWalkInCustomerId($workspace, $payload);
            $items = $this->normalizePosItems(
                workspaceId: $workspace->id,
                items: $payload['items'] ?? [],
                requireActive: true
            );

            $order = $this->createOrderWithSnapshots(
                workspace: $workspace,
                customerId: $customerId,
                source: 'qr_menu',
                items: $items,
                table: $table,
                session: $session,
                discountAmount: 0,
                notes: $payload['notes'] ?? null,
                metadata: [
                    'channel' => 'qr_menu',
                    'customer_name' => $payload['customer_name'] ?? null,
                    'customer_phone' => $payload['customer_phone'] ?? null,
                ],
                currency: (string) ($items->first()['currency'] ?? 'USD'),
            );

            event(new \App\Events\OrderCreated($order));

            return $order->fresh(['items', 'customer', 'table', 'tableSession']);
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

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateOrderItems(Order $order, array $payload): Order
    {
        if (! in_array($order->source, ['pos', 'qr_menu'], true)) {
            throw new RuntimeException('يمكن تعديل عناصر فواتير POS فقط.');
        }

        if (in_array($order->pos_status, ['completed', 'cancelled'], true)) {
            throw new RuntimeException('لا يمكن تعديل طلب مكتمل أو ملغي.');
        }

        $incomingItems = collect($payload['items'] ?? []);
        if ($incomingItems->isEmpty()) {
            throw new RuntimeException('يجب إرسال عناصر للتعديل.');
        }

        return DB::transaction(function () use ($order, $incomingItems, $payload): Order {
            $existingIds = $order->items()->pluck('id')->all();

            foreach ($incomingItems as $line) {
                $itemId = (int) ($line['id'] ?? 0);
                if (! in_array($itemId, $existingIds, true)) {
                    continue;
                }

                $remove = (bool) ($line['remove'] ?? false);
                if ($remove) {
                    OrderItem::query()->where('order_id', $order->id)->whereKey($itemId)->delete();
                    continue;
                }

                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = max(0, (float) ($line['unit_price'] ?? 0));
                $lineDiscount = max(0, (float) ($line['discount_amount'] ?? 0));
                $lineTotal = max(0, ($quantity * $unitPrice) - $lineDiscount);

                OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereKey($itemId)
                    ->update([
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $lineDiscount,
                        'total_amount' => $lineTotal,
                    ]);
            }

            $itemCount = OrderItem::query()->where('order_id', $order->id)->count();
            if ($itemCount === 0) {
                throw new RuntimeException('لا يمكن حفظ طلب بدون عناصر.');
            }

            $subtotal = (float) OrderItem::query()->where('order_id', $order->id)->sum('total_amount');
            $discountAmount = max(0, (float) ($payload['discount_amount'] ?? $order->discount_amount));
            $order->update([
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discountAmount,
                'total_amount' => max(0, round($subtotal - $discountAmount, 2)),
            ]);

            return $order->fresh(['items', 'table', 'tableSession', 'customer']);
        });
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

        $hasRunningOrders = Order::query()
            ->where('table_session_id', $session->id)
            ->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready'])
            ->exists();

        if ($hasRunningOrders) {
            throw new RuntimeException('لا يمكن إغلاق الجلسة بوجود طلبات جارية.');
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
            return [
                'product_id' => null,
                'product_name' => $item->product_name ?: 'Item',
                'description' => $item->variant_name ? 'Variant: '.$item->variant_name : null,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount_amount,
                'tax_rate' => 0.0,
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
     * @return Collection<int,array<string,mixed>>
     */
    private function normalizePosItems(
        int $workspaceId,
        array $items,
        bool $requireActive
    ): Collection
    {
        $normalized = collect();

        foreach ($items as $item) {
            $menuItem = PosMenuItem::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereKey((int) ($item['pos_menu_item_id'] ?? 0))
                ->first();

            if (! $menuItem) {
                throw new RuntimeException('أحد أصناف الكاشير غير صالح.');
            }

            if ($requireActive && ! $menuItem->is_active) {
                throw new RuntimeException('أحد أصناف الكاشير غير مفعل.');
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) $menuItem->price;
            $lineTotal = round($quantity * $unitPrice, 2);
            $normalized->push([
                'pos_menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'item_type' => $menuItem->item_type ?: 'عام',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $lineTotal,
                'currency' => $menuItem->currency ?: 'USD',
            ]);
        }

        if ($normalized->isEmpty()) {
            throw new RuntimeException('لا يمكن إنشاء طلب بدون عناصر.');
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{0:DiningTable|null,1:TableSession|null}
     */
    private function resolveTableAndSession(int $workspaceId, mixed $diningTableId): array
    {
        if (empty($diningTableId)) {
            return [null, null];
        }

        $table = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereKey((int) $diningTableId)
            ->first();
        if (! $table) {
            throw new RuntimeException('الطاولة المحددة غير صالحة.');
        }

        $session = $this->ensureOpenSession($table);
        $table->update(['status' => 'occupied']);

        return [$table, $session];
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $items
     * @param  array<string,mixed>|null  $metadata
     */
    private function createOrderWithSnapshots(
        Workspace $workspace,
        ?int $customerId,
        string $source,
        Collection $items,
        ?DiningTable $table,
        ?TableSession $session,
        float $discountAmount,
        ?string $notes,
        ?array $metadata,
        string $currency
    ): Order {
        $subtotal = round((float) $items->sum('total_amount'), 2);
        $discountAmount = max(0, round($discountAmount, 2));
        $total = max(0, round($subtotal - $discountAmount, 2));

        $order = Order::query()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customerId,
            'dining_table_id' => $table?->id,
            'table_session_id' => $session?->id,
            'order_number' => $this->nextOrderNumber(),
            'source' => $source,
            'status' => 'confirmed',
            'pos_status' => 'new',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'shipping_status' => 'not_shipped',
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'shipping_amount' => 0,
            'total_amount' => $total,
            'notes' => $notes,
            'metadata' => $metadata,
            'placed_at' => now(),
        ]);

        foreach ($items as $item) {
            $order->items()->create([
                'workspace_id' => $workspace->id,
                'order_id' => $order->id,
                'product_id' => null,
                'product_variant_id' => null,
                'pos_menu_item_id' => $item['pos_menu_item_id'],
                'product_name' => $item['name'],
                'variant_name' => null,
                'item_type' => $item['item_type'],
                'sku' => null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => 0,
                'total_amount' => $item['total_amount'],
            ]);
        }

        if ($customerId) {
            $customer = Customer::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey($customerId)
                ->first();
            if ($customer) {
                $customer->update([
                    'orders_count' => $customer->orders()->count(),
                    'total_purchases' => $customer->orders()->sum('total_amount'),
                    'last_order_at' => now(),
                ]);
            }
        }

        return $order;
    }

    private function nextOrderNumber(): string
    {
        $lastId = (Order::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'POS-'.str_pad((string) $lastId, 8, '0', STR_PAD_LEFT);
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
