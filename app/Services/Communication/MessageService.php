<?php

namespace App\Services\Communication;

use App\Events\Realtime\ConversationUpdated;
use App\Events\Realtime\MessageCreated;
use App\Exceptions\ChannelUnavailableException;
use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogService;
use App\Services\Communication\Channels\ChannelAdapterManager;
use App\Services\Communication\Channels\ChannelSendResult;
use App\Services\Communication\Channels\OutboundMessageIntent;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function __construct(
        private readonly ChannelAdapterManager $adapters,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordInbound(Conversation $conversation, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $data): Message {
            $message = $this->persist($conversation, [
                ...$data,
                'direction' => 'inbound',
                'delivery_status' => Message::DELIVERY_RECEIVED,
                'user_id' => $data['user_id'] ?? null,
            ]);

            $this->touchConversation($conversation, $message);
            event(new MessageCreated($message));
            event(new ConversationUpdated($conversation->fresh()));

            return $message;
        });
    }

    public function recordInternalNote(Conversation $conversation, User $actor, string $content, array $metadata = []): Message
    {
        return DB::transaction(function () use ($conversation, $actor, $content, $metadata): Message {
            $message = $this->persist($conversation, [
                'direction' => 'internal_note',
                'message_type' => 'text',
                'content' => $content,
                'user_id' => $actor->id,
                'delivery_status' => Message::DELIVERY_NA,
                'metadata' => $metadata,
            ]);

            $this->touchConversation($conversation, $message);
            event(new MessageCreated($message));
            event(new ConversationUpdated($conversation->fresh()));

            return $message;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordOutbound(Conversation $conversation, array $data, ?User $actor = null): Message
    {
        $channel = (string) ($conversation->channel ?: 'manual');
        $adapter = $this->adapters->for($channel);
        $connection = $conversation->channelConnection()
            ->withoutGlobalScopes()
            ->find($conversation->channel_connection_id);

        if (in_array($adapter->catalogStatus(), ['coming_soon'], true) && $channel !== 'manual' && $channel !== 'web') {
            throw new ChannelUnavailableException($channel, 'coming_soon');
        }

        if ($channel === 'email') {
            throw new ChannelUnavailableException($channel, 'not_wired', __('إرسال البريد من Inbox غير مفعّل بعد. استخدم مركز البريد.'));
        }

        $needsProvider = $channel === 'whatsapp';

        $message = DB::transaction(function () use ($conversation, $data, $actor, $needsProvider): Message {
            $message = $this->persist($conversation, [
                ...$data,
                'direction' => 'outbound',
                'user_id' => $data['user_id'] ?? $actor?->id,
                'delivery_status' => $needsProvider ? Message::DELIVERY_PENDING : Message::DELIVERY_NA,
                'channel_connection_id' => $conversation->channel_connection_id,
            ]);

            $this->touchConversation($conversation, $message);

            return $message;
        });

        if (! $needsProvider) {
            event(new MessageCreated($message));
            event(new ConversationUpdated($conversation->fresh()));

            return $message->fresh() ?? $message;
        }

        $workspace = Workspace::withoutGlobalScopes()->find($conversation->workspace_id);
        if (! $workspace) {
            $message->forceFill([
                'delivery_status' => Message::DELIVERY_FAILED,
                'delivery_error' => 'Workspace missing.',
            ])->save();

            event(new MessageCreated($message->fresh() ?? $message));
            event(new ConversationUpdated($conversation->fresh()));

            return $message->fresh() ?? $message;
        }

        $intent = new OutboundMessageIntent(
            workspace: $workspace,
            conversation: $conversation,
            message: $message,
            body: (string) ($data['content'] ?? ''),
            connection: $connection,
            actor: $actor,
        );

        try {
            $result = $adapter->send($intent);
        } catch (FeatureNotAvailableException|UsageLimitExceededException|\Throwable $exception) {
            $result = ChannelSendResult::failed($exception->getMessage() ?: $exception::class);
        }

        $this->applySendResult($message, $result);
        $this->auditLogService->log(
            action: ($data['ai_generated'] ?? false) ? 'communication.ai.outbound' : 'communication.message.outbound',
            entityType: 'message',
            entityId: $message->id,
            newValues: [
                'delivery_status' => $message->delivery_status,
                'channel' => $channel,
                'delivery_error' => $message->delivery_error,
            ],
            actor: $actor,
            workspaceId: $conversation->workspace_id,
        );

        event(new MessageCreated($message->fresh() ?? $message));
        event(new ConversationUpdated($conversation->fresh()));

        return $message->fresh() ?? $message;
    }

    public function retryOutbound(Message $message, User $actor): Message
    {
        if ($message->direction !== 'outbound' || $message->delivery_status !== Message::DELIVERY_FAILED) {
            throw new \InvalidArgumentException('Only failed outbound messages can be retried.');
        }

        $conversation = Conversation::withoutGlobalScopes()->find($message->conversation_id);
        if (! $conversation) {
            throw new \InvalidArgumentException('Conversation is missing for this message.');
        }

        return $this->recordOutbound($conversation, [
            'content' => (string) $message->content,
            'message_type' => $message->message_type ?: 'text',
            'customer_id' => $message->customer_id,
            'metadata' => array_merge(
                is_array($message->metadata) ? $message->metadata : [],
                ['retry_of_message_id' => $message->id],
            ),
        ], $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Conversation $conversation, array $data): Message
    {
        return Message::withoutGlobalScopes()->create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'channel_connection_id' => $data['channel_connection_id'] ?? $conversation->channel_connection_id,
            'customer_id' => $data['customer_id'] ?? $conversation->customer_id,
            'user_id' => $data['user_id'] ?? null,
            'direction' => $data['direction'],
            'message_type' => $data['message_type'] ?? 'text',
            'content' => $data['content'] ?? null,
            'external_message_id' => $data['external_message_id'] ?? null,
            'ai_generated' => (bool) ($data['ai_generated'] ?? false),
            'delivery_status' => $data['delivery_status'] ?? null,
            'delivery_error' => $data['delivery_error'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    private function applySendResult(Message $message, ChannelSendResult $result): void
    {
        if ($result->isSent()) {
            $message->forceFill([
                'delivery_status' => Message::DELIVERY_SENT,
                'delivery_error' => null,
                'external_message_id' => $result->externalMessageId ?: $message->external_message_id,
                'delivered_at' => now(),
            ])->save();

            return;
        }

        $message->forceFill([
            'delivery_status' => Message::DELIVERY_FAILED,
            'delivery_error' => $result->error ?: 'Send failed.',
        ])->save();
    }

    private function touchConversation(Conversation $conversation, Message $message): void
    {
        Conversation::withoutGlobalScopes()->whereKey($conversation->id)->update([
            'last_message_at' => $message->created_at ?? now(),
        ]);

        if ($conversation->customer_id) {
            $conversation->customer()?->withoutGlobalScopes()->whereKey($conversation->customer_id)->update([
                'last_conversation_at' => $message->created_at ?? now(),
            ]);
        }
    }
}
