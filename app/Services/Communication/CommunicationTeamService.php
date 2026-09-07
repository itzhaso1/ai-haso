<?php

namespace App\Services\Communication;

use App\Models\Communication\CommunicationTeam;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogService;

class CommunicationTeamService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(Workspace $workspace, string $name, array $userIds = [], ?User $actor = null): CommunicationTeam
    {
        $team = CommunicationTeam::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'is_default' => false,
        ]);

        $this->syncMembers($team, $workspace, $userIds, $actor);

        return $team->fresh('members') ?? $team;
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function syncMembers(CommunicationTeam $team, Workspace $workspace, array $userIds, ?User $actor = null): void
    {
        $validIds = User::query()
            ->whereIn('id', $userIds)
            ->whereHas('workspaces', function ($query) use ($workspace): void {
                $query->where('workspaces.id', $workspace->id)
                    ->where('workspace_users.status', 'active');
            })
            ->pluck('id')
            ->all();

        $team->members()->sync(
            collect($validIds)->mapWithKeys(fn (int $id): array => [
                $id => ['workspace_id' => $workspace->id],
            ])->all()
        );

        $this->auditLogService->log(
            action: 'communication.team.changed',
            entityType: 'communication_team',
            entityId: $team->id,
            newValues: ['member_ids' => $validIds],
            actor: $actor,
            workspaceId: $workspace->id,
        );
    }
}
