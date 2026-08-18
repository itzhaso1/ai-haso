<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Conversation
    {
        return Conversation::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMessage(Conversation $conversation, array $data, ?User $actor = null): Message
    {
        return DB::transaction(function () use ($conversation, $data, $actor): Message {
            $message = $conversation->messages()->create([
                ...$data,
                'user_id' => $data['user_id'] ?? $actor?->id,
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            if ($conversation->customer) {
                $conversation->customer->update([
                    'last_conversation_at' => $message->created_at,
                ]);
            }

            return $message;
        });
    }

    public function toggleAi(Conversation $conversation, bool $enabled): Conversation
    {
        $conversation->update(['ai_enabled' => $enabled]);

        return $conversation->refresh();
    }
}
