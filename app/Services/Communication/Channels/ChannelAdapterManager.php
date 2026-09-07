<?php

namespace App\Services\Communication\Channels;

use App\Services\Communication\Channels\Adapters\ComingSoonAdapter;
use App\Services\Communication\Channels\Adapters\EmailAdapter;
use App\Services\Communication\Channels\Adapters\InternalAdapter;
use App\Services\Communication\Channels\Adapters\WhatsAppAdapter;

class ChannelAdapterManager
{
    public function __construct(
        private readonly WhatsAppAdapter $whatsAppAdapter,
        private readonly EmailAdapter $emailAdapter,
        private readonly InternalAdapter $internalAdapter,
    ) {}

    public function for(string $channel): ChannelAdapterInterface
    {
        return match ($channel) {
            'whatsapp' => $this->whatsAppAdapter,
            'email' => $this->emailAdapter,
            'manual' => $this->internalAdapter,
            default => new ComingSoonAdapter($channel),
        };
    }
}
