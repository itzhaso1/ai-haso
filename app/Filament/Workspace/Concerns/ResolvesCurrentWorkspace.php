<?php

namespace App\Filament\Workspace\Concerns;

use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

trait ResolvesCurrentWorkspace
{
    protected static function currentWorkspace(): ?Workspace
    {
        $workspaceId = session('current_workspace_id');
        $user = Auth::user();

        if (! $workspaceId || ! $user) {
            return null;
        }

        return $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->wherePivot('status', 'active')
            ->first();
    }

    protected static function isCommercialWorkspace(): bool
    {
        $workspace = static::currentWorkspace();

        return in_array($workspace?->type, ['company', 'store'], true);
    }
}
