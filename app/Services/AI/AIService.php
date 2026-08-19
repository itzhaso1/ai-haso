<?php

namespace App\Services\AI;

use App\Models\AiLog;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;

class AIService
{
    public function __construct(private readonly AiProviderManager $providerManager) {}

    public function generateReply(Conversation $conversation, Message $message): string
    {
        $setting = AiSetting::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->first() ?? new AiSetting([
            'name' => 'AI Assistant',
            'instructions' => 'كن مساعدًا مهذبًا ومختصرًا.',
            'provider' => config('ai.default_provider', 'google_ai_studio'),
            'model' => $this->defaultModel(config('ai.default_provider', 'google_ai_studio')),
            'temperature' => $this->defaultTemperature(config('ai.default_provider', 'google_ai_studio')),
            'max_tokens' => $this->defaultMaxTokens(config('ai.default_provider', 'google_ai_studio')),
        ]);

        $providerName = $this->providerManager->normalize((string) ($setting->provider ?: config('ai.default_provider', 'google_ai_studio')));
        $provider = $this->providerManager->resolve($providerName);

        $products = Product::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('status', 'active')
            ->with(['variants' => fn ($query) => $query
                ->where('workspace_id', $conversation->workspace_id)
                ->where('status', 'active')])
            ->limit(20)
            ->get(['id', 'name', 'description', 'sku', 'price', 'sale_price', 'stock', 'currency', 'brand'])
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'description' => $product->description,
                'sku' => $product->sku,
                'price' => $product->sale_price ?: $product->price,
                'stock' => $product->stock,
                'currency' => $product->currency,
                'brand' => $product->brand,
                'variants' => $product->variants
                    ->map(fn (ProductVariant $variant): array => [
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'attributes' => $variant->attributes ?? [],
                        'price' => $variant->sale_price ?: $variant->price,
                        'stock' => $variant->stock,
                    ])->values()->all(),
            ])->all();

        $messages = [
            [
                'role' => 'system',
                'content' => trim(($setting->instructions ?? '')."\n\n".
                    'قواعد مهمة: استخدم فقط بيانات نفس workspace الحالية ولا تختلق بيانات غير موجودة. '.
                    "إذا كان المنتج غير متوفر أخبر العميل بذلك بوضوح.\n\n".
                    "المنتجات المتاحة داخل نفس مساحة العمل:\n".json_encode($products, JSON_UNESCAPED_UNICODE)),
            ],
            [
                'role' => 'user',
                'content' => $message->content ?? '',
            ],
        ];

        try {
            $result = $provider->generate(
                messages: $messages,
                model: (string) ($setting->model ?: $this->defaultModel($providerName)),
                temperature: (float) ($setting->temperature ?: $this->defaultTemperature($providerName)),
                maxTokens: (int) ($setting->max_tokens ?: $this->defaultMaxTokens($providerName)),
            );

            AiLog::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'type' => 'reply',
                'input_payload' => ['messages' => $messages],
                'output_payload' => $result['raw'],
                'tokens_used' => $result['tokens_used'],
                'status' => 'success',
            ]);

            return $result['content'] ?: 'تعذر إنشاء رد مناسب حالياً.';
        } catch (\Throwable $exception) {
            AiLog::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'type' => 'reply',
                'input_payload' => ['messages' => $messages],
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return 'تعذر إنشاء الرد الآن. حاول مرة أخرى لاحقًا.';
        }
    }

    private function defaultModel(string $provider): string
    {
        return (string) config('ai.'.$provider.'.model', config('ai.openai.model'));
    }

    private function defaultTemperature(string $provider): float
    {
        return (float) config('ai.'.$provider.'.temperature', 0.4);
    }

    private function defaultMaxTokens(string $provider): int
    {
        return (int) config('ai.'.$provider.'.max_tokens', 512);
    }
}
