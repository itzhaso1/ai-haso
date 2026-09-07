<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceMembership;

class ConversationPolicy
{
    use ChecksWorkspaceMembership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->hasMembership($user, $conversation->workspace);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->canStaffConversation($user, $conversation);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->canStaffConversation($user, $conversation);
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        return $this->canStaffConversation($user, $conversation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->hasAnyWorkspaceRole($user, $conversation->workspace, ['owner', 'admin', 'manager']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return $this->delete($user, $conversation);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return false;
    }

    private function canStaffConversation(User $user, Conversation $conversation): bool
    {
        if ($this->hasAnyWorkspaceRole($user, $conversation->workspace, ['owner', 'admin', 'manager', 'agent'])) {
            return true;
        }

        return $this->hasMembership($user, $conversation->workspace)
            && $user->can('conversations.manage');
    }
}
