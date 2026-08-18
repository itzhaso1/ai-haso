<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $phoneNumberId,
        public readonly string $to,
        public readonly string $message,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = config('services.whatsapp.token');
        if (! $token) {
            Log::warning('WhatsApp token missing; skipping outbound send.', [
                'phone_number_id' => $this->phoneNumberId,
                'to' => $this->to,
            ]);

            return;
        }

        Http::withToken($token)
            ->post(sprintf('https://graph.facebook.com/%s/%s/messages', config('whatsapp.api_version'), $this->phoneNumberId), [
                'messaging_product' => 'whatsapp',
                'to' => $this->to,
                'type' => 'text',
                'text' => [
                    'body' => $this->message,
                ],
            ]);
    }
}
