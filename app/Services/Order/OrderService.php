<?php

namespace App\Services\Order;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $items = $data['items'] ?? [];
            if (! is_array($items) || $items === []) {
                throw new \InvalidArgumentException('Order requires at least one item.');
            }

            $order = Order::query()->create([
                'customer_id' => $data['customer_id'] ?? null,
                'order_number' => $this->nextOrderNumber(),
                'status' => $data['status'] ?? 'confirmed',
                'payment_status' => 'pending',
                'fulfillment_status' => 'unfulfilled',
                'shipping_status' => 'not_shipped',
                'currency' => $data['currency'] ?? 'USD',
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_amount' => $data['shipping_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'placed_at' => now(),
            ]);

            $subtotal = 0.0;

            foreach ($items as $item) {
                $product = Product::query()->whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                $variant = null;
                $unitPrice = (float) ($item['unit_price'] ?? ($product->sale_price ?: $product->price));

                if (! empty($item['product_variant_id'])) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($item['product_variant_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $unitPrice = (float) ($item['unit_price'] ?? ($variant->sale_price ?: $variant->price ?: $unitPrice));
                }

                $quantity = (int) $item['quantity'];
                $lineTotal = max(0, ($unitPrice * $quantity) - (float) ($item['discount_amount'] ?? 0));
                $subtotal += $lineTotal;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total_amount' => $lineTotal,
                ]);

                $this->inventoryService->adjustStock(
                    productId: $product->id,
                    variantId: $variant?->id,
                    type: 'reserve',
                    quantity: $quantity,
                    actor: $actor,
                    referenceType: 'order',
                    referenceId: $order->id,
                    notes: 'Order reservation'
                );
            }

            $total = $subtotal - (float) $order->discount_amount + (float) $order->shipping_amount;
            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => max(0, $total),
            ]);

            if ($order->customer) {
                $order->customer->update([
                    'orders_count' => $order->customer->orders()->count(),
                    'total_purchases' => $order->customer->orders()->sum('total_amount'),
                    'last_order_at' => now(),
                ]);
            }

            event(new OrderCreated($order));

            return $order->fresh(['items', 'customer']);
        });
    }

    public function cancel(Order $order, ?User $actor = null): Order
    {
        if ($order->status === 'cancelled') {
            return $order;
        }

        DB::transaction(function () use ($order, $actor): void {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $this->inventoryService->adjustStock(
                    productId: $item->product_id,
                    variantId: $item->product_variant_id,
                    type: 'release',
                    quantity: (int) $item->quantity,
                    actor: $actor,
                    referenceType: 'order_cancelled',
                    referenceId: $order->id,
                    notes: 'Order cancellation release'
                );
            }

            $order->update([
                'status' => 'cancelled',
                'fulfillment_status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        });

        return $order->fresh(['items', 'customer']);
    }

    private function nextOrderNumber(): string
    {
        $lastId = (Order::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'ORD-'.str_pad((string) $lastId, 8, '0', STR_PAD_LEFT);
    }
}
