<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Communication\ConversationWriter;
use App\Services\Communication\MessageService;
use App\Support\Communication\ChannelIdentifier;

class ConversationService
{
    public function __construct(
        private readonly ConversationWriter $conversationWriter,
        private readonly MessageService $messageService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Conversation
    {
        return $this->conversationWriter->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMessage(Conversation $conversation, array $data, ?User $actor = null): Message
    {
        $direction = (string) ($data['direction'] ?? 'outbound');

        if ($direction === 'internal_note') {
            return $this->messageService->recordInternalNote(
                $conversation,
                $actor ?? throw new \InvalidArgumentException('Actor is required for internal notes.'),
                (string) ($data['content'] ?? ''),
                is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            );
        }

        if ($direction === 'inbound') {
            return $this->messageService->recordInbound($conversation, $data);
        }

        return $this->messageService->recordOutbound($conversation, $data, $actor);
    }

    public function toggleAi(Conversation $conversation, bool $enabled): Conversation
    {
        $conversation->update(['ai_enabled' => $enabled]);

        return $conversation->refresh();
    }

    public function normalizeChannelName(string $channel): string
    {
        return ChannelIdentifier::normalizeChannelName($channel);
    }
}
