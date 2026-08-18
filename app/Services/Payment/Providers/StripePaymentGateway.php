<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentLink(string $reference, float $amount, string $currency, array $metadata = []): array
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret is not configured.');
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/payment_links', [
                'line_items[0][price_data][currency]' => strtolower($currency),
                'line_items[0][price_data][product_data][name]' => 'Order '.$reference,
                'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
                'line_items[0][quantity]' => 1,
                'metadata[reference]' => $reference,
            ])
            ->throw()
            ->json();

        return [
            'provider_payment_id' => (string) ($response['id'] ?? Str::lower(Str::random(24))),
            'payment_link' => (string) ($response['url'] ?? ''),
            'payload' => $response,
        ];
    }

    public function verifyWebhook(array $headers, array $payload): array
    {
        $eventId = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        $reference = $payload['data']['object']['metadata']['reference'] ?? null;

        return [
            'verified' => true,
            'event_id' => $eventId,
            'status' => $type === 'checkout.session.completed' ? 'paid' : 'pending',
            'reference' => $reference,
            'payload' => $payload,
            'reason' => null,
        ];
    }
}
