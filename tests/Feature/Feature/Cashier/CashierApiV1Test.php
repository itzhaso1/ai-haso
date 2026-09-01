<?php

namespace Tests\Feature\Feature\Cashier;

use App\Models\Order;
use App\Models\PosMenuItem;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_login_bootstrap_catalog_and_takeaway_order(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');
        $item = $this->makeItem($workspace, 12.5);

        $login = $this->postJson('/api/cashier/v1/auth/login', [
            'email_or_phone' => $owner->email,
            'password' => 'password',
            'device_name' => 'كاشير حاسم test',
            'device_type' => 'cashier',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue((bool) $login->json('data.pos_enabled'));
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        $bootstrap = $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->getJson('/api/cashier/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.app.name', 'كاشير حاسم');

        $this->assertTrue((bool) $bootstrap->json('data.pos_enabled'));
        $permissions = $bootstrap->json('data.permissions') ?? [];
        $this->assertTrue((bool) ($permissions['orders.manage'] ?? false));

        $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->getJson('/api/cashier/v1/catalog/items')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $item->id)
            ->assertJsonPath('data.items.0.price', 12.5);

        $clientRef = 'cashier-test-'.uniqid();

        $this->withToken($token)
            ->withHeaders([
                'X-Workspace-Id' => (string) $workspace->id,
                'Idempotency-Key' => $clientRef,
            ])
            ->postJson('/api/cashier/v1/orders', [
                'order_type' => 'takeaway',
                'client_reference' => $clientRef,
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_type', 'takeaway')
            ->assertJsonPath('message', 'تم إنشاء الطلب بنجاح.');

        $order = Order::query()->where('client_reference', $clientRef)->firstOrFail();
        $this->assertNull($order->table_session_id);
        $this->assertEquals(25.0, (float) $order->subtotal);

        $this->withToken($token)
            ->withHeaders([
                'X-Workspace-Id' => (string) $workspace->id,
                'Idempotency-Key' => $clientRef.'-http',
            ])
            ->postJson('/api/cashier/v1/orders', [
                'order_type' => 'takeaway',
                'client_reference' => $clientRef,
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->assertSame(1, Order::query()->where('client_reference', $clientRef)->count());
    }

    public function test_cashier_kitchen_reports_table_store_and_me_permissions(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $login = $this->postJson('/api/cashier/v1/auth/login', [
            'email_or_phone' => $owner->email,
            'password' => 'password',
            'device_name' => 'كاشير حاسم test',
            'device_type' => 'cashier',
        ])->assertOk();

        $token = $login->json('data.token');
        $headers = ['X-Workspace-Id' => (string) $workspace->id];

        $me = $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/auth/me')
            ->assertOk()
            ->json('data');

        $permissions = $me['permissions'] ?? [];
        $this->assertTrue((bool) ($permissions['orders.manage'] ?? false));
        $this->assertTrue((bool) ($me['pos_enabled'] ?? false));

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/kitchen/orders')
            ->assertOk()
            ->assertJsonStructure(['data' => ['orders', 'statuses']]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/reports/daily')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'summary' => [
                    'invoices_count',
                    'orders_count',
                    'paid_orders_count',
                    'unpaid_orders_count',
                ],
                'top_items',
                'quantity_by_type',
                'channel_stats',
                'date',
            ]]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/cashier/v1/tables', ['name' => 'طاولة اختبار API'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'طاولة اختبار API');

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/orders/channel-stats')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stats']]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/orders/recent-menu?after_id=0')
            ->assertOk()
            ->assertJsonStructure(['data' => ['orders', 'latest_id']]);

        $bootstrapSettings = $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/cashier/v1/bootstrap')
            ->assertOk()
            ->json('data.settings');
        $this->assertArrayHasKey('sound_enabled', $bootstrapSettings);
        $this->assertArrayHasKey('enable_delivery', $bootstrapSettings);
    }

    public function test_cashier_can_edit_and_delete_table_order(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');
        $item = $this->makeItem($workspace, 10);
        $item2 = $this->makeItem($workspace, 7.5);

        $login = $this->postJson('/api/cashier/v1/auth/login', [
            'email_or_phone' => $owner->email,
            'password' => 'password',
            'device_name' => 'كاشير حاسم test',
            'device_type' => 'cashier',
        ])->assertOk();

        $token = $login->json('data.token');
        $headers = [
            'X-Workspace-Id' => (string) $workspace->id,
            'Idempotency-Key' => 'edit-del-'.uniqid(),
        ];

        $table = \App\Models\DiningTable::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'طاولة تعديل '.uniqid(),
            'status' => 'available',
            'qr_token' => \Illuminate\Support\Str::random(48),
        ]);

        $create = $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/cashier/v1/orders', [
                'order_type' => 'table',
                'dining_table_id' => $table->id,
                'client_reference' => 'edit-order-'.uniqid(),
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated();

        $orderId = (int) $create->json('data.id');
        $lineId = (int) $create->json('data.items.0.id');
        $this->assertGreaterThan(0, $lineId);

        $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->postJson("/api/cashier/v1/orders/{$orderId}/items", [
                'notes' => 'ملاحظة تعديل',
                'discount_amount' => 1,
                'items' => [
                    [
                        'id' => $lineId,
                        'quantity' => 3,
                        'unit_price' => 10,
                    ],
                    [
                        'pos_menu_item_id' => $item2->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'ملاحظة تعديل')
            ->assertJsonPath('data.discount_amount', 1);

        $order = Order::query()->findOrFail($orderId);
        $this->assertEquals(2, $order->items()->count());
        $this->assertEquals(37.5, (float) $order->subtotal); // 3*10 + 7.5
        $this->assertEquals('ملاحظة تعديل', $order->notes);

        $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->deleteJson("/api/cashier/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.pos_status', 'cancelled');

        $this->assertEquals('cancelled', $order->fresh()->pos_status);

        $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->getJson('/api/cashier/v1/reports/daily')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary' => [
                'open_orders_count',
                'completed_orders_count',
                'cancelled_orders_count',
                'discount_total',
                'tax_total',
            ], 'payment_methods']]);
    }

    public function test_cashier_rejects_workspace_without_pos_feature(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        \App\Models\WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'feature_key' => 'pos'],
            ['workspace_id' => $workspace->id, 'feature_key' => 'pos', 'enabled' => false, 'source' => 'manual']
        );

        $tokenResult = $owner->createToken('cashier', ['*']);
        $tokenResult->accessToken->forceFill(['workspace_id' => $workspace->id])->save();
        $token = $tokenResult->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Workspace-Id' => (string) $workspace->id])
            ->getJson('/api/cashier/v1/catalog/items')
            ->assertStatus(403)
            ->assertJsonPath('message', 'الكاشير غير متاح في باقتك الحالية');
    }

    private function makeItem(Workspace $workspace, float $price = 10): PosMenuItem
    {
        return PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'منتج كاشير',
            'price' => $price,
            'currency' => 'SAR',
            'is_active' => true,
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
        ]);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach (['pos', 'qr_menu', 'products', 'orders'] as $feature) {
            \App\Models\WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }

        $plan = \App\Models\Plan::query()
            ->where('workspace_type', $workspaceType)
            ->where('is_active', true)
            ->orderByDesc('price')
            ->first();

        if ($plan) {
            \App\Models\Subscription::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                ]
            );
        }

        return [$user, $workspace];
    }
}
