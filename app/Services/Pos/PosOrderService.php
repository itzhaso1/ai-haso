<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosCashierInvoice;
use App\Models\PosCustomerSession;
use App\Models\PosMenuItem;
use App\Models\TableSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createPosOrder(Workspace $workspace, array $payload, ?User $actor): Order
    {
        return DB::transaction(function () use ($workspace, $payload, $actor): Order {
            if ($existing = $this->findIdempotentOrder($workspace->id, $payload['client_reference'] ?? null)) {
                return $existing;
            }

            $orderType = $this->resolveOrderType($payload, isset($payload['dining_table_id']));
            $diningTableId = in_array($orderType, [Order::ORDER_TYPE_TAKEAWAY, Order::ORDER_TYPE_DELIVERY], true)
                ? null
                : ($payload['dining_table_id'] ?? null);

            [$table, $session] = $this->resolveTableAndSession($workspace->id, $diningTableId);
            if ($table) {
                $orderType = Order::ORDER_TYPE_TABLE;
            }

            $items = $this->normalizePosItems(
                workspaceId: $workspace->id,
                items: $payload['items'] ?? [],
                requireActive: true
            );

            $discountAmount = $this->resolveDiscountAmount($payload, (float) $items->sum('total_amount'));

            $metadata = [
                'channel' => 'cashier',
                'created_by_user_id' => $actor?->id,
                'created_by_name' => $actor?->name,
                'payment_method' => 'cashier',
                'order_type' => $orderType,
            ];

            if ($table && $session) {
                $order = $this->mergeOrCreateSessionOrder(
                    workspace: $workspace,
                    table: $table,
                    session: $session,
                    items: $items,
                    customerId: isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
                    source: 'pos',
                    discountAmount: $discountAmount,
                    notes: $payload['notes'] ?? null,
                    metadata: $metadata,
                    orderType: $orderType,
                    clientReference: $this->normalizeClientReference($payload['client_reference'] ?? null),
                );
            } else {
                $order = $this->createOrderWithSnapshots(
                    workspace: $workspace,
                    customerId: isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
                    source: 'pos',
                    items: $items,
                    table: $table,
                    session: $session,
                    discountAmount: $discountAmount,
                    notes: $payload['notes'] ?? null,
                    metadata: $metadata,
                    currency: $this->resolveOrderCurrency($items),
                    orderType: $orderType,
                    clientReference: $this->normalizeClientReference($payload['client_reference'] ?? null),
                );

                event(new \App\Events\OrderCreated($order));
            }

            $this->auditPosAction('pos.order.created', $order, $actor, [
                'order_type' => $orderType,
                'source' => 'pos',
            ]);

            return $order->fresh(['items', 'customer', 'table', 'tableSession']);
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createQrMenuOrder(Workspace $workspace, ?DiningTable $table, array $payload, ?PosCustomerSession $guestSession = null): Order
    {
        return DB::transaction(function () use ($workspace, $table, $payload, $guestSession): Order {
            if ($existing = $this->findIdempotentOrder($workspace->id, $payload['client_reference'] ?? null)) {
                return $existing;
            }

            $session = null;
            if ($table) {
                if (! $guestSession) {
                    throw new RuntimeException('انتهت جلسة هذه الطاولة. يرجى بدء جلسة جديدة.');
                }

                if ((int) $guestSession->workspace_id !== (int) $workspace->id
                    || (int) $guestSession->dining_table_id !== (int) $table->id
                    || ! $guestSession->isActive()
                ) {
                    throw new RuntimeException('انتهت جلسة هذه الطاولة. يرجى بدء جلسة جديدة.');
                }

                $session = TableSession::withoutGlobalScopes()
                    ->whereKey($guestSession->table_session_id)
                    ->lockForUpdate()
                    ->first();

                if (! $session || $session->status !== 'open' || (int) $session->dining_table_id !== (int) $table->id) {
                    throw new RuntimeException('انتهت جلسة هذه الطاولة. يرجى بدء جلسة جديدة.');
                }
            }

            $customerId = $this->resolveWalkInCustomerId($workspace, $payload);
            $items = $this->normalizePosItems(
                workspaceId: $workspace->id,
                items: $payload['items'] ?? [],
                requireActive: true
            );

            $orderType = $table ? Order::ORDER_TYPE_TABLE : Order::ORDER_TYPE_TAKEAWAY;
            $metadata = [
                'channel' => 'qr_menu',
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'payment_method' => $this->normalizePaymentMethod($payload),
                'order_type' => $orderType,
            ];
            if ($guestSession) {
                $metadata['pos_customer_session_id'] = $guestSession->id;
            }

            $clientReference = $this->normalizeClientReference($payload['client_reference'] ?? null);

            if ($table && $session) {
                $order = $this->mergeOrCreateSessionOrder(
                    workspace: $workspace,
                    table: $table,
                    session: $session,
                    items: $items,
                    customerId: $customerId,
                    source: 'qr_menu',
                    discountAmount: 0,
                    notes: $payload['notes'] ?? null,
                    metadata: $metadata,
                    orderType: $orderType,
                    clientReference: $clientReference,
                );
            } else {
                $order = $this->createOrderWithSnapshots(
                    workspace: $workspace,
                    customerId: $customerId,
                    source: 'qr_menu',
                    items: $items,
                    table: $table,
                    session: $session,
                    discountAmount: 0,
                    notes: $payload['notes'] ?? null,
                    metadata: $metadata,
                    currency: $this->resolveOrderCurrency($items),
                    orderType: $orderType,
                    clientReference: $clientReference,
                );

                event(new \App\Events\OrderCreated($order));
            }

            $fresh = $order->fresh(['items', 'customer', 'table', 'tableSession']);
            event(new \App\Events\NewMenuOrderCreated($fresh));

            return $fresh;
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
            $cancelled = $this->orderService->cancel($order->load('items'), $actor);
            if ($cancelled->dining_table_id) {
                $table = DiningTable::withoutGlobalScopes()->find($cancelled->dining_table_id);
                if ($table) {
                    $this->refreshTableStatus($table);
                }
            }

            $fresh = $cancelled->fresh(['items', 'customer', 'table', 'tableSession']);
            event(new \App\Events\OrderCancelled($fresh));
            $this->auditPosAction('pos.order.cancelled', $fresh, $actor);

            return $fresh;
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

        $fresh = $order->fresh(['items', 'customer', 'table', 'tableSession']);
        event(new \App\Events\OrderUpdated($fresh));

        return $fresh;
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
                $cancelled = $this->orderService->cancel($order->fresh(['items']), null);
                if ($cancelled->dining_table_id) {
                    $table = DiningTable::withoutGlobalScopes()->find($cancelled->dining_table_id);
                    if ($table) {
                        $this->refreshTableStatus($table);
                    }
                }

                return $cancelled->fresh(['items', 'table', 'tableSession', 'customer']);
            }

            $subtotal = (float) OrderItem::query()->where('order_id', $order->id)->sum('total_amount');
            $discountAmount = max(0, (float) ($payload['discount_amount'] ?? $order->discount_amount));
            if (isset($payload['discount_percent'])) {
                $discountAmount = $this->percentToAmount((float) $payload['discount_percent'], $subtotal);
            }
            $taxAmount = $this->calculateTaxAmount(
                Workspace::withoutGlobalScopes()->find($order->workspace_id),
                $subtotal,
                $discountAmount
            );
            $order->update([
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => max(0, round($subtotal - $discountAmount + $taxAmount, 2)),
            ]);

            $fresh = $order->fresh(['items', 'table', 'tableSession', 'customer']);
            event(new \App\Events\OrderUpdated($fresh));
            $this->auditPosAction('pos.order.items_updated', $fresh, null);

            return $fresh;
        });
    }

    public function openSession(DiningTable $table): TableSession
    {
        $wasNew = ! TableSession::query()
            ->where('dining_table_id', $table->id)
            ->where('status', 'open')
            ->exists();

        $session = $this->ensureOpenSession($table);
        $this->refreshTableStatus($table);

        if ($wasNew || $session->wasRecentlyCreated) {
            event(new \App\Events\TableSessionOpened($session));
        }

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

            app(TableGuestSessionService::class)->revokeForTableSession($session);

            $table = $session->table;
            if ($table) {
                $this->refreshTableStatus($table, $session->id);
                event(new \App\Events\TableUpdated($table->fresh()));
            }

            event(new \App\Events\TableSessionClosed($session->fresh()));
            $this->auditPosAction('pos.table_session.closed', $session, User::query()->find($actorUserId), [
                'invoice_id' => $invoice?->id,
                'orders_count' => $orders->count(),
            ]);

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

            app(TableGuestSessionService::class)->revokeForTableSession($session);

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

    /**
     * Move an open session (and all its orders) to another table.
     * If the target already has an open session, orders are attached to that session.
     */
    public function transferSession(TableSession $session, DiningTable $targetTable): void
    {
        if ($session->status !== 'open') {
            throw new RuntimeException('يمكن نقل الجلسات المفتوحة فقط.');
        }

        if ((int) $session->workspace_id !== (int) $targetTable->workspace_id) {
            throw new RuntimeException('الطاولة الهدف خارج نطاق مساحة العمل.');
        }

        if ((int) $session->dining_table_id === (int) $targetTable->id) {
            throw new RuntimeException('الطاولة الهدف هي نفسها الطاولة الحالية.');
        }

        DB::transaction(function () use ($session, $targetTable): void {
            $sourceTable = $session->table;
            $targetSession = $this->ensureOpenSession($targetTable);

            if ((int) $targetSession->id === (int) $session->id) {
                return;
            }

            Order::query()
                ->where('table_session_id', $session->id)
                ->update([
                    'dining_table_id' => $targetTable->id,
                    'table_session_id' => $targetSession->id,
                ]);

            PosCustomerSession::withoutGlobalScopes()
                ->where('table_session_id', $session->id)
                ->update([
                    'dining_table_id' => $targetTable->id,
                    'table_session_id' => $targetSession->id,
                ]);

            $session->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $targetTable->update(['status' => 'occupied']);
            event(new \App\Events\TableUpdated($targetTable->fresh()));
            event(new \App\Events\TableSessionClosed($session->fresh()));

            if ($sourceTable) {
                $this->refreshTableStatus($sourceTable, $session->id);
                event(new \App\Events\TableUpdated($sourceTable->fresh()));
            }

            $this->auditPosAction('pos.table_session.transferred', $targetSession, null, [
                'from_table_id' => $sourceTable?->id,
                'to_table_id' => $targetTable->id,
                'from_session_id' => $session->id,
                'to_session_id' => $targetSession->id,
            ]);
        });
    }

    /**
     * Merge source table open session into the target table open session.
     */
    public function mergeSessions(TableSession $sourceSession, DiningTable $targetTable): void
    {
        if ($sourceSession->status !== 'open') {
            throw new RuntimeException('يمكن دمج الجلسات المفتوحة فقط.');
        }

        if ((int) $sourceSession->workspace_id !== (int) $targetTable->workspace_id) {
            throw new RuntimeException('الطاولة الهدف خارج نطاق مساحة العمل.');
        }

        if ((int) $sourceSession->dining_table_id === (int) $targetTable->id) {
            throw new RuntimeException('لا يمكن دمج الطاولة مع نفسها.');
        }

        DB::transaction(function () use ($sourceSession, $targetTable): void {
            $sourceTable = $sourceSession->table;
            $targetSession = $this->ensureOpenSession($targetTable);

            Order::query()
                ->where('table_session_id', $sourceSession->id)
                ->update([
                    'dining_table_id' => $targetTable->id,
                    'table_session_id' => $targetSession->id,
                ]);

            PosCustomerSession::withoutGlobalScopes()
                ->where('table_session_id', $sourceSession->id)
                ->update([
                    'dining_table_id' => $targetTable->id,
                    'table_session_id' => $targetSession->id,
                ]);

            $sourceSession->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $targetTable->update(['status' => 'occupied']);
            event(new \App\Events\TableUpdated($targetTable->fresh()));
            event(new \App\Events\TableSessionClosed($sourceSession->fresh()));

            if ($sourceTable) {
                $this->refreshTableStatus($sourceTable, $sourceSession->id);
                event(new \App\Events\TableUpdated($sourceTable->fresh()));
            }

            $this->auditPosAction('pos.table_session.merged', $targetSession, null, [
                'from_table_id' => $sourceTable?->id,
                'to_table_id' => $targetTable->id,
                'from_session_id' => $sourceSession->id,
                'to_session_id' => $targetSession->id,
            ]);
        });
    }

    /**
     * Split open session items into multiple guest checks (orders) within the same session.
     * Every billable line quantity must be fully allocated across the groups.
     *
     * @param  array<int,array{items:array<int,array{order_item_id:int|string,quantity:int|string}>}>  $groups
     * @return Collection<int,Order>
     */
    public function splitSessionByItems(TableSession $session, array $groups, ?User $actor): Collection
    {
        if ($session->status !== 'open') {
            throw new RuntimeException('يمكن تقسيم الحساب للجلسات المفتوحة فقط.');
        }

        if (count($groups) < 2) {
            throw new RuntimeException('يجب إنشاء حسابين على الأقل لتقسيم الفاتورة.');
        }

        return DB::transaction(function () use ($session, $groups, $actor): Collection {
            $orders = Order::query()
                ->where('table_session_id', $session->id)
                ->whereIn('source', ['pos', 'qr_menu'])
                ->where('pos_status', '!=', 'cancelled')
                ->whereNull('pos_cashier_invoice_id')
                ->with('items')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw new RuntimeException('لا توجد طلبات قابلة للتقسيم في هذه الجلسة.');
            }

            $itemsById = $orders->flatMap->items->keyBy('id');
            $remaining = $itemsById->mapWithKeys(fn (OrderItem $item) => [$item->id => (int) $item->quantity]);

            $normalizedGroups = [];
            foreach ($groups as $groupIndex => $group) {
                $lines = collect($group['items'] ?? [])
                    ->filter(fn ($line) => (int) ($line['quantity'] ?? 0) > 0)
                    ->values();

                if ($lines->isEmpty()) {
                    throw new RuntimeException('كل حساب يجب أن يحتوي على صنف واحد على الأقل.');
                }

                $built = [];
                foreach ($lines as $line) {
                    $itemId = (int) ($line['order_item_id'] ?? 0);
                    $qty = (int) ($line['quantity'] ?? 0);
                    $sourceItem = $itemsById->get($itemId);
                    if (! $sourceItem) {
                        throw new RuntimeException('أحد أصناف التقسيم غير صالح.');
                    }
                    $left = (int) ($remaining[$itemId] ?? 0);
                    if ($qty > $left) {
                        throw new RuntimeException('كمية التقسيم أكبر من المتاح للصنف: '.$sourceItem->product_name);
                    }
                    $remaining[$itemId] = $left - $qty;
                    $unit = (float) $sourceItem->unit_price;
                    $built[] = [
                        'source' => $sourceItem,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'total_amount' => round($qty * $unit, 2),
                    ];
                }
                $normalizedGroups[$groupIndex] = $built;
            }

            $leftover = collect($remaining)->sum();
            if ($leftover > 0) {
                throw new RuntimeException('يجب توزيع كل أصناف الطاولة على الحسابات. الإجمالي المتبقي غير موزع.');
            }

            $workspace = Workspace::query()->findOrFail($session->workspace_id);
            $table = $session->table;
            $created = collect();

            foreach ($normalizedGroups as $builtLines) {
                $collection = collect($builtLines)->map(fn (array $line) => [
                    'pos_menu_item_id' => $line['source']->pos_menu_item_id,
                    'product_id' => $line['source']->product_id,
                    'name' => $line['source']->product_name,
                    'item_type' => $line['source']->item_type,
                    'size_label' => $line['source']->variant_name,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total_amount' => $line['total_amount'],
                    'currency' => 'USD',
                ]);

                // Prefer currency from source order when available.
                $firstSourceOrder = $orders->firstWhere('id', $builtLines[0]['source']->order_id);
                $currency = $firstSourceOrder?->currency ?: 'USD';

                $order = $this->createOrderWithSnapshots(
                    workspace: $workspace,
                    customerId: $firstSourceOrder?->customer_id,
                    source: 'pos',
                    items: $collection->map(function (array $row) use ($currency) {
                        $row['currency'] = $currency;

                        return $row;
                    }),
                    table: $table,
                    session: $session,
                    discountAmount: 0,
                    notes: null,
                    metadata: [
                        'channel' => 'cashier',
                        'created_by_user_id' => $actor?->id,
                        'created_by_name' => $actor?->name,
                        'payment_method' => 'cashier',
                        'split_from_session' => true,
                    ],
                    currency: $currency,
                    syncInventory: false,
                );

                $created->push($order);
            }

            // Original orders become empty shells → cancel them (items already reallocated).
            foreach ($orders as $order) {
                OrderItem::query()->where('order_id', $order->id)->delete();
                $this->orderService->cancel($order->fresh('items'), $actor);
            }

            return $created;
        });
    }

    public function applySessionNote(TableSession $session, string $note): void
    {
        if ($session->status !== 'open') {
            throw new RuntimeException('يمكن إضافة ملاحظة للجلسات المفتوحة فقط.');
        }

        $note = trim($note);
        if ($note === '') {
            throw new RuntimeException('الملاحظة فارغة.');
        }

        $order = Order::query()
            ->where('table_session_id', $session->id)
            ->whereIn('source', ['pos', 'qr_menu'])
            ->where('pos_status', '!=', 'cancelled')
            ->latest('id')
            ->first();

        if (! $order) {
            throw new RuntimeException('لا يوجد طلب لإضافة الملاحظة عليه. أضف طلبًا أولًا.');
        }

        $metadata = (array) ($order->metadata ?? []);
        $metadata['session_note'] = $note;
        $order->update([
            'notes' => $note,
            'metadata' => $metadata,
        ]);
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
        return DB::transaction(function () use ($table): TableSession {
            DiningTable::withoutGlobalScopes()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            $session = TableSession::query()
                ->where('dining_table_id', $table->id)
                ->where('status', 'open')
                ->lockForUpdate()
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
        });
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
            // Inventory sync only when PosMenuItem.product_id is set; otherwise skip.
            $productId = $menuItem->product_id ? (int) $menuItem->product_id : null;
            $normalized->push([
                'pos_menu_item_id' => $menuItem->id,
                'product_id' => $productId,
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

        return [$table, $session];
    }

    /**
     * Merge matching lines into the current table session; create a new order only for leftovers.
     *
     * @param  Collection<int,array<string,mixed>>  $items
     * @param  array<string,mixed>|null  $metadata
     */
    private function mergeOrCreateSessionOrder(
        Workspace $workspace,
        DiningTable $table,
        TableSession $session,
        Collection $items,
        ?int $customerId,
        string $source,
        float $discountAmount,
        ?string $notes,
        ?array $metadata,
        string $orderType = Order::ORDER_TYPE_TABLE,
        ?string $clientReference = null,
    ): Order {
        $remaining = collect();
        $lastTouched = null;

        foreach ($items as $item) {
            $existingLine = $this->findMergeableSessionLine($session, $item);
            if ($existingLine) {
                $this->increaseOrderItemQuantity($existingLine, (int) $item['quantity'], $workspace);
                $lastTouched = $existingLine->order()->with(['items', 'customer', 'table', 'tableSession'])->first();
                continue;
            }

            $remaining->push($item);
        }

        if ($remaining->isNotEmpty()) {
            $lastTouched = $this->createOrderWithSnapshots(
                workspace: $workspace,
                customerId: $customerId,
                source: $source,
                items: $remaining,
                table: $table,
                session: $session,
                discountAmount: $discountAmount,
                notes: $notes,
                metadata: $metadata,
                currency: $this->resolveOrderCurrency($remaining),
                orderType: $orderType,
                clientReference: $clientReference,
            );

            event(new \App\Events\OrderCreated($lastTouched));
        } elseif ($notes && $lastTouched && blank($lastTouched->notes)) {
            $lastTouched->update(['notes' => $notes]);
        }

        if (! $lastTouched) {
            throw new RuntimeException('لا يمكن إنشاء طلب بدون عناصر.');
        }

        // Persist client reference for idempotency even when only quantities were merged.
        if ($clientReference && blank($lastTouched->client_reference)) {
            $lastTouched->update(['client_reference' => $clientReference]);
        }

        $table->update(['status' => 'occupied']);
        event(new \App\Events\TableUpdated($table->fresh()));

        return $lastTouched->fresh(['items', 'customer', 'table', 'tableSession']);
    }

    /**
     * @param  array<string,mixed>  $item
     */
    private function findMergeableSessionLine(TableSession $session, array $item): ?OrderItem
    {
        $variant = $item['size_label'] ?? null;

        return OrderItem::query()
            ->whereHas('order', function ($query) use ($session): void {
                $query->where('table_session_id', $session->id)
                    ->whereNotIn('pos_status', ['cancelled', 'completed'])
                    ->whereNull('pos_cashier_invoice_id');
            })
            ->where('pos_menu_item_id', (int) $item['pos_menu_item_id'])
            ->where('unit_price', (float) $item['unit_price'])
            ->when(
                filled($variant),
                fn ($query) => $query->where('variant_name', $variant),
                fn ($query) => $query->where(function ($inner): void {
                    $inner->whereNull('variant_name')->orWhere('variant_name', '');
                })
            )
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    private function increaseOrderItemQuantity(OrderItem $line, int $quantityDelta, ?Workspace $workspace = null): void
    {
        $quantityDelta = max(1, $quantityDelta);
        $newQuantity = (int) $line->quantity + $quantityDelta;
        $unitPrice = (float) $line->unit_price;
        $discount = (float) $line->discount_amount;
        $lineTotal = max(0, round(($newQuantity * $unitPrice) - $discount, 2));

        $line->update([
            'quantity' => $newQuantity,
            'total_amount' => $lineTotal,
        ]);

        $deltaItem = $line->replicate();
        $deltaItem->quantity = $quantityDelta;
        $deltaItem->total_amount = round($quantityDelta * $unitPrice, 2);
        $this->syncInventoryForPosLine($deltaItem);

        $order = Order::withoutGlobalScopes()->whereKey($line->order_id)->lockForUpdate()->firstOrFail();
        $subtotal = (float) OrderItem::query()->where('order_id', $order->id)->sum('total_amount');
        $workspace ??= Workspace::withoutGlobalScopes()->find($order->workspace_id);
        $taxAmount = $this->calculateTaxAmount($workspace, $subtotal, (float) $order->discount_amount);
        $order->update([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => max(0, round($subtotal - (float) $order->discount_amount + $taxAmount, 2)),
        ]);
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
        string $currency,
        bool $syncInventory = true,
        string $orderType = Order::ORDER_TYPE_TAKEAWAY,
        ?string $clientReference = null,
    ): Order {
        $subtotal = round((float) $items->sum('total_amount'), 2);
        $discountAmount = max(0, round($discountAmount, 2));
        $taxAmount = $this->calculateTaxAmount($workspace, $subtotal, $discountAmount);
        $total = max(0, round($subtotal - $discountAmount + $taxAmount, 2));

        $order = Order::query()->create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customerId,
            'dining_table_id' => $table?->id,
            'table_session_id' => $session?->id,
            'order_number' => $this->nextOrderNumber(),
            'client_reference' => $clientReference,
            'source' => $source,
            'order_type' => $orderType,
            'status' => 'confirmed',
            'pos_status' => 'new',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'shipping_status' => 'not_shipped',
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'shipping_amount' => 0,
            'total_amount' => $total,
            'notes' => $notes,
            'metadata' => $metadata,
            'placed_at' => now(),
        ]);

        foreach ($items as $item) {
            $orderItem = $order->items()->create([
                'workspace_id' => $workspace->id,
                'order_id' => $order->id,
                'product_id' => $item['product_id'] ?? null,
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

            if ($syncInventory) {
                $this->syncInventoryForPosLine($orderItem, $actorUserId = null);
            }
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
        $openSessionIds = $table->sessions()
            ->where('status', 'open')
            ->when($ignoredSessionId, fn ($query) => $query->where('id', '!=', $ignoredSessionId))
            ->pluck('id');

        $hasBillableOrders = $openSessionIds->isNotEmpty()
            && Order::withoutGlobalScopes()
                ->whereIn('table_session_id', $openSessionIds)
                ->where('pos_status', '!=', 'cancelled')
                ->exists();

        if ($hasBillableOrders) {
            $table->update(['status' => 'occupied']);

            return;
        }

        // Preserve manually set operational statuses.
        if (in_array($table->status, ['reserved', 'cleaning', 'closed'], true)) {
            return;
        }

        $table->update(['status' => 'available']);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveOrderType(array $payload, bool $hasTableHint): string
    {
        $type = strtolower((string) ($payload['order_type'] ?? ''));
        if (in_array($type, [Order::ORDER_TYPE_TABLE, Order::ORDER_TYPE_TAKEAWAY, Order::ORDER_TYPE_DELIVERY], true)) {
            return $type;
        }

        return $hasTableHint ? Order::ORDER_TYPE_TABLE : Order::ORDER_TYPE_TAKEAWAY;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveDiscountAmount(array $payload, float $subtotal): float
    {
        if (isset($payload['discount_percent']) && is_numeric($payload['discount_percent'])) {
            return $this->percentToAmount((float) $payload['discount_percent'], $subtotal);
        }

        return max(0, round((float) ($payload['discount_amount'] ?? 0), 2));
    }

    private function percentToAmount(float $percent, float $subtotal): float
    {
        $percent = max(0, min(100, $percent));

        return max(0, round($subtotal * ($percent / 100), 2));
    }

    private function calculateTaxAmount(?Workspace $workspace, float $subtotal, float $discountAmount): float
    {
        if (! $workspace) {
            return 0.0;
        }

        $rate = (float) data_get($workspace->settings ?? [], 'pos.tax_rate', 0);
        if ($rate <= 0) {
            return 0.0;
        }

        $taxable = max(0, $subtotal - $discountAmount);

        return max(0, round($taxable * ($rate / 100), 2));
    }

    private function normalizeClientReference(mixed $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $reference = trim($reference);

        return $reference !== '' ? mb_substr($reference, 0, 120) : null;
    }

    private function findIdempotentOrder(int $workspaceId, mixed $reference): ?Order
    {
        $clientReference = $this->normalizeClientReference($reference);
        if (! $clientReference) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->with(['items', 'customer', 'table', 'tableSession'])
            ->where('workspace_id', $workspaceId)
            ->where('client_reference', $clientReference)
            ->first();
    }

    private function auditPosAction(string $action, mixed $entity, ?User $actor = null, ?array $meta = null): void
    {
        try {
            app(AuditLogService::class)->log(
                action: $action,
                entityType: is_object($entity) ? $entity::class : 'pos',
                entityId: is_object($entity) && isset($entity->id) ? (int) $entity->id : null,
                actor: $actor,
                meta: $meta,
                workspaceId: is_object($entity) && isset($entity->workspace_id) ? (int) $entity->workspace_id : null,
            );
        } catch (\Throwable $exception) {
            Log::warning('POS audit log failed', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Deduct inventory when the POS menu item is linked to a catalog product.
     * Menu items without product_id are intentionally skipped (no inventory side effects).
     */
    private function syncInventoryForPosLine(OrderItem $orderItem, ?int $actorUserId = null): void
    {
        if (! $orderItem->product_id) {
            return;
        }

        try {
            $actor = $actorUserId ? User::query()->find($actorUserId) : null;
            $this->inventoryService->adjustStock(
                productId: (int) $orderItem->product_id,
                variantId: $orderItem->product_variant_id ? (int) $orderItem->product_variant_id : null,
                type: 'remove',
                quantity: max(1, (int) $orderItem->quantity),
                actor: $actor,
                referenceType: Order::class,
                referenceId: (int) $orderItem->order_id,
                notes: 'POS order inventory sync',
            );
        } catch (\Throwable $exception) {
            // Stub-safe: log and continue so cashier flow is not broken by inventory edge cases.
            Log::warning('pos.inventory_sync_skipped', [
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
