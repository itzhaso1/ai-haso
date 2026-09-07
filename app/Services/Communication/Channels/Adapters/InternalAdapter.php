<?php

namespace App\Services\Communication\Channels\Adapters;

use App\Models\Communication\ChannelConnection;
use App\Services\Communication\Channels\ChannelAdapterInterface;
use App\Services\Communication\Channels\ChannelSendResult;
use App\Services\Communication\Channels\OutboundMessageIntent;

class InternalAdapter implements ChannelAdapterInterface
{
    public function channel(): string
    {
        return 'manual';
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
        return new ChannelSendResult('n_a');
    }
}
