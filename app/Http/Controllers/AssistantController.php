<?php

namespace App\Http\Controllers;

use App\Services\AI\WebsiteAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class AssistantController extends Controller
{
    private const MAX_MESSAGES_PER_MINUTE = 8;

    public function chat(Request $request, WebsiteAssistantService $assistantService): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
        ]);

        $rateKey = $this->rateLimitKey($request);
        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_MESSAGES_PER_MINUTE)) {
            $retryAfter = RateLimiter::availableIn($rateKey);

            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى للرسائل. حاول مرة أخرى بعد قليل.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        RateLimiter::hit($rateKey, 60);

        $result = $assistantService->replyWithMeta(
            prompt: $validated['message'],
            request: $request,
            user: $request->user(),
        );

        return response()->json([
            'data' => [
                'reply' => $result['reply'],
                'source' => $result['source'],
                'reason' => $result['reason'],
                'model' => $result['model'],
            ],
        ]);
    }

    private function rateLimitKey(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = (string) $request->ip();

        return 'assistant-chat:'.($userId ? 'user:'.$userId : 'ip:'.$ip);
    }
}
