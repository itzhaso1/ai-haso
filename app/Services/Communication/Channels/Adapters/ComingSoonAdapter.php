<?php

namespace App\Services\Communication\Channels\Adapters;

use App\Models\Communication\ChannelConnection;
use App\Services\Communication\Channels\ChannelAdapterInterface;
use App\Services\Communication\Channels\ChannelSendResult;
use App\Services\Communication\Channels\OutboundMessageIntent;

class ComingSoonAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly string $channelKey,
    ) {}

    public function channel(): string
    {
        return $this->channelKey;
    }

    public function catalogStatus(): string
    {
        return 'coming_soon';
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
        return ChannelSendResult::unsupported('قناة '.$this->channelKey.' قيد التجهيز.');
    }
}
