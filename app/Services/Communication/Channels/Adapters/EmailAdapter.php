<?php

namespace App\Services\Communication\Channels\Adapters;

use App\Models\Communication\ChannelConnection;
use App\Services\Communication\Channels\ChannelAdapterInterface;
use App\Services\Communication\Channels\ChannelSendResult;
use App\Services\Communication\Channels\OutboundMessageIntent;
use Illuminate\Support\Facades\Log;

class EmailAdapter implements ChannelAdapterInterface
{
    public function channel(): string
    {
        return 'email';
    }

    public function catalogStatus(): string
    {
        return 'available';
    }

    public function capabilities(?ChannelConnection $connection): array
    {
        return [
            'send_text' => false,
            'send_media' => false,
            'inbound' => false,
            'templates' => false,
        ];
    }

    public function canSend(?ChannelConnection $connection): bool
    {
        return false;
    }

    public function send(OutboundMessageIntent $intent): ChannelSendResult
    {
        Log::info('communication.email.adapter.noop', [
            'conversation_id' => $intent->conversation->id,
            'workspace_id' => $intent->workspace->id,
        ]);

        return ChannelSendResult::notWired('Email Hub remains the send path until the conversational adapter is wired.');
    }
}
