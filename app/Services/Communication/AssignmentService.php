<?php

namespace App\Services\Communication;

use App\Events\Realtime\ConversationUpdated;
use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use InvalidArgumentException;

class AssignmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function assign(
        Conversation $conversation,
        ?int $teamId,
        ?int $userId,
        ?string $priority = null,
        ?User $actor = null,
    ): Conversation {
        $old = [
            'assigned_team_id' => $conversation->assigned_team_id,
            'assigned_user_id' => $conversation->assigned_user_id,
            'priority' => $conversation->priority,
        ];

        if ($teamId) {
            $team = CommunicationTeam::withoutGlobalScopes()
                ->where('workspace_id', $conversation->workspace_id)
                ->whereKey($teamId)
                ->first();
            if (! $team) {
                throw new InvalidArgumentException('Team does not belong to this workspace.');
            }
        }

        if ($userId) {
            $member = User::query()
                ->whereKey($userId)
                ->whereHas('workspaces', function ($query) use ($conversation): void {
                    $query->where('workspaces.id', $conversation->workspace_id)
                        ->where('workspace_users.status', 'active');
                })
                ->first();
            if (! $member) {
                throw new InvalidArgumentException('Agent is not an active member of this workspace.');
            }
            if ($teamId && ! $this->userOnTeam($conversation->workspace_id, $teamId, $userId)) {
                throw new InvalidArgumentException('Agent is not a member of the selected team.');
            }
        }

        $conversation->forceFill([
            'assigned_team_id' => $teamId,
            'assigned_user_id' => $userId,
            'assigned_at' => ($teamId || $userId) ? now() : null,
            'priority' => $priority ?: ($conversation->priority ?: 'normal'),
        ])->save();

        $this->auditLogService->log(
            action: 'communication.assignment.changed',
            entityType: 'conversation',
            entityId: $conversation->id,
            oldValues: $old,
            newValues: [
                'assigned_team_id' => $conversation->assigned_team_id,
                'assigned_user_id' => $conversation->assigned_user_id,
                'priority' => $conversation->priority,
            ],
            actor: $actor,
            workspaceId: $conversation->workspace_id,
        );

        event(new ConversationUpdated($conversation->fresh() ?? $conversation));

        return $conversation->fresh() ?? $conversation;
    }

    public function unassign(Conversation $conversation, ?User $actor = null): Conversation
    {
        return $this->assign($conversation, null, null, $conversation->priority, $actor);
    }

    public function setPriority(Conversation $conversation, string $priority, ?User $actor = null): Conversation
    {
        return $this->assign(
            $conversation,
            $conversation->assigned_team_id ? (int) $conversation->assigned_team_id : null,
            $conversation->assigned_user_id ? (int) $conversation->assigned_user_id : null,
            $priority,
            $actor,
        );
    }

    private function userOnTeam(int $workspaceId, int $teamId, int $userId): bool
    {
        return CommunicationTeam::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereKey($teamId)
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->exists();
    }
}
