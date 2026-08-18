<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAIResponse implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $incomingMessage = Message::query()->find($this->messageId);

        if (! $conversation || ! $incomingMessage || ! $conversation->ai_enabled) {
            return;
        }

        $reply = $aiService->generateReply($conversation, $incomingMessage);

        Message::query()->create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'content' => $reply,
            'ai_generated' => true,
        ]);

        $conversation->update(['last_message_at' => now()]);
    }
}
