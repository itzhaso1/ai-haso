<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\WhatsAppOutboundMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $phoneNumberId,
        public readonly string $to,
        public readonly string $message,
        public readonly ?int $outboundMessageId = null,
    ) {}

    public function handle(): void
    {
        $outbound = $this->outboundMessage();

        if ($outbound) {
            $outbound->forceFill([
                'status' => WhatsAppOutboundMessage::STATUS_SENDING,
                'attempts' => (int) $outbound->attempts + 1,
            ])->save();
        }

        $token = config('services.whatsapp.token');
        if (! $token) {
            Log::warning('WhatsApp token missing; skipping outbound send.', [
                'phone_number_id' => $this->phoneNumberId,
                'to' => $this->to,
                'outbound_message_id' => $this->outboundMessageId,
            ]);

            $this->markFailed($outbound, 'WhatsApp permanent token is not configured.');

            return;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('whatsapp.api_version', 'v20.0'),
            $this->phoneNumberId,
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->to,
            'type' => 'text',
            'text' => [
                'body' => $this->message,
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $body = $response->json();
            if (! is_array($body)) {
                $body = ['raw' => $response->body()];
            }

            if ($response->successful()) {
                $providerMessageId = data_get($body, 'messages.0.id');

                if ($outbound) {
                    $outbound->forceFill([
                        'status' => WhatsAppOutboundMessage::STATUS_SENT,
                        'provider_message_id' => is_string($providerMessageId) ? $providerMessageId : null,
                        'provider_response' => $body,
                        'last_error' => null,
                        'sent_at' => now(),
                        'failed_at' => null,
                    ])->save();
                    $this->syncMessageDelivery($outbound, Message::DELIVERY_SENT, is_string($providerMessageId) ? $providerMessageId : null);
                }

                return;
            }

            $error = (string) (data_get($body, 'error.message') ?: $response->body() ?: 'WhatsApp Graph API error');
            $this->markFailed($outbound, $error, $body);

            if ($response->serverError() || $response->status() === 429) {
                throw new \RuntimeException($error);
            }
        } catch (Throwable $exception) {
            $this->markFailed($outbound, $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $outbound = $this->outboundMessage();
        if (! $outbound) {
            return;
        }

        $outbound->forceFill([
            'status' => WhatsAppOutboundMessage::STATUS_FAILED,
            'last_error' => $exception?->getMessage() ?: $outbound->last_error,
            'failed_at' => $outbound->failed_at ?? now(),
        ])->save();
        $this->syncMessageDelivery($outbound, Message::DELIVERY_FAILED);
    }

    private function outboundMessage(): ?WhatsAppOutboundMessage
    {
        if (! $this->outboundMessageId) {
            return null;
        }

        return WhatsAppOutboundMessage::withoutGlobalScopes()->find($this->outboundMessageId);
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    private function markFailed(?WhatsAppOutboundMessage $outbound, string $error, ?array $providerResponse = null): void
    {
        if (! $outbound) {
            return;
        }

        $outbound->forceFill([
            'status' => WhatsAppOutboundMessage::STATUS_FAILED,
            'last_error' => $error,
            'provider_response' => $providerResponse ?? $outbound->provider_response,
            'failed_at' => now(),
        ])->save();
        $this->syncMessageDelivery($outbound, Message::DELIVERY_FAILED, error: $error);
    }

    private function syncMessageDelivery(
        WhatsAppOutboundMessage $outbound,
        string $status,
        ?string $externalMessageId = null,
        ?string $error = null,
    ): void {
        if (! $outbound->message_id) {
            return;
        }

        $message = Message::withoutGlobalScopes()->find($outbound->message_id);
        if (! $message) {
            return;
        }

        $message->forceFill([
            'delivery_status' => $status,
            'delivery_error' => $status === Message::DELIVERY_FAILED ? ($error ?: $outbound->last_error) : null,
            'external_message_id' => $externalMessageId ?: $message->external_message_id,
            'delivered_at' => $status === Message::DELIVERY_SENT ? now() : $message->delivered_at,
        ])->save();
    }
}
