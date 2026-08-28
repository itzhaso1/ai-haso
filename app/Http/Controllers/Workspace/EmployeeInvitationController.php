<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\EmployeeInvitation;
use App\Models\WorkspaceUser;
use App\Notifications\EmployeeInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeInvitationController extends Controller
{
    use InteractsWithWorkspace;

    public function index(): View
    {
        $workspace = $this->currentWorkspace();

        return view('workspace.employees.index', [
            'memberships' => WorkspaceUser::query()
                ->with('user')
                ->where('workspace_id', $workspace->id)
                ->latest('id')
                ->paginate(12),
            'invitations' => EmployeeInvitation::query()
                ->where('workspace_id', $workspace->id)
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('workspace.employees.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->currentWorkspace();

        $payload = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:owner,admin,manager,agent'],
        ]);

        $invitation = EmployeeInvitation::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'invited_by' => $request->user()?->id,
            'email' => $payload['email'],
            'role' => $payload['role'],
            'status' => 'pending',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('central_mail', $payload['email'])
            ->notify(new EmployeeInvitationNotification($invitation));

        return redirect()->route('workspace.employees.index')->with('success', 'تم إرسال الدعوة بنجاح.');
    }

    public function destroy(EmployeeInvitation $employee): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        abort_unless((int) $employee->workspace_id === (int) $workspace->id, 404);

        $employee->update(['status' => 'cancelled']);

        return redirect()->route('workspace.employees.index')->with('success', 'تم إلغاء الدعوة.');
    }
}
