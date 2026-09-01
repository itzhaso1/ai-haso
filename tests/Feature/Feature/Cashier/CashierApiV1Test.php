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
