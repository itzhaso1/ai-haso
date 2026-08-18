<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'roles' => Role::query()->where('guard_name', 'web')->get(),
                'permissions' => Permission::query()->where('guard_name', 'web')->get(),
            ],
        ]);
    }

    public function assignRole(Request $request): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        abort_unless(
            $user->workspaces()
                ->where('workspaces.id', $workspace->id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );
        $user->syncRoles([$validated['role']]);

        return response()->json(['data' => ['user_id' => $user->id, 'role' => $validated['role']]]);
    }

    public function syncPermissions(Request $request): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        abort_unless(
            $user->workspaces()
                ->where('workspaces.id', $workspace->id)
                ->wherePivot('status', 'active')
                ->exists(),
            404
        );
        $user->syncPermissions($validated['permissions'] ?? []);

        return response()->json(['data' => ['user_id' => $user->id, 'permissions' => $validated['permissions'] ?? []]]);
    }
}
