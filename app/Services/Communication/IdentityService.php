<?php

namespace App\Services\Communication;

use App\Models\Communication\CustomerChannelIdentity;
use App\Models\Customer;
use App\Services\Audit\AuditLogService;
use App\Support\Communication\ChannelIdentifier;
use Illuminate\Support\Facades\Log;

class IdentityService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{customer:Customer,identity:CustomerChannelIdentity,created:bool}
     */
    public function resolve(
        int $workspaceId,
        string $channel,
        string $identifierRaw,
        ?int $connectionId = null,
        ?int $conversationId = null,
    ): array {
        $channel = ChannelIdentifier::normalizeChannelName($channel);
        $identifier = ChannelIdentifier::normalize($channel, $identifierRaw);

        $existing = CustomerChannelIdentity::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->where('identifier', $identifier)
            ->first();

        if ($existing) {
            $customer = Customer::withoutGlobalScopes()->find($existing->customer_id);
            if ($customer) {
                return ['customer' => $customer, 'identity' => $existing, 'created' => false];
            }
        }

        $customer = $this->matchCustomerColumn($workspaceId, $channel, $identifier, $identifierRaw);
        if ($customer) {
            $identity = $this->remember(
                $workspaceId,
                $customer,
                $channel,
                $identifier,
                $identifierRaw,
                'customer_column',
                $connectionId,
                $conversationId,
            );

            return ['customer' => $customer, 'identity' => $identity, 'created' => false];
        }

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Customer '.$identifierRaw,
            'phone' => in_array($channel, ['whatsapp', 'sms'], true) ? $identifierRaw : null,
            'whatsapp' => $channel === 'whatsapp' ? $identifierRaw : null,
            'email' => $channel === 'email' ? $identifierRaw : null,
        ]);

        $identity = $this->remember(
            $workspaceId,
            $customer,
            $channel,
            $identifier,
            $identifierRaw,
            'created_from_channel',
            $connectionId,
            $conversationId,
        );

        return ['customer' => $customer, 'identity' => $identity, 'created' => true];
    }

    private function matchCustomerColumn(int $workspaceId, string $channel, string $identifier, string $raw): ?Customer
    {
        $query = Customer::withoutGlobalScopes()->where('workspace_id', $workspaceId);

        if (in_array($channel, ['whatsapp', 'sms'], true)) {
            return $query->where(function ($inner) use ($raw, $identifier): void {
                $inner->where('whatsapp', $raw)
                    ->orWhere('phone', $raw)
                    ->orWhere('whatsapp', $identifier)
                    ->orWhere('phone', $identifier);
            })->first();
        }

        if ($channel === 'email') {
            return $query->whereRaw('lower(email) = ?', [strtolower($raw)])->first();
        }

        return null;
    }

    private function remember(
        int $workspaceId,
        Customer $customer,
        string $channel,
        string $identifier,
        string $raw,
        string $matchRule,
        ?int $connectionId,
        ?int $conversationId,
    ): CustomerChannelIdentity {
        $identity = CustomerChannelIdentity::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'channel' => $channel,
                'identifier' => $identifier,
            ],
            [
                'customer_id' => $customer->id,
                'identifier_raw' => $raw,
                'channel_connection_id' => $connectionId,
                'conversation_id' => $conversationId,
                'match_rule' => $matchRule,
                'matched_at' => now(),
                'metadata' => [],
            ],
        );

        if ((int) $identity->customer_id !== (int) $customer->id) {
            Log::warning('communication.identity.collision', [
                'workspace_id' => $workspaceId,
                'channel' => $channel,
                'identifier' => $identifier,
                'existing_customer_id' => $identity->customer_id,
                'incoming_customer_id' => $customer->id,
            ]);

            return $identity;
        }

        $this->auditLogService->log(
            action: 'communication.identity.linked',
            entityType: 'customer_channel_identity',
            entityId: $identity->id,
            newValues: [
                'customer_id' => $customer->id,
                'channel' => $channel,
                'match_rule' => $matchRule,
            ],
            workspaceId: $workspaceId,
        );

        return $identity;
    }
}
