<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosCashierInvoice;
use App\Models\PosMenuItem;
use App\Models\TableSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
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
                    'payment_method' => 'cashier',
                ],
                currency: $this->resolveOrderCurrency($items),
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
            $session = null;
            if ($table) {
                $session = $this->ensureOpenSession($table);
                $table->update(['status' => 'occupied']);
            }
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
                    'payment_method' => $this->normalizePaymentMethod($payload),
                ],
                currency: $this->resolveOrderCurrency($items),
            );

            event(new \App\Events\OrderCreated($order));

            return $order->fresh(['items', 'customer', 'table', 'tableSession']);
        });
    }

    public function createPaymentLinkForOrder(Order $order): Payment
    {
        if (! in_array($order->source, ['pos', 'qr_menu'], true)) {
            throw new RuntimeException('رابط الدفع متاح لطلبات الكاشير فقط.');
        }

        $payment = $this->paymentService->createPaymentLink($order);

        $metadata = (array) ($order->metadata ?? []);
        $metadata['payment_method'] = 'pay_now';
        $order->update(['metadata' => $metadata]);

        return $payment;
    }

    public function updatePosStatus(Order $order, string $status, ?User $actor): Order
    {
        if ($status === 'cancelled') {
            return $this->orderService->cancel($order->load('items'), $actor);
        }

        $attributes = ['pos_status' => $status];
        if (in_array($status, ['accepted', 'preparing', 'ready', 'delivered'], true)) {
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
        $this->refreshTableStatus($table);

        return $session;
    }

    public function closeSession(TableSession $session, int $actorUserId): ?PosCashierInvoice
    {
        if (in_array($session->status, ['closed', 'cancelled'], true)) {
            return null;
        }

        return DB::transaction(function () use ($session, $actorUserId): ?PosCashierInvoice {
            $orders = Order::query()
                ->where('table_session_id', $session->id)
                ->whereIn('source', ['pos', 'qr_menu'])
                ->where('pos_status', '!=', 'cancelled')
                ->whereNull('pos_cashier_invoice_id')
                ->with('items')
                ->get();

            $invoice = null;
            if ($orders->isNotEmpty()) {
                $invoice = $this->buildCashierInvoiceFromOrders(
                    orders: $orders,
                    table: $session->table,
                    session: $session,
                    actorUserId: $actorUserId
                );

                Order::query()
                    ->whereIn('id', $orders->pluck('id')->all())
                    ->update([
                        'pos_cashier_invoice_id' => $invoice->id,
                        'pos_status' => 'completed',
                        'status' => 'completed',
                        'fulfillment_status' => 'fulfilled',
                    ]);
            }

            $session->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $table = $session->table;
            if ($table) {
                $this->refreshTableStatus($table, $session->id);
            }

            return $invoice;
        });
    }

    public function cancelSession(TableSession $session, ?User $actor): void
    {
        if (in_array($session->status, ['closed', 'cancelled'], true)) {
            return;
        }

        DB::transaction(function () use ($session, $actor): void {
            $orders = Order::query()
                ->where('table_session_id', $session->id)
                ->whereIn('source', ['pos', 'qr_menu'])
                ->whereNull('pos_cashier_invoice_id')
                ->where('pos_status', '!=', 'cancelled')
                ->with('items')
                ->get();

            foreach ($orders as $order) {
                $this->orderService->cancel($order, $actor);
            }

            $session->update([
                'status' => 'cancelled',
                'closed_at' => now(),
            ]);

            $table = $session->table;
            if ($table) {
                $this->refreshTableStatus($table, $session->id);
            }
        });
    }

    public function applySessionDiscount(TableSession $session, float $discountAmount): void
    {
        if ($session->status !== 'open') {
            throw new RuntimeException('يمكن تطبيق الخصم على الجلسات المفتوحة فقط.');
        }

        DB::transaction(function () use ($session, $discountAmount): void {
            $orders = Order::query()
                ->where('table_session_id', $session->id)
                ->whereIn('source', ['pos', 'qr_menu'])
                ->where('pos_status', '!=', 'cancelled')
                ->whereNull('pos_cashier_invoice_id')
                ->orderBy('id')
                ->get();

            if ($orders->isEmpty()) {
                return;
            }

            foreach ($orders as $order) {
                $subtotal = round((float) $order->items()->sum('total_amount'), 2);
                $order->update([
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'total_amount' => $subtotal,
                ]);
            }

            $remainingDiscount = max(0, round($discountAmount, 2));
            foreach ($orders as $order) {
                if ($remainingDiscount <= 0) {
                    break;
                }

                $subtotal = (float) $order->subtotal;
                $appliedDiscount = min($remainingDiscount, $subtotal);
                $remainingDiscount = round($remainingDiscount - $appliedDiscount, 2);

                $order->update([
                    'discount_amount' => $appliedDiscount,
                    'total_amount' => max(0, round($subtotal - $appliedDiscount, 2)),
                ]);
            }
        });
    }

    public function createInvoiceFromOrder(Order $order, int $actorUserId): PosCashierInvoice
    {
        if ($order->pos_cashier_invoice_id) {
            $existing = PosCashierInvoice::withoutGlobalScopes()->whereKey($order->pos_cashier_invoice_id)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($order->source !== 'pos' && $order->source !== 'qr_menu') {
            throw new RuntimeException('هذه العملية متاحة لطلبات الكاشير فقط.');
        }

        if ($order->pos_status === 'cancelled') {
            throw new RuntimeException('لا يمكن إصدار فاتورة لطلب ملغي.');
        }

        if ($order->table_session_id) {
            $session = TableSession::query()->whereKey($order->table_session_id)->first();
            if ($session && $session->status === 'open') {
                throw new RuntimeException('لطلبات الطاولات: أغلق الجلسة لإصدار فاتورة نهائية واحدة.');
            }
        }

        return DB::transaction(function () use ($order, $actorUserId): PosCashierInvoice {
            $lockedOrder = Order::query()
                ->with(['items', 'table', 'tableSession'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $this->buildCashierInvoiceFromOrders(
                orders: collect([$lockedOrder]),
                table: $lockedOrder->table,
                session: $lockedOrder->tableSession,
                actorUserId: $actorUserId
            );

            $lockedOrder->update([
                'pos_cashier_invoice_id' => $invoice->id,
                'pos_status' => 'completed',
                'status' => 'completed',
                'fulfillment_status' => 'fulfilled',
            ]);

            return $invoice;
        });
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
    ): Collection {
        $normalized = collect();

        foreach ($items as $item) {
            $menuItem = PosMenuItem::withoutGlobalScopes()
                ->with('category:id,name')
                ->where('workspace_id', $workspaceId)
                ->whereKey((int) ($item['pos_menu_item_id'] ?? 0))
                ->first();

            if (! $menuItem) {
                throw new RuntimeException('أحد أصناف الكاشير غير صالح.');
            }

            if ($requireActive && ! $menuItem->is_active) {
                throw new RuntimeException('أحد أصناف الكاشير غير مفعل.');
            }

            $itemCurrency = (string) ($menuItem->currency ?: 'USD');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) $menuItem->price;
            $lineTotal = round($quantity * $unitPrice, 2);
            $normalized->push([
                'pos_menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'item_type' => $menuItem->item_type ?: ($menuItem->category?->name ?? 'عام'),
                'size_label' => $menuItem->size_label,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $lineTotal,
                'currency' => $itemCurrency,
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
                'variant_name' => $item['size_label'],
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

    /**
     * @param  Collection<int,Order>  $orders
     */
    private function buildCashierInvoiceFromOrders(
        Collection $orders,
        ?DiningTable $table,
        ?TableSession $session,
        int $actorUserId
    ): PosCashierInvoice {
        if ($orders->isEmpty()) {
            throw new RuntimeException('لا توجد طلبات لإنشاء الفاتورة.');
        }

        $workspaceId = (int) $orders->first()->workspace_id;
        $currency = $this->resolveCurrencyFromOrders($orders);
        $subtotal = round((float) $orders->sum(fn (Order $order) => (float) $order->subtotal), 2);
        $discount = round((float) $orders->sum(fn (Order $order) => (float) $order->discount_amount), 2);
        $total = round((float) $orders->sum(fn (Order $order) => (float) $order->total_amount), 2);

        $invoice = PosCashierInvoice::query()->create([
            'workspace_id' => $workspaceId,
            'dining_table_id' => $table?->id,
            'table_session_id' => $session?->id,
            'closed_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'invoice_number' => $this->nextCashierInvoiceNumber(),
            'status' => 'closed',
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'closed_at' => now(),
            'metadata' => [
                'orders_count' => $orders->count(),
                'orders' => $orders->pluck('order_number')->values()->all(),
            ],
        ]);

        $itemGroups = OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id')->all())
            ->get()
            ->groupBy(fn (OrderItem $item): string => implode('|', [
                (string) $item->pos_menu_item_id,
                (string) $item->product_name,
                (string) $item->item_type,
                (string) $item->variant_name,
                (string) $item->unit_price,
            ]));

        foreach ($itemGroups as $group) {
            $first = $group->first();
            if (! $first) {
                continue;
            }

            $quantity = (int) $group->sum('quantity');
            $discountAmount = round((float) $group->sum('discount_amount'), 2);
            $lineTotal = round((float) $group->sum('total_amount'), 2);

            $invoice->items()->create([
                'workspace_id' => $workspaceId,
                'pos_cashier_invoice_id' => $invoice->id,
                'pos_menu_item_id' => $first->pos_menu_item_id,
                'item_name' => $first->product_name,
                'item_type' => $first->item_type,
                'size_label' => $first->variant_name,
                'quantity' => $quantity,
                'unit_price' => $first->unit_price,
                'discount_amount' => $discountAmount,
                'total_amount' => $lineTotal,
            ]);
        }

        return $invoice;
    }

    private function nextOrderNumber(): string
    {
        $lastId = (Order::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'POS-'.str_pad((string) $lastId, 8, '0', STR_PAD_LEFT);
    }

    private function nextCashierInvoiceNumber(): string
    {
        $lastId = (PosCashierInvoice::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'CASH-'.str_pad((string) $lastId, 8, '0', STR_PAD_LEFT);
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
            // customers.phone is NOT NULL + unique per workspace; synthesize a stable walk-in key.
            'phone' => $phone !== '' ? $phone : 'walkin-'.strtolower((string) str()->ulid()),
            'orders_count' => 0,
            'total_purchases' => 0,
        ]);

        return $customer->id;
    }

    /**
     * @param Collection<int,array<string,mixed>> $items
     */
    private function resolveOrderCurrency(Collection $items): string
    {
        $currencies = $items
            ->pluck('currency')
            ->filter(fn ($currency) => is_string($currency) && $currency !== '')
            ->map(fn ($currency) => strtoupper((string) $currency))
            ->unique()
            ->values();

        if ($currencies->count() === 0) {
            return 'USD';
        }

        return $currencies->count() === 1 ? (string) $currencies->first() : 'MIX';
    }

    /**
     * @param Collection<int,Order> $orders
     */
    private function resolveCurrencyFromOrders(Collection $orders): string
    {
        $currencies = $orders
            ->pluck('currency')
            ->filter(fn ($currency) => is_string($currency) && $currency !== '')
            ->map(fn ($currency) => strtoupper((string) $currency))
            ->unique()
            ->values();

        if ($currencies->count() === 0) {
            return 'USD';
        }

        return $currencies->count() === 1 ? (string) $currencies->first() : 'MIX';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function normalizePaymentMethod(array $payload): string
    {
        $method = (string) ($payload['payment_method'] ?? ((bool) ($payload['pay_now'] ?? false) ? 'pay_now' : 'pay_later'));

        return $method === 'pay_now' ? 'pay_now' : 'pay_later';
    }

    private function refreshTableStatus(DiningTable $table, ?int $ignoredSessionId = null): void
    {
        $hasOpenSessions = $table->sessions()
            ->where('status', 'open')
            ->when($ignoredSessionId, fn ($query) => $query->where('id', '!=', $ignoredSessionId))
            ->exists();

        $table->update(['status' => $hasOpenSessions ? 'occupied' : 'available']);
    }
}
