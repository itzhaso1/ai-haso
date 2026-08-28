<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\EmailWebhookEvent;

class ResendWebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): EmailWebhookEvent
    {
        $eventType = (string) ($payload['type'] ?? 'unknown');
        $providerMessageId = $this->extractProviderMessageId($payload);
        $emailLog = null;

        if ($providerMessageId !== '') {
            $emailLog = EmailLog::query()
                ->where('provider', 'resend')
                ->where('provider_message_id', $providerMessageId)
                ->latest('id')
                ->first();
        }

        $event = EmailWebhookEvent::query()->create([
            'email_log_id' => $emailLog?->id,
            'provider' => 'resend',
            'event_type' => $eventType,
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        if ($emailLog) {
            if (in_array($eventType, ['email.delivered', 'delivered'], true)) {
                $emailLog->forceFill([
                    'status' => 'sent',
                    'error' => null,
                ])->save();
            }

            if (in_array($eventType, ['email.bounced', 'bounced'], true)) {
                $emailLog->forceFill([
                    'status' => 'failed',
                    'error' => 'Resend webhook reported bounce.',
                ])->save();
            }
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderMessageId(array $payload): string
    {
        $direct = $payload['message_id'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            $candidate = $data['email_id'] ?? $data['id'] ?? $data['message_id'] ?? null;
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
