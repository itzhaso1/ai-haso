<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication\ChannelConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;

final class OutboundMessageIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly Conversation $conversation,
        public readonly Message $message,
        public readonly string $body,
        public readonly ?ChannelConnection $connection,
        public readonly ?User $actor = null,
        public readonly array $metadata = [],
    ) {}
}
