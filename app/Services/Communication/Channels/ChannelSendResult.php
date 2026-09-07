<?php

namespace App\Services\Communication\Channels;

final class ChannelSendResult
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const NOT_WIRED = 'not_wired';

    public const UNSUPPORTED = 'unsupported';

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $externalMessageId = null,
        public readonly ?string $error = null,
        public readonly array $providerResponse = [],
    ) {}

    public static function sent(?string $externalMessageId = null, array $providerResponse = []): self
    {
        return new self(self::SENT, $externalMessageId, null, $providerResponse);
    }

    public static function failed(string $error, array $providerResponse = []): self
    {
        return new self(self::FAILED, null, $error, $providerResponse);
    }

    public static function notWired(string $error = 'Channel adapter is not wired.'): self
    {
        return new self(self::NOT_WIRED, null, $error);
    }

    public static function unsupported(string $error = 'Channel is coming soon.'): self
    {
        return new self(self::UNSUPPORTED, null, $error);
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT;
    }
}
