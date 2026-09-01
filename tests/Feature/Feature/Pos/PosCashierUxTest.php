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

    public function test_cashier_renders_category_sidebar_not_dropdown(): void
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

        $this->assertStringContainsString('التصنيفات', $html);
        $this->assertStringContainsString('الكل', $html);
        $this->assertStringContainsString('إنشاء الطلب', $html);
        $this->assertStringContainsString('طلب خارجي', $html);
        $this->assertStringContainsString('طباعة الفاتورة', $html);
        $this->assertStringContainsString('بدون فاتورة', $html);
        $this->assertStringContainsString('ابحث عن صنف', $html);
        $this->assertStringNotContainsString('كل التصنيفات', $html);
        $this->assertStringNotContainsString('إتمام عبر سلة الجلسة', $html);
        $this->assertStringNotContainsString('إنشاء Order', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*x-model="selectedCategoryId"/', $html);
    }

    public function test_create_order_json_does_not_force_print_redirect(): void
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
        // Must NOT auto-navigate: print is optional payload only.
        $this->assertStringContainsString('/print', (string) $response->json('print_url'));
    }

    public function test_create_order_form_stays_on_cashier_without_print_redirect(): void
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

    public function test_cart_checkout_json_returns_optional_print_url(): void
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

    public function test_tables_index_hides_inline_close_and_bill_buttons(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $html = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.pos.tables.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('تفاصيل الطلب', $html);
        $this->assertStringContainsString('tablesBoard', $html);
        $this->assertStringContainsString('إغلاق الطاولة', $html);
        $this->assertStringContainsString('الحساب', $html);
        $this->assertStringContainsString('toggleMenu', $html);
        $this->assertStringContainsString('confirmClose', $html);
        // Old always-visible dual action buttons on each card are gone.
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
