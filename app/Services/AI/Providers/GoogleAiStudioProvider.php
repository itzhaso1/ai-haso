<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAiStudioProvider implements AiProviderInterface
{
    public function generate(array $messages, string $model, float $temperature, int $maxTokens): array
    {
        $apiKey = config('services.google_ai_studio.key');
        if (! $apiKey) {
            throw new RuntimeException('Google AI Studio API key is not configured.');
        }

        $contents = array_map(static fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [
                ['text' => $message['content']],
            ],
        ], $messages);

        $endpoint = rtrim((string) config('ai.google_ai_studio.base_url'), '/').'/models/'.$model.':generateContent';

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
        ])
            ->post($endpoint, [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens,
                ],
            ])
            ->throw()
            ->json();

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $text = collect($parts)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        return [
            'content' => (string) $text,
            'tokens_used' => (int) ($response['usageMetadata']['totalTokenCount'] ?? 0),
            'raw' => $response,
        ];
    }
}
