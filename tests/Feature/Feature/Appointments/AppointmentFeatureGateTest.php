<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_appointments_require_feature_flag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertRedirect(route('workspace.subscriptions.index'));

        $this->enableWorkspaceFeature($workspace, 'appointments');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertOk();
    }
}
