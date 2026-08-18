<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceResolverService
{
    public function resolveFromRequest(Request $request, ?User $user): ?Workspace
    {
        $requestedWorkspace = $request->route('workspace')
            ?? $request->header('X-Workspace-Id')
            ?? $request->input('workspace_id');

        if ($requestedWorkspace === null || $user === null) {
            return null;
        }

        return $user->workspaces()
            ->where(function ($query) use ($requestedWorkspace): void {
                $query
                    ->where('workspaces.id', $requestedWorkspace)
                    ->orWhere('workspaces.uuid', $requestedWorkspace)
                    ->orWhere('workspaces.slug', $requestedWorkspace);
            })
            ->wherePivot('status', 'active')
            ->first();
    }
}
