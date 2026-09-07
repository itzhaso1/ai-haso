<?php

namespace App\Services\Communication\Channels\Adapters;

use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\UsageLimitExceededException;
use App\Models\Communication\ChannelConnection;
use App\Models\WhatsAppOutboundMessage;
use App\Models\WhatsAppPhoneNumber;
use App\Services\Communication\Channels\ChannelAdapterInterface;
use App\Services\Communication\Channels\ChannelSendResult;
use App\Services\Communication\Channels\OutboundMessageIntent;
use App\Services\WhatsApp\WhatsAppOutboundService;

class WhatsAppAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly WhatsAppOutboundService $whatsAppOutbound,
    ) {}

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function catalogStatus(): string
    {
        return 'available';
    }

    public function capabilities(?ChannelConnection $connection): array
    {
        return [
            'send_text' => $connection?->isConnected() ?? false,
            'send_media' => false,
            'inbound' => true,
            'templates' => true,
        ];
    }

    public function canSend(?ChannelConnection $connection): bool
    {
        return $connection?->channel === 'whatsapp' && $connection->isConnected();
    }

    public function send(OutboundMessageIntent $intent): ChannelSendResult
    {
        $connection = $intent->connection;
        if (! $this->canSend($connection)) {
            return ChannelSendResult::failed('WhatsApp is not connected for this conversation.');
        }

        $phoneNumberId = $this->resolvePhoneNumberId($intent);
        $to = trim((string) $intent->conversation->external_id);

        if ($phoneNumberId === null || $phoneNumberId === '') {
            return ChannelSendResult::failed('WhatsApp phone_number_id is unknown on this conversation.');
        }

        if ($to === '' || $to === 'unknown') {
            return ChannelSendResult::failed('WhatsApp recipient is unknown on this conversation.');
        }

        try {
            $outbound = $this->whatsAppOutbound->sendText(
                workspace: $intent->workspace,
                phoneNumberId: $phoneNumberId,
                to: $to,
                body: $intent->body,
                conversationId: $intent->conversation->id,
                messageId: $intent->message->id,
                queue: false,
            );
        } catch (FeatureNotAvailableException|UsageLimitExceededException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return ChannelSendResult::failed($exception->getMessage());
        }

        $outbound->refresh();

        if ($outbound->status === WhatsAppOutboundMessage::STATUS_SENT) {
            return ChannelSendResult::sent(
                $outbound->provider_message_id,
                is_array($outbound->provider_response) ? $outbound->provider_response : [],
            );
        }

        return ChannelSendResult::failed(
            (string) ($outbound->last_error ?: 'WhatsApp send failed.'),
            is_array($outbound->provider_response) ? $outbound->provider_response : [],
        );
    }

    private function resolvePhoneNumberId(OutboundMessageIntent $intent): ?string
    {
        $metadata = is_array($intent->conversation->metadata) ? $intent->conversation->metadata : [];
        $fromMetadata = $metadata['phone_number_id'] ?? null;
        if (is_string($fromMetadata) && $fromMetadata !== '') {
            return $fromMetadata;
        }

        $connection = $intent->connection;
        if ($connection?->source_type === ChannelConnection::SOURCE_WHATSAPP_PHONE && $connection->source_id) {
            $phone = WhatsAppPhoneNumber::withoutGlobalScopes()->find($connection->source_id);

            return $phone?->phone_number_id;
        }

        $connectionMeta = is_array($connection?->metadata) ? $connection->metadata : [];
        $fromConnection = $connectionMeta['phone_number_id'] ?? $connection?->external_account_id;

        return is_string($fromConnection) && $fromConnection !== '' ? $fromConnection : null;
    }
}
