<?php

namespace App\Jobs;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use App\Services\Communication\MessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAIResponse implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $messageId,
    ) {}

    public function handle(AIService $aiService, MessageService $messageService): void
    {
        $conversation = Conversation::withoutGlobalScopes()->find($this->conversationId);
        $incomingMessage = Message::withoutGlobalScopes()->find($this->messageId);

        if (! $conversation || ! $incomingMessage || ! $conversation->ai_enabled) {
            return;
        }

        try {
            $reply = $aiService->generateReply($conversation, $incomingMessage);
        } catch (FeatureNotAvailableException|UsageLimitExceededException $exception) {
            Log::info('AI reply skipped due to entitlement limits.', [
                'conversation_id' => $conversation->id,
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        try {
            $messageService->recordOutbound($conversation, [
                'content' => $reply,
                'message_type' => 'text',
                'ai_generated' => true,
                'customer_id' => $conversation->customer_id,
            ]);
        } catch (FeatureNotAvailableException|UsageLimitExceededException $exception) {
            Log::info('AI WhatsApp outbound skipped due to entitlement limits.', [
                'conversation_id' => $conversation->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
