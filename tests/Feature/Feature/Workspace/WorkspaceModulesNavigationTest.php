<?php

namespace Tests\Feature\Feature\Workspace;

use App\Models\Contract\Contract as WorkspaceContract;
use App\Models\Customer;
use App\Models\Finance\FinanceEmployeeProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceModulesNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_dashboard_shows_grouped_modules_with_existing_routes(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.dashboard'));

        $response->assertOk()
            ->assertSee('Products & Inventory')
            ->assertSee('Customers & Orders')
            ->assertSee('Communication')
            ->assertSee('Payments & Subscriptions')
            ->assertSee('Finance')
            ->assertSee('Contracts')
            ->assertSee(route('workspace.categories.index'), false)
            ->assertSee(route('workspace.products.index'), false)
            ->assertSee(route('workspace.inventory.index'), false)
            ->assertSee(route('workspace.customers.index'), false)
            ->assertSee(route('workspace.finance.dashboard'), false)
            ->assertSee(route('workspace.appointments.dashboard'), false);
    }

    public function test_contracts_module_respects_workspace_customer_isolation(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [$ownerB, $workspaceB] = $this->createWorkspaceOwner('store');

        $customerB = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Foreign Customer',
            'phone' => '0500001111',
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.contracts.store'), [
                'title' => 'Isolation Contract',
                'customer_id' => $customerB->id,
                'currency' => 'SAR',
                'value' => 1000,
            ])
            ->assertSessionHasErrors('customer_id');

        $customerA = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Local Customer',
            'phone' => '0500002222',
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.contracts.store'), [
                'title' => 'Local Contract',
                'customer_id' => $customerA->id,
                'currency' => 'SAR',
                'items' => [
                    [
                        'title' => 'Website Design',
                        'quantity' => 1,
                        'unit_price' => 1000,
                    ],
                ],
            ])
            ->assertRedirect();

        $contract = WorkspaceContract::withoutGlobalScopes()
            ->where('workspace_id', $workspaceA->id)
            ->where('title', 'Local Contract')
            ->firstOrFail();

        $this->actingAs($ownerB)
            ->withSession(['current_workspace_id' => $workspaceB->id])
            ->get(route('workspace.contracts.show', $contract))
            ->assertNotFound();
    }

    public function test_finance_employees_module_uses_workspace_members_without_duplication(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [$ownerB, $workspaceB] = $this->createWorkspaceOwner('store');

        $foreignUser = User::factory()->create();
        $workspaceB->users()->attach($foreignUser->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.finance.employees.store'), [
                'user_id' => $foreignUser->id,
                'finance_role' => 'accountant',
            ])
            ->assertSessionHasErrors('user_id');

        $localUser = User::factory()->create();
        $workspaceA->users()->attach($localUser->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.finance.employees.store'), [
                'user_id' => $localUser->id,
                'finance_role' => 'cashier',
                'basic_salary' => 5000,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $profile = FinanceEmployeeProfile::withoutGlobalScopes()
            ->where('workspace_id', $workspaceA->id)
            ->where('user_id', $localUser->id)
            ->firstOrFail();

        $this->assertSame('cashier', $profile->finance_role);
        $this->assertSame('5000.00', (string) $profile->basic_salary);
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
