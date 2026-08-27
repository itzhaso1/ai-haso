<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\User;
use Illuminate\Http\Request;

abstract class AppointmentsBaseController extends Controller
{
    use InteractsWithWorkspace;

    protected function authorizeAppointments(Request $request, string $permission = 'appointments.view'): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->can($permission)) {
            return;
        }

        $workspace = $this->currentWorkspace();
        $isElevatedMember = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->exists();

        abort_unless($isElevatedMember, 403, 'You are not allowed to access appointments module.');
    }
}
