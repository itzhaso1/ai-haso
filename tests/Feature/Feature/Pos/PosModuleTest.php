<?php

namespace Tests\Feature\Feature\Pos;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_qr_order_flow_creates_order_and_session_with_price_snapshot(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $product = Product::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Cappuccino',
            'slug' => 'cappuccino',
            'sku' => 'CAP-001',
            'price' => 4.00,
            'currency' => 'USD',
            'stock' => 20,
            'status' => 'active',
            'show_in_menu' => true,
            'allow_online_ordering' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.tables.store'), [
                'name' => 'Table 3',
            ])->assertRedirect();

        $table = DiningTable::query()->where('name', 'Table 3')->firstOrFail();

        $this->post(route('menu.table.order', ['workspace' => $workspace->slug, 'token' => $table->qr_token]), [
            'customer_name' => 'Walk In',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        $order = Order::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('source', 'qr_menu')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($table->id, (int) $order->dining_table_id);
        $this->assertSame('new', $order->pos_status);
        $this->assertNotNull($order->table_session_id);

        $orderItem = OrderItem::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(4.0, (float) $orderItem->unit_price);
        $this->assertSame(8.0, (float) $orderItem->total_amount);

        $this->assertDatabaseHas('table_sessions', [
            'workspace_id' => $workspace->id,
            'dining_table_id' => $table->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('dining_tables', [
            'id' => $table->id,
            'status' => 'occupied',
        ]);

        $product->update(['price' => 11.00]);
        $this->assertSame(4.0, (float) $orderItem->fresh()->unit_price);
    }

    public function test_pos_cashier_order_can_update_status_and_generate_finance_invoice(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $table = DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Table 1',
            'status' => 'available',
            'qr_token' => 'table_1_token_for_test',
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Latte',
            'slug' => 'latte',
            'sku' => 'LAT-001',
            'price' => 5.00,
            'currency' => 'USD',
            'stock' => 30,
            'status' => 'active',
            'show_in_menu' => true,
            'allow_online_ordering' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.store'), [
                'dining_table_id' => $table->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])->assertRedirect();

        $order = Order::query()->where('source', 'pos')->latest('id')->firstOrFail();
        $this->assertNotNull($order->table_session_id);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.status', $order), [
                'pos_status' => 'preparing',
            ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'pos_status' => 'preparing',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.status', $order), [
                'pos_status' => 'completed',
            ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'pos_status' => 'completed',
            'status' => 'completed',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.invoice', $order))
            ->assertRedirect();

        $this->assertNotNull($order->fresh()->finance_invoice_id);
    }

    public function test_workspace_isolation_blocks_cross_workspace_pos_access(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('store');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        $tableB = DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Table B',
            'status' => 'available',
            'qr_token' => 'token_b_table',
        ]);

        $productB = Product::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'sku' => 'HID-001',
            'price' => 9.00,
            'currency' => 'USD',
            'stock' => 10,
            'status' => 'active',
            'show_in_menu' => true,
            'allow_online_ordering' => true,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get(route('workspace.pos.tables.show', $tableB))
            ->assertNotFound();

        $response = $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.pos.orders.store'), [
                'items' => [
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('table_sessions', 0);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
