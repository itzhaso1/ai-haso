<?php

namespace App\Support\Communication;

final class ConversationIdentityKey
{
    public static function make(?int $channelConnectionId, string $channel, ?string $externalId): ?string
    {
        $externalId = trim((string) $externalId);
        if ($externalId === '') {
            return null;
        }

        $channel = strtolower(trim($channel));
        if ($channel === '') {
            $channel = 'manual';
        }

        $scope = $channelConnectionId !== null
            ? 'conn:'.$channelConnectionId
            : 'channel:'.$channel;

        return hash('sha256', $scope.'|'.$externalId);
    }
}
