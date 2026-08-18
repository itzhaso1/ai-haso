<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Feature\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_workspace_cannot_access_commerce_features_by_default(): void
    {
        $service = app(FeatureAccessService::class);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'individual',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $plan = Plan::query()->create([
            'code' => 'individual_free',
            'name' => 'Individual Free',
            'workspace_type' => 'individual',
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'price' => 0,
            'is_active' => true,
            'features' => ['ai', 'smart_replies', 'conversations', 'usage', 'subscription'],
            'limits' => ['ai_usage' => 1000],
        ]);

        Subscription::query()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertTrue($service->hasFeature($user, $workspace, 'ai'));
        $this->assertFalse($service->hasFeature($user, $workspace, 'products'));
    }
}
