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
        $this->assertStringContainsString('مشروبات', $html);
        $this->assertStringContainsString('ابحث عن صنف', $html);
        $this->assertStringContainsString('data-pos-categories-sidebar', $html);
        $this->assertStringContainsString('data-pos-products', $html);
        $this->assertStringContainsString('data-pos-cart', $html);

        // Category dropdown must be gone.
        $this->assertStringNotContainsString('كل التصنيفات', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*x-model="selectedCategoryId"/', $html);

        // Cart / order controls remain the original ones.
        $this->assertStringContainsString('إنشاء Order', $html);
        $this->assertStringContainsString('إتمام عبر سلة الجلسة', $html);
        $this->assertStringContainsString('prepareSubmit', $html);
    }

    public function test_cashier_category_filter_uses_client_side_selected_category(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $html = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.pos.cashier.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('selectedCategoryId', $html);
        $this->assertStringContainsString('filteredItems', $html);
        $this->assertStringContainsString('pos_item_category_id', $html);
        $this->assertStringContainsString('countInCategory', $html);
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
