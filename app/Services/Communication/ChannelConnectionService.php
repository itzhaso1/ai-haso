<?php

namespace App\Services\Communication;

use App\Models\Communication\ChannelConnection;
use App\Models\EmailAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\Workspace;
use App\Services\Audit\AuditLogService;
use App\Support\Communication\ChannelIdentifier;

class ChannelConnectionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function syncWorkspace(Workspace $workspace): void
    {
        $this->syncWhatsApp($workspace);
        $this->syncEmail($workspace);
    }

    public function syncWhatsApp(Workspace $workspace): void
    {
        $phones = WhatsAppPhoneNumber::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->get();

        foreach ($phones as $phone) {
            $this->syncWhatsAppPhone($phone);
        }
    }

    public function syncWhatsAppPhone(WhatsAppPhoneNumber $phone): ChannelConnection
    {
        $status = $phone->status === 'connected'
            ? ChannelConnection::STATUS_CONNECTED
            : ($phone->status === 'error' ? ChannelConnection::STATUS_ERROR : ChannelConnection::STATUS_DISCONNECTED);

        $previous = ChannelConnection::withoutGlobalScopes()
            ->where('workspace_id', $phone->workspace_id)
            ->where('source_type', ChannelConnection::SOURCE_WHATSAPP_PHONE)
            ->where('source_id', $phone->id)
            ->first();

        $connection = ChannelConnection::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $phone->workspace_id,
                'source_type' => ChannelConnection::SOURCE_WHATSAPP_PHONE,
                'source_id' => $phone->id,
            ],
            [
                'channel' => 'whatsapp',
                'display_name' => $phone->verified_name ?: $phone->display_phone_number,
                'status' => $status,
                'provider' => 'meta',
                'external_account_id' => $phone->phone_number_id,
                'capabilities' => [
                    'send_text' => $status === ChannelConnection::STATUS_CONNECTED,
                    'send_media' => false,
                    'inbound' => true,
                    'templates' => true,
                ],
                'connected_at' => $status === ChannelConnection::STATUS_CONNECTED ? now() : null,
                'metadata' => [
                    'phone_number_id' => $phone->phone_number_id,
                    'whats_app_account_id' => $phone->whats_app_account_id,
                ],
            ],
        );

        $this->auditConnectionChange($previous, $connection);

        return $connection;
    }

    public function syncEmail(Workspace $workspace): void
    {
        $accounts = EmailAccount::withoutGlobalScopes()->where('workspace_id', $workspace->id)->get();
        foreach ($accounts as $account) {
            $previous = ChannelConnection::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('source_type', ChannelConnection::SOURCE_EMAIL_ACCOUNT)
                ->where('source_id', $account->id)
                ->first();

            $connection = ChannelConnection::withoutGlobalScopes()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'source_type' => ChannelConnection::SOURCE_EMAIL_ACCOUNT,
                    'source_id' => $account->id,
                ],
                [
                    'channel' => 'email',
                    'display_name' => $account->name ?: $account->email,
                    'status' => ChannelConnection::STATUS_CONNECTED,
                    'provider' => 'imap_smtp',
                    'external_account_id' => $account->email,
                    'capabilities' => [
                        'send_text' => false,
                        'send_media' => false,
                        'inbound' => false,
                        'templates' => false,
                    ],
                    'connected_at' => now(),
                    'metadata' => ['hub' => 'email', 'wired' => false],
                ],
            );

            $this->auditConnectionChange($previous, $connection);
        }
    }

    public function findWhatsAppByPhoneNumberId(int $workspaceId, string $phoneNumberId): ?ChannelConnection
    {
        return ChannelConnection::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('external_account_id', $phoneNumberId)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(Workspace $workspace): array
    {
        $this->syncWorkspace($workspace);

        $connections = ChannelConnection::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->orderBy('channel')
            ->orderBy('id')
            ->get()
            ->groupBy('channel');

        $catalog = [];
        foreach (config('communication.channels', []) as $key => $meta) {
            $key = ChannelIdentifier::normalizeChannelName((string) $key);
            $channelConnections = $connections->get($key, collect());
            $connected = $channelConnections->first(fn (ChannelConnection $row) => $row->isConnected());
            $catalogStatus = (string) ($meta['catalog_status'] ?? 'coming_soon');

            $status = $catalogStatus === 'coming_soon'
                ? ChannelConnection::STATUS_COMING_SOON
                : ($connected?->status ?? ChannelConnection::STATUS_DISCONNECTED);

            $catalog[] = [
                'key' => $key,
                'name' => $meta['label'] ?? $key,
                'icon' => $meta['icon'] ?? $key,
                'connected' => $status === ChannelConnection::STATUS_CONNECTED,
                'status' => $status,
                'status_text' => $this->statusText($status),
                'hint' => $this->hint($key, $status, $connected),
                'connections' => $channelConnections->values(),
                'primary_action' => $this->primaryAction($key, $status),
            ];
        }

        return $catalog;
    }

    private function statusText(string $status): string
    {
        return match ($status) {
            ChannelConnection::STATUS_CONNECTED => 'Connected',
            ChannelConnection::STATUS_ERROR => 'Error',
            ChannelConnection::STATUS_COMING_SOON => 'Coming Soon',
            default => 'Disconnected',
        };
    }

    private function hint(string $channel, string $status, ?ChannelConnection $connection): string
    {
        if ($status === ChannelConnection::STATUS_COMING_SOON) {
            return 'التكامل الحقيقي غير متوفر بعد.';
        }
        if ($connection && $status === ChannelConnection::STATUS_CONNECTED) {
            return $connection->display_name;
        }
        if ($channel === 'whatsapp') {
            return 'Connect via Meta Embedded Signup';
        }
        if ($channel === 'email') {
            return 'No email account configured yet.';
        }

        return 'Not connected.';
    }

    private function primaryAction(string $channel, string $status): string
    {
        if ($status === ChannelConnection::STATUS_COMING_SOON) {
            return 'Coming Soon';
        }
        if ($channel === 'whatsapp') {
            return $status === ChannelConnection::STATUS_CONNECTED ? 'Reconnect WhatsApp' : 'Connect WhatsApp';
        }
        if ($channel === 'email') {
            return $status === ChannelConnection::STATUS_CONNECTED ? 'Manage Email' : 'Connect Email';
        }

        return 'Manage channel';
    }

    private function auditConnectionChange(?ChannelConnection $previous, ChannelConnection $connection): void
    {
        if ($previous && $previous->status === $connection->status) {
            return;
        }

        $this->auditLogService->log(
            action: 'communication.connection.changed',
            entityType: 'channel_connection',
            entityId: $connection->id,
            oldValues: $previous ? ['status' => $previous->status] : null,
            newValues: [
                'status' => $connection->status,
                'channel' => $connection->channel,
                'source_type' => $connection->source_type,
                'source_id' => $connection->source_id,
            ],
            workspaceId: $connection->workspace_id,
        );
    }
}
