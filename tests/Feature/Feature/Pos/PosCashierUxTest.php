<?php

namespace Tests\Feature\Feature\Pos;

use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashierUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_keeps_category_sidebar_and_narrow_cart(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $category = PosItemCategory::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروبات',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'pos_item_category_id' => $category->id,
            'name' => 'قهوة',
            'price' => 10,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.pos.cashier.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-pos-categories-sidebar', $html);
        $this->assertStringContainsString('التصنيفات', $html);
        $this->assertStringContainsString('الكل', $html);
        $this->assertStringContainsString('xl:col-span-3', $html); // narrower cart
        $this->assertStringContainsString('xl:col-span-7', $html); // wider products
        $this->assertStringContainsString('إنشاء الطلب', $html);
        $this->assertStringContainsString('طلب خارجي', $html);
        $this->assertStringContainsString('إتمام + طباعة الفاتورة', $html);
        $this->assertStringContainsString('إتمام بدون فاتورة', $html);
        $this->assertStringNotContainsString('كل التصنيفات', $html);
        $this->assertStringNotContainsString('إتمام عبر سلة الجلسة', $html);
        $this->assertStringNotContainsString('إنشاء Order', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*x-model="selectedCategoryId"/', $html);
    }

    public function test_create_order_json_returns_optional_print_without_forced_redirect(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'عصير',
            'price' => 8,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('workspace.pos.orders.store'), [
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم إنشاء الطلب بنجاح')
            ->assertJsonStructure(['order_id', 'order_number', 'print_url']);

        $this->assertNotEmpty($response->json('order_number'));
        $this->assertNotEmpty($response->json('print_url'));
    }

    public function test_create_order_form_stays_on_cashier(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'ماء',
            'price' => 2,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->from(route('workspace.pos.cashier.index'))
            ->post(route('workspace.pos.orders.store'), [
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('workspace.pos.cashier.index'));
    }

    public function test_external_order_checkout_json_does_not_auto_redirect(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'شاي',
            'price' => 5,
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('workspace.pos.cart.items.store'), [
                'pos_menu_item_id' => $item->id,
                'quantity' => 2,
            ])->assertOk();

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('workspace.pos.cart.checkout'), []);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', null)
            ->assertJsonStructure(['order_id', 'order_number', 'print_url']);
    }

    public function test_tables_board_separates_details_and_menu_actions(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $html = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.pos.tables.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-table-order-details', $html);
        $this->assertStringContainsString('تفاصيل الطلب', $html);
        $this->assertStringContainsString('data-table-menu', $html);
        $this->assertStringContainsString('الحساب', $html);
        $this->assertStringContainsString('إغلاق الطاولة', $html);
        $this->assertStringContainsString('confirmClose', $html);
        $this->assertStringNotContainsString('إغلاق الجلسة</button>', $html);
    }

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = \App\Models\User::factory()->create();
        $workspace = \App\Models\Workspace::factory()->create([
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
            ->where('code', $workspaceType.'_pro')
            ->first()
            ?? \App\Models\Plan::query()
                ->where('workspace_type', $workspaceType)
                ->where('is_active', true)
                ->orderByDesc('price')
                ->first();

        if ($plan) {
            \App\Models\Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        }

        return [$user, $workspace];
    }
}
