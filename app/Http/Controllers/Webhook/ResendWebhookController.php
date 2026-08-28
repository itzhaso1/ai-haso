<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Email\ResendWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendWebhookController extends Controller
{
    public function __construct(
        private readonly ResendWebhookService $resendWebhookService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook payload.',
            ], 422);
        }

        // Keep the endpoint open for future signature verification middleware.
        $event = $this->resendWebhookService->handle($payload);

        return response()->json([
            'ok' => true,
            'event_id' => $event->id,
        ], 202);
    }
}
