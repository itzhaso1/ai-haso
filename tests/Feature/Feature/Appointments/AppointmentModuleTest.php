<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService;
use App\Models\Appointment\AppointmentStaff;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_dashboard_is_isolated_and_accessible(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertOk()
            ->assertSee('إدارة المواعيد');
    }

    public function test_create_booking_and_prevent_time_overlap_for_same_staff(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $employee = User::factory()->create();
        $workspace->users()->attach($employee->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.services.store'), [
                'name' => 'كشف طبي',
                'duration_minutes' => 30,
                'price' => 100,
            ])
            ->assertRedirect();

        $service = AppointmentService::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.staff.store'), [
                'user_id' => $employee->id,
                'name' => 'د. أحمد',
                'role' => 'طبيب',
            ])
            ->assertRedirect();

        $staff = AppointmentStaff::query()->firstOrFail();

        $starts = now()->addDay()->setTime(10, 0, 0);
        $ends = now()->addDay()->setTime(10, 30, 0);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل أول',
                'customer_phone' => '0500001111',
                'starts_at' => $starts->toDateTimeString(),
                'ends_at' => $ends->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $this->assertSame(1, AppointmentBooking::query()->count());

        $overlapResponse = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل ثاني',
                'customer_phone' => '0500002222',
                'starts_at' => $starts->copy()->addMinutes(10)->toDateTimeString(),
                'ends_at' => $ends->copy()->addMinutes(10)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ]);

        $overlapResponse->assertRedirect();
        $overlapResponse->assertSessionHas('error');
        $this->assertSame(1, AppointmentBooking::query()->count());
    }

    public function test_appointments_booking_isolation_between_workspaces(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        $serviceB = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'خدمة مساحة B',
            'duration_minutes' => 20,
            'price' => 50,
            'is_active' => true,
        ]);

        $bookingB = AppointmentBooking::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'booking_number' => 'APT-20300101-0001',
            'service_id' => $serviceB->id,
            'customer_name' => 'عميل مساحة B',
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 10:30:00',
            'status' => 'scheduled',
            'source' => 'dashboard',
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.appointments.bookings.status', $bookingB), [
                'status' => 'completed',
            ])
            ->assertNotFound();
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
