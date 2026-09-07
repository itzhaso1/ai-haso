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
        $counts = $this->unreadCountsForConversations($user, [$conversation->id]);

        return $counts[$conversation->id] ?? 0;
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, int>
     */
    public function unreadCountsForConversations(User $user, array $conversationIds): array
    {
        $conversationIds = array_values(array_unique(array_filter($conversationIds)));
        $counts = [];
        foreach ($conversationIds as $conversationId) {
            $counts[(int) $conversationId] = 0;
        }

        if ($conversationIds === []) {
            return $counts;
        }

        $rows = Message::withoutGlobalScopes()
            ->selectRaw('messages.conversation_id, COUNT(*) as unread_count')
            ->leftJoin('conversation_user_states as cus', function ($join) use ($user): void {
                $join->on('cus.conversation_id', '=', 'messages.conversation_id')
                    ->where('cus.user_id', '=', $user->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.direction', 'inbound')
            ->where(function ($query): void {
                $query->whereNull('cus.last_read_message_id')
                    ->orWhereColumn('messages.id', '>', 'cus.last_read_message_id');
            })
            ->groupBy('messages.conversation_id')
            ->pluck('unread_count', 'conversation_id');

        foreach ($rows as $conversationId => $count) {
            $counts[(int) $conversationId] = (int) $count;
        }

        return $counts;
    }
}
