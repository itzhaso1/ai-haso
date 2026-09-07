<?php

namespace App\Support\Communication;

final class ChannelPresentation
{
    /**
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            '' => 'All Channels',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'web_chat' => 'Web Chat',
            'instagram' => 'Instagram',
            'facebook_messenger' => 'Messenger',
            'telegram' => 'Telegram',
            'sms' => 'SMS',
            'manual' => 'Internal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function inboxFilters(): array
    {
        return [
            'all' => 'All',
            'unread' => 'Unread',
            'assigned_to_me' => 'Assigned to me',
            'unassigned' => 'Unassigned',
            'my_team' => 'My Team',
            'high_priority' => 'High Priority',
            'open' => 'Open',
            'closed' => 'Closed',
        ];
    }

    public static function label(string $channel): string
    {
        $channel = ChannelIdentifier::normalizeChannelName($channel);

        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'web_chat', 'web' => 'Web Chat',
            'instagram' => 'Instagram',
            'facebook_messenger' => 'Messenger',
            'telegram' => 'Telegram',
            'sms' => 'SMS',
            'manual' => 'Internal',
            default => $channel !== '' ? $channel : 'Internal',
        };
    }

    public static function badgeClasses(string $channel): string
    {
        $channel = ChannelIdentifier::normalizeChannelName($channel);

        return match ($channel) {
            'whatsapp' => 'bg-emerald-100 text-emerald-800',
            'email' => 'bg-amber-100 text-amber-800',
            'web_chat', 'web' => 'bg-sky-100 text-sky-800',
            'instagram' => 'bg-pink-100 text-pink-800',
            'facebook_messenger' => 'bg-blue-100 text-blue-800',
            'telegram' => 'bg-cyan-100 text-cyan-800',
            'sms' => 'bg-violet-100 text-violet-800',
            'manual' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public static function isComingSoon(string $channel): bool
    {
        $channel = ChannelIdentifier::normalizeChannelName($channel);
        if ($channel === 'web') {
            $channel = 'web_chat';
        }

        $status = (string) config('communication.channels.'.$channel.'.catalog_status', 'coming_soon');

        return $status === 'coming_soon';
    }

    /**
     * @return list<string>
     */
    public static function channelAliases(string $channel): array
    {
        $channel = ChannelIdentifier::normalizeChannelName($channel);

        return match ($channel) {
            'web_chat', 'web' => ['web_chat', 'web'],
            'facebook_messenger' => ['facebook_messenger', 'messenger'],
            'manual' => ['manual', 'internal'],
            default => [$channel],
        };
    }
}
