<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class WorkspaceService
{
    public function createForUser(User $user, string $workspaceType, ?string $workspaceName = null): Workspace
    {
        return DB::transaction(function () use ($user, $workspaceType, $workspaceName): Workspace {
            $name = $workspaceName ?: $user->name.' Workspace';

            $workspace = Workspace::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
                'type' => $workspaceType,
                'owner_user_id' => $user->id,
                'status' => 'active',
            ]);

            $workspace->users()->attach($user->id, [
                'membership_role' => 'owner',
                'status' => 'active',
                'is_primary' => true,
                'joined_at' => now(),
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
            Role::findOrCreate('owner', 'web');
            $user->assignRole('owner');
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);

            return $workspace->fresh();
        });
    }
}
