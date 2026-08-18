<?php

namespace App\Services\AI;

use App\Models\AiLog;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Str;

class AIService
{
    public function __construct(private readonly AiProviderInterface $provider) {}

    public function generateReply(Conversation $conversation, Message $message): string
    {
        $setting = AiSetting::query()->first() ?? new AiSetting([
            'name' => 'AI Assistant',
            'instructions' => 'كن مساعدًا مهذبًا ومختصرًا.',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.4,
            'max_tokens' => 512,
        ]);

        $products = Product::query()
            ->where('status', 'active')
            ->limit(20)
            ->get(['name', 'sku', 'price', 'sale_price', 'stock'])
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $product->sale_price ?: $product->price,
                'stock' => $product->stock,
            ])->all();

        $messages = [
            [
                'role' => 'system',
                'content' => trim(($setting->instructions ?? '')."\n\nالمنتجات المتاحة داخل نفس مساحة العمل:\n".json_encode($products, JSON_UNESCAPED_UNICODE)),
            ],
            [
                'role' => 'user',
                'content' => $message->content ?? '',
            ],
        ];

        try {
            $result = $this->provider->generate(
                messages: $messages,
                model: $setting->model,
                temperature: (float) $setting->temperature,
                maxTokens: (int) $setting->max_tokens
            );

            AiLog::query()->create([
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
}
