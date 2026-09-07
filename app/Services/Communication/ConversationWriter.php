<?php

namespace App\Services\Communication;

use App\Models\Communication\ChannelConnection;
use App\Models\Conversation;
use App\Support\Communication\ChannelIdentifier;
use App\Support\Communication\ConversationIdentityKey;

class ConversationWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Conversation
    {
        $channel = ChannelIdentifier::normalizeChannelName((string) ($data['channel'] ?? 'manual'));
        $data['channel'] = $channel;
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata['channel_source'] = $channel;
        $data['metadata'] = $metadata;
        $data['identity_key'] = ConversationIdentityKey::make(
            isset($data['channel_connection_id']) ? (int) $data['channel_connection_id'] : null,
            $channel,
            $data['external_id'] ?? null,
        );

        return Conversation::withoutGlobalScopes()->create($data);
    }

    public function findOrCreateWhatsApp(
        int $workspaceId,
        string $waId,
        ?ChannelConnection $connection,
        int $customerId,
    ): Conversation {
        $connectionId = $connection?->id;
        $identityKey = ConversationIdentityKey::make($connectionId, 'whatsapp', $waId);

        $query = Conversation::withoutGlobalScopes()->where('workspace_id', $workspaceId);

        $conversation = null;
        if ($identityKey) {
            $conversation = (clone $query)->where('identity_key', $identityKey)->first();
        }

        if (! $conversation) {
            $conversation = (clone $query)
                ->where('external_id', $waId)
                ->where('channel', 'whatsapp')
                ->when(
                    $connectionId,
                    fn ($q) => $q->where(function ($inner) use ($connectionId): void {
                        $inner->whereNull('channel_connection_id')
                            ->orWhere('channel_connection_id', $connectionId);
                    }),
                )
                ->first();
        }

        if ($conversation) {
            $updates = [
                'customer_id' => $conversation->customer_id ?: $customerId,
                'channel' => 'whatsapp',
            ];
            if ($connectionId && ! $conversation->channel_connection_id) {
                $updates['channel_connection_id'] = $connectionId;
            }
            $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
            $metadata['channel_source'] = 'whatsapp';
            if ($connection?->external_account_id) {
                $metadata['phone_number_id'] = $connection->external_account_id;
            }
            $updates['metadata'] = $metadata;
            $conversation->forceFill($updates)->save();

            return $conversation->fresh() ?? $conversation;
        }

        return $this->create([
            'workspace_id' => $workspaceId,
            'customer_id' => $customerId,
            'channel' => 'whatsapp',
            'channel_connection_id' => $connectionId,
            'external_id' => $waId,
            'status' => 'open',
            'ai_enabled' => true,
            'last_message_at' => now(),
            'metadata' => [
                'channel_source' => 'whatsapp',
                'phone_number_id' => $connection?->external_account_id,
            ],
        ]);
    }
}
