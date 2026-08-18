<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class LocalPaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentLink(string $reference, float $amount, string $currency, array $metadata = []): array
    {
        $paymentId = 'local_'.Str::lower(Str::random(24));
        $token = Str::random(48);

        return [
            'provider_payment_id' => $paymentId,
            'payment_link' => url('/pay/local/'.$token),
            'payload' => [
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
            ],
        ];
    }

    public function verifyWebhook(array $headers, array $payload): array
    {
        return [
            'verified' => true,
            'event_id' => (string) ($payload['event_id'] ?? Str::uuid()->toString()),
            'status' => $payload['status'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'payload' => $payload,
            'reason' => null,
        ];
    }
}
