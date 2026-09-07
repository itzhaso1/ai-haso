<?php

namespace App\Services\Communication;

use App\Events\Realtime\ConversationUpdated;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\Message;
use App\Models\User;

class UnreadService
{
    public function markRead(Conversation $conversation, User $user, ?int $messageId = null): ConversationUserState
    {
        $lastId = $messageId
            ?? (int) Message::withoutGlobalScopes()->where('conversation_id', $conversation->id)->max('id');

        $state = ConversationUserState::withoutGlobalScopes()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        $state->workspace_id = $conversation->workspace_id;
        $state->last_read_message_id = $lastId > 0 ? $lastId : null;
        $state->last_read_at = now();
        $state->save();

        event(new ConversationUpdated($conversation->fresh() ?? $conversation));

        return $state;
    }

    public function archive(Conversation $conversation, User $user, bool $archived = true): ConversationUserState
    {
        $state = ConversationUserState::withoutGlobalScopes()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        $state->workspace_id = $conversation->workspace_id;
        $state->archived_at = $archived ? now() : null;
        $state->save();

        event(new ConversationUpdated($conversation->fresh() ?? $conversation));

        return $state;
    }

    public function mute(Conversation $conversation, User $user, bool $muted = true): ConversationUserState
    {
        $state = ConversationUserState::withoutGlobalScopes()->firstOrNew([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        $state->workspace_id = $conversation->workspace_id;
        $state->muted_at = $muted ? now() : null;
        $state->save();

        return $state;
    }

    public function unreadCountForConversation(Conversation $conversation, User $user): int
    {
        $state = ConversationUserState::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        $query = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound');

        if ($state?->last_read_message_id) {
            $query->where('id', '>', $state->last_read_message_id);
        }

        return (int) $query->count();
    }
}
