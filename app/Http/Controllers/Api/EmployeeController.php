<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeInvitation;
use App\Models\User;
use App\Models\WorkspaceUser;
use App\Notifications\EmployeeInvitationNotification;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function index(): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        $employees = $workspace->memberships()
            ->with('user')
            ->where('status', 'active')
            ->get();

        return response()->json(['data' => $employees]);
    }

    public function invite(Request $request): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:owner,admin,manager,agent'],
        ]);

        $invitation = EmployeeInvitation::query()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => 'pending',
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'invited_by' => $request->user()?->id,
        ]);

        Notification::route('central_mail', $validated['email'])
            ->notify(new EmployeeInvitationNotification($invitation));

        return response()->json(['data' => $invitation], 201);
    }

    public function update(Request $request, WorkspaceUser $membership): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace && $membership->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'membership_role' => ['nullable', 'in:owner,admin,manager,agent'],
            'status' => ['nullable', 'in:active,invited,suspended'],
        ]);

        $membership->update($validated);

        return response()->json(['data' => $membership->refresh()]);
    }

    public function destroy(WorkspaceUser $membership): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace && $membership->workspace_id === $workspace->id, 404);

        $membership->delete();

        return response()->json(status: 204);
    }

    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $invitation = EmployeeInvitation::withoutGlobalScopes()
            ->where('token', $token)
            ->where('email', $user->email)
            ->where('status', 'pending')
            ->first();

        if (! $invitation) {
            throw new ModelNotFoundException('Invitation not found.');
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);

            return response()->json([
                'message' => 'Invitation has expired.',
            ], 422);
        }

        $membership = DB::transaction(function () use ($invitation, $user) {
            $membership = WorkspaceUser::query()->updateOrCreate(
                [
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                [
                    'membership_role' => $invitation->role,
                    'status' => 'active',
                    'is_primary' => false,
                    'invited_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]
            );

            app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->workspace_id);
            try {
                $role = Role::findOrCreate($invitation->role, 'web');
                $user->assignRole($role);
            } finally {
                app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            }

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $membership;
        });

        return response()->json([
            'message' => 'Invitation accepted.',
            'data' => [
                'membership' => $membership->fresh(),
                'workspace_id' => $invitation->workspace_id,
            ],
        ]);
    }
}
