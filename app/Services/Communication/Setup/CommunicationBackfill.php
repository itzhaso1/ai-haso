<?php

namespace App\Services\Communication\Setup;

use App\Models\Communication\ChannelConnection;
use App\Support\Communication\ChannelIdentifier;
use App\Support\Communication\ConversationIdentityKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommunicationBackfill
{
    public function run(): void
    {
        if (! Schema::hasTable('conversations') || ! Schema::hasTable('channel_connections')) {
            return;
        }

        $this->detectCustomerIdentifierCollisions();
        $this->syncWhatsAppConnections();
        $this->syncEmailConnections();
        $this->backfillConversationChannels();
        $this->attachWhatsAppConnections();
        $this->backfillIdentityKeys();
        $this->backfillIdentitiesFromCustomers();
        $this->backfillIdentitiesFromConversations();
        $this->backfillMessageDeliveryStatus();
        $this->verifyOrThrow();
    }

    public function verifyOrThrow(): void
    {
        if (! Schema::hasColumn('conversations', 'identity_key')) {
            return;
        }

        $missingKeys = (int) DB::table('conversations')
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNull('identity_key')
            ->whereNull('deleted_at')
            ->count();

        if ($missingKeys > 0) {
            throw new \RuntimeException(
                "Communication backfill incomplete: {$missingKeys} conversation(s) have external_id without identity_key."
            );
        }

        $duplicates = DB::table('conversations')
            ->select('workspace_id', 'identity_key', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('identity_key')
            ->whereNull('deleted_at')
            ->groupBy('workspace_id', 'identity_key')
            ->having('aggregate', '>', 1)
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException(
                "Communication backfill failed: {$duplicates} duplicate identity_key group(s)."
            );
        }
    }

    private function detectCustomerIdentifierCollisions(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        foreach (['phone' => 'whatsapp', 'whatsapp' => 'whatsapp', 'email' => 'email'] as $column => $channel) {
            $rows = DB::table('customers')
                ->select('workspace_id', $column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->whereNull('deleted_at')
                ->get();

            $seen = [];
            foreach ($rows as $row) {
                $raw = (string) $row->{$column};
                $identifier = ChannelIdentifier::normalize($channel, $raw);
                if ($identifier === '') {
                    continue;
                }
                $key = $row->workspace_id.'|'.$channel.'|'.$identifier;
                $seen[$key][] = $raw;
            }

            foreach ($seen as $key => $values) {
                if (count($values) < 2) {
                    continue;
                }
                [$workspaceId, $channelName, $identifier] = explode('|', $key, 3);
                $this->recordIssue((int) $workspaceId, 'identity_collision', 'customer', null, [
                    'channel' => $channelName,
                    'identifier' => $identifier,
                    'column' => $column,
                    'occurrences' => count($values),
                ]);
            }
        }
    }

    private function syncWhatsAppConnections(): void
    {
        if (! Schema::hasTable('whats_app_phone_numbers')) {
            return;
        }

        $phones = DB::table('whats_app_phone_numbers as p')
            ->leftJoin('whats_app_accounts as a', 'a.id', '=', 'p.whats_app_account_id')
            ->whereNull('p.deleted_at')
            ->select([
                'p.id',
                'p.workspace_id',
                'p.phone_number_id',
                'p.display_phone_number',
                'p.verified_name',
                'p.status',
                'a.business_account_id',
                'a.display_name as account_name',
                'a.status as account_status',
            ])
            ->get();

        foreach ($phones as $phone) {
            $status = $this->mapConnectionStatus((string) ($phone->status ?: $phone->account_status ?: 'disconnected'));
            $exists = DB::table('channel_connections')
                ->where('workspace_id', $phone->workspace_id)
                ->where('source_type', ChannelConnection::SOURCE_WHATSAPP_PHONE)
                ->where('source_id', $phone->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('channel_connections')->insert([
                'workspace_id' => $phone->workspace_id,
                'channel' => 'whatsapp',
                'display_name' => $phone->verified_name
                    ?: $phone->display_phone_number
                    ?: ($phone->account_name ?: 'WhatsApp'),
                'status' => $status,
                'provider' => 'meta',
                'external_account_id' => $phone->phone_number_id,
                'credentials_ref' => null,
                'capabilities' => json_encode([
                    'send_text' => true,
                    'send_media' => false,
                    'inbound' => true,
                    'templates' => true,
                ]),
                'source_type' => ChannelConnection::SOURCE_WHATSAPP_PHONE,
                'source_id' => $phone->id,
                'connected_at' => $status === ChannelConnection::STATUS_CONNECTED ? now() : null,
                'last_error' => null,
                'metadata' => json_encode([
                    'business_account_id' => $phone->business_account_id,
                    'phone_number_id' => $phone->phone_number_id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncEmailConnections(): void
    {
        if (! Schema::hasTable('email_accounts')) {
            return;
        }

        $accounts = DB::table('email_accounts')->whereNull('deleted_at')->get();
        foreach ($accounts as $account) {
            $exists = DB::table('channel_connections')
                ->where('workspace_id', $account->workspace_id)
                ->where('source_type', ChannelConnection::SOURCE_EMAIL_ACCOUNT)
                ->where('source_id', $account->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('channel_connections')->insert([
                'workspace_id' => $account->workspace_id,
                'channel' => 'email',
                'display_name' => $account->name ?: $account->email,
                'status' => ChannelConnection::STATUS_CONNECTED,
                'provider' => 'imap_smtp',
                'external_account_id' => $account->email,
                'credentials_ref' => null,
                'capabilities' => json_encode([
                    'send_text' => false,
                    'send_media' => false,
                    'inbound' => false,
                    'templates' => false,
                ]),
                'source_type' => ChannelConnection::SOURCE_EMAIL_ACCOUNT,
                'source_id' => $account->id,
                'connected_at' => $account->created_at ?? now(),
                'last_error' => null,
                'metadata' => json_encode(['hub' => 'email', 'wired' => false]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillConversationChannels(): void
    {
        $conversations = DB::table('conversations')->whereNull('deleted_at')->get(['id', 'channel', 'metadata']);
        foreach ($conversations as $conversation) {
            $metadata = $this->decodeMetadata($conversation->metadata);
            $source = ChannelIdentifier::normalizeChannelName((string) ($metadata['channel_source'] ?? $conversation->channel ?? 'manual'));
            if ($source === (string) $conversation->channel) {
                continue;
            }

            DB::table('conversations')->where('id', $conversation->id)->update([
                'channel' => $source,
                'updated_at' => now(),
            ]);
        }
    }

    private function attachWhatsAppConnections(): void
    {
        $connections = DB::table('channel_connections')
            ->where('channel', 'whatsapp')
            ->where('source_type', ChannelConnection::SOURCE_WHATSAPP_PHONE)
            ->whereNull('deleted_at')
            ->get();

        $byPhoneNumberId = [];
        $byWorkspace = [];
        foreach ($connections as $connection) {
            $meta = $this->decodeMetadata($connection->metadata);
            $phoneNumberId = (string) ($meta['phone_number_id'] ?? $connection->external_account_id ?? '');
            if ($phoneNumberId !== '') {
                $byPhoneNumberId[$connection->workspace_id.'|'.$phoneNumberId] = $connection->id;
            }
            $byWorkspace[$connection->workspace_id][] = $connection;
        }

        $conversations = DB::table('conversations')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->where('channel', 'whatsapp')
                    ->orWhere('metadata', 'like', '%whatsapp%');
            })
            ->get(['id', 'workspace_id', 'channel', 'external_id', 'channel_connection_id', 'metadata']);

        foreach ($conversations as $conversation) {
            if ($conversation->channel !== 'whatsapp') {
                continue;
            }
            if ($conversation->channel_connection_id) {
                continue;
            }

            $metadata = $this->decodeMetadata($conversation->metadata);
            $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');
            $connectionId = null;

            if ($phoneNumberId !== '') {
                $connectionId = $byPhoneNumberId[$conversation->workspace_id.'|'.$phoneNumberId] ?? null;
                if ($connectionId === null) {
                    $this->recordIssue((int) $conversation->workspace_id, 'missing_connection', 'conversation', (int) $conversation->id, [
                        'phone_number_id' => $phoneNumberId,
                    ]);
                }
            } else {
                $workspaceConnections = $byWorkspace[$conversation->workspace_id] ?? [];
                if (count($workspaceConnections) === 1) {
                    $connectionId = $workspaceConnections[0]->id;
                } elseif (count($workspaceConnections) > 1) {
                    $this->recordIssue((int) $conversation->workspace_id, 'ambiguous_connection', 'conversation', (int) $conversation->id, [
                        'connection_count' => count($workspaceConnections),
                    ]);
                }
            }

            if ($connectionId) {
                DB::table('conversations')->where('id', $conversation->id)->update([
                    'channel_connection_id' => $connectionId,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function backfillIdentityKeys(): void
    {
        $conversations = DB::table('conversations')
            ->whereNull('deleted_at')
            ->get(['id', 'channel_connection_id', 'channel', 'external_id']);

        foreach ($conversations as $conversation) {
            $key = ConversationIdentityKey::make(
                $conversation->channel_connection_id ? (int) $conversation->channel_connection_id : null,
                (string) ($conversation->channel ?: 'manual'),
                $conversation->external_id,
            );

            DB::table('conversations')->where('id', $conversation->id)->update([
                'identity_key' => $key,
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillIdentitiesFromCustomers(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('customer_channel_identities')) {
            return;
        }

        $customers = DB::table('customers')->whereNull('deleted_at')->get(['id', 'workspace_id', 'phone', 'whatsapp', 'email']);
        foreach ($customers as $customer) {
            $this->insertIdentity(
                (int) $customer->workspace_id,
                (int) $customer->id,
                'whatsapp',
                (string) ($customer->whatsapp ?: $customer->phone ?: ''),
                'customer_column_backfill',
            );
            if (filled($customer->email)) {
                $this->insertIdentity(
                    (int) $customer->workspace_id,
                    (int) $customer->id,
                    'email',
                    (string) $customer->email,
                    'customer_column_backfill',
                );
            }
        }
    }

    private function backfillIdentitiesFromConversations(): void
    {
        $conversations = DB::table('conversations')
            ->whereNull('deleted_at')
            ->whereNotNull('customer_id')
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->get(['id', 'workspace_id', 'customer_id', 'channel', 'external_id', 'channel_connection_id']);

        foreach ($conversations as $conversation) {
            $this->insertIdentity(
                (int) $conversation->workspace_id,
                (int) $conversation->customer_id,
                (string) $conversation->channel,
                (string) $conversation->external_id,
                'conversation_backfill',
                $conversation->channel_connection_id ? (int) $conversation->channel_connection_id : null,
                (int) $conversation->id,
            );
        }
    }

    private function insertIdentity(
        int $workspaceId,
        int $customerId,
        string $channel,
        string $raw,
        string $matchRule,
        ?int $connectionId = null,
        ?int $conversationId = null,
    ): void {
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }

        $channel = ChannelIdentifier::normalizeChannelName($channel);
        $identifier = ChannelIdentifier::normalize($channel, $raw);
        if ($identifier === '') {
            return;
        }

        $existing = DB::table('customer_channel_identities')
            ->where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->where('identifier', $identifier)
            ->first();

        if ($existing) {
            if ((int) $existing->customer_id !== $customerId) {
                $this->recordIssue($workspaceId, 'identity_collision', 'customer_channel_identity', (int) $existing->id, [
                    'channel' => $channel,
                    'identifier' => $identifier,
                    'existing_customer_id' => $existing->customer_id,
                    'incoming_customer_id' => $customerId,
                    'match_rule' => $matchRule,
                ]);
            }

            return;
        }

        DB::table('customer_channel_identities')->insert([
            'workspace_id' => $workspaceId,
            'customer_id' => $customerId,
            'channel' => $channel,
            'identifier' => $identifier,
            'identifier_raw' => $raw,
            'channel_connection_id' => $connectionId,
            'conversation_id' => $conversationId,
            'match_rule' => $matchRule,
            'matched_at' => now(),
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function backfillMessageDeliveryStatus(): void
    {
        if (! Schema::hasColumn('messages', 'delivery_status')) {
            return;
        }

        DB::table('messages')->whereNull('delivery_status')->where('direction', 'internal_note')->update([
            'delivery_status' => 'n_a',
            'updated_at' => now(),
        ]);

        DB::table('messages')->whereNull('delivery_status')->update([
            'delivery_status' => 'sent',
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordIssue(int $workspaceId, string $type, ?string $entityType, ?int $entityId, array $payload): void
    {
        if (! Schema::hasTable('communication_backfill_issues')) {
            return;
        }

        DB::table('communication_backfill_issues')->insert([
            'workspace_id' => $workspaceId,
            'issue_type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (! is_string($metadata) || $metadata === '') {
            return [];
        }
        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mapConnectionStatus(string $status): string
    {
        return match ($status) {
            'connected' => ChannelConnection::STATUS_CONNECTED,
            'error' => ChannelConnection::STATUS_ERROR,
            'pending' => ChannelConnection::STATUS_DISCONNECTED,
            default => ChannelConnection::STATUS_DISCONNECTED,
        };
    }
}
