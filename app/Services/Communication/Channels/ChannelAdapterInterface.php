<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication\ChannelConnection;

interface ChannelAdapterInterface
{
    public function channel(): string;

    /**
     * available | coming_soon
     */
    public function catalogStatus(): string;

    /**
     * @return array{send_text:bool,send_media:bool,inbound:bool,templates:bool}
     */
    public function capabilities(?ChannelConnection $connection): array;

    public function canSend(?ChannelConnection $connection): bool;

    public function send(OutboundMessageIntent $intent): ChannelSendResult;
}
