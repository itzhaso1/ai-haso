<?php

namespace Tests\Feature\Feature\Pos;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosCashierInvoice;
use App\Models\PosMenuItem;
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

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Cappuccino',
            'item_type' => 'مشروبات',
            'price' => 4.00,
            'currency' => 'USD',
            'is_active' => true,
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
                ['pos_menu_item_id' => $item->id, 'quantity' => 2],
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

        $item->update(['price' => 11.00]);
        $this->assertSame(4.0, (float) $orderItem->fresh()->unit_price);
    }

    public function test_pos_cashier_order_can_update_status_and_generate_cashier_invoice(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $table = DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Table 1',
            'status' => 'available',
            'qr_token' => 'table_1_token_for_test',
        ]);

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Latte',
            'item_type' => 'مشروبات',
            'price' => 5.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.store'), [
                'dining_table_id' => $table->id,
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 2],
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

        $cashierInvoiceId = $order->fresh()->pos_cashier_invoice_id;
        $this->assertNotNull($cashierInvoiceId);
        $this->assertDatabaseHas('pos_cashier_invoices', [
            'id' => $cashierInvoiceId,
            'workspace_id' => $workspace->id,
            'dining_table_id' => $table->id,
        ]);
    }

    public function test_cashier_can_create_single_order_with_mixed_item_currencies(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $usdItem = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Espresso',
            'item_type' => 'مشروبات',
            'price' => 3.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $eurItem = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Cookie',
            'item_type' => 'حلويات',
            'price' => 2.50,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.store'), [
                'items' => [
                    ['pos_menu_item_id' => $usdItem->id, 'quantity' => 1],
                    ['pos_menu_item_id' => $eurItem->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $order = Order::query()
            ->where('workspace_id', $workspace->id)
            ->where('source', 'pos')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('MIX', $order->currency);
        $this->assertSame(8.0, (float) $order->subtotal);
        $this->assertSame(8.0, (float) $order->total_amount);
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

        $itemB = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Hidden Product',
            'item_type' => 'عام',
            'price' => 9.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get(route('workspace.pos.tables.show', $tableB))
            ->assertNotFound();

        $response = $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.pos.orders.store'), [
                'items' => [
                    ['pos_menu_item_id' => $itemB->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('items.0.pos_menu_item_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('table_sessions', 0);
    }

    public function test_pos_item_management_reflects_in_public_menu(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.items.store'), [
                'name' => 'Ice Latte',
                'item_type' => 'مشروبات باردة',
                'price' => 7.5,
                'currency' => 'USD',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->get(route('menu.general', ['workspace' => $workspace->slug]))
            ->assertOk()
            ->assertSee('Ice Latte');
    }

    public function test_table_order_items_can_be_edited_and_session_closing_requires_no_running_orders(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $table = DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Table 7',
            'status' => 'available',
            'qr_token' => 'table_7_token_for_test',
        ]);

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Cheesecake',
            'item_type' => 'حلويات',
            'price' => 8.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.store'), [
                'dining_table_id' => $table->id,
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $order = Order::query()->where('source', 'pos')->latest('id')->firstOrFail();
        $line = $order->items()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.update-items', $order), [
                'discount_amount' => 1.00,
                'items' => [
                    ['id' => $line->id, 'quantity' => 3, 'unit_price' => 8.00],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_items', [
            'id' => $line->id,
            'quantity' => 3,
            'total_amount' => 24.00,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'subtotal' => 24.00,
            'discount_amount' => 1.00,
            'total_amount' => 23.00,
        ]);

        $sessionId = (int) $order->table_session_id;
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $sessionId]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.status', $order), [
                'pos_status' => 'delivered',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $sessionId]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice = PosCashierInvoice::query()->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'pos_cashier_invoice_id' => $invoice?->id,
            'pos_status' => 'completed',
        ]);
    }

    public function test_table_view_can_add_new_order_from_table_menu_form(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $table = DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Table 8',
            'status' => 'occupied',
            'qr_token' => 'table_8_token_for_test',
        ]);

        PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Americano',
            'item_type' => 'مشروبات',
            'price' => 4.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $menuItem = PosMenuItem::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'Americano')
            ->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.tables.orders.store', $table), [
                'items' => [
                    ['pos_menu_item_id' => $menuItem->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'workspace_id' => $workspace->id,
            'dining_table_id' => $table->id,
            'source' => 'pos',
        ]);
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
