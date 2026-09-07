<?php

namespace App\Support\Communication;

final class ChannelIdentifier
{
    public static function normalize(string $channel, string $identifier): string
    {
        $identifier = trim($identifier);
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'whatsapp', 'sms' => preg_replace('/\D+/', '', $identifier) ?: $identifier,
            'email' => strtolower($identifier),
            default => $identifier,
        };
    }

    public static function normalizeChannelName(string $channel): string
    {
        $normalized = strtolower(trim($channel));

        return match ($normalized) {
            'facebook', 'messenger', 'facebook-messenger' => 'facebook_messenger',
            'ig' => 'instagram',
            'webchat', 'web-chat' => 'web_chat',
            default => $normalized !== '' ? $normalized : 'manual',
        };
    }
}
