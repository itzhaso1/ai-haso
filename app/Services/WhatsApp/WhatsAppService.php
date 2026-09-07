<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\WhatsAppPhoneNumber;
use App\Services\Communication\ChannelConnectionService;
use App\Services\Communication\ConversationWriter;
use App\Services\Communication\IdentityService;
use App\Services\Communication\MessageService;
use Illuminate\Support\Str;

class WhatsAppService
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly ConversationWriter $conversationWriter,
        private readonly MessageService $messageService,
        private readonly ChannelConnectionService $channelConnectionService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function processWebhook(array $payload, array $headers): void
    {
        $phoneNumberId = $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;
        $messageData = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        $eventId = $payload['entry'][0]['id'] ?? (string) Str::uuid();

        if (! $phoneNumberId || ! is_array($messageData)) {
            return;
        }

        $phone = WhatsAppPhoneNumber::withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if (! $phone) {
            return;
        }

        $event = WebhookEvent::withoutGlobalScopes()->firstOrCreate(
            [
                'provider' => 'whatsapp',
                'external_event_id' => $eventId,
            ],
            [
                'workspace_id' => $phone->workspace_id,
                'event_type' => 'message',
                'idempotency_key' => $eventId,
                'headers' => $headers,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($event->wasRecentlyCreated === false) {
            return;
        }

        ProcessIncomingWhatsAppMessage::dispatch(
            $phone->workspace_id,
            $messageData,
            $event->id,
            (string) $phoneNumberId,
        );
    }

    /**
     * @param  array<string, mixed>  $messageData
     */
    public function storeIncomingMessage(int $workspaceId, array $messageData, ?string $phoneNumberId = null): Message
    {
        $customerPhone = (string) ($messageData['from'] ?? 'unknown');
        $content = $messageData['text']['body'] ?? null;
        $externalMessageId = $messageData['id'] ?? null;

        $connection = null;
        if (is_string($phoneNumberId) && $phoneNumberId !== '') {
            $phone = WhatsAppPhoneNumber::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('phone_number_id', $phoneNumberId)
                ->first();
            if ($phone) {
                $connection = $this->channelConnectionService->syncWhatsAppPhone($phone);
            }
        }

        $resolved = $this->identityService->resolve(
            $workspaceId,
            'whatsapp',
            $customerPhone,
            $connection?->id,
        );

        $conversation = $this->conversationWriter->findOrCreateWhatsApp(
            $workspaceId,
            $customerPhone,
            $connection,
            $resolved['customer']->id,
        );

        $message = $this->messageService->recordInbound($conversation, [
            'customer_id' => $resolved['customer']->id,
            'message_type' => 'text',
            'content' => $content,
            'external_message_id' => $externalMessageId,
            'metadata' => $messageData,
            'channel_connection_id' => $connection?->id,
        ]);

        if ($resolved['identity']->conversation_id !== $conversation->id) {
            $resolved['identity']->forceFill([
                'conversation_id' => $conversation->id,
                'channel_connection_id' => $connection?->id ?? $resolved['identity']->channel_connection_id,
            ])->save();
        }

        return $message;
    }
}
