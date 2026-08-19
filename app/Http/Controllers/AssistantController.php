<?php

namespace App\Http\Controllers;

use App\Services\AI\WebsiteAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function chat(Request $request, WebsiteAssistantService $assistantService): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
        ]);

        $reply = $assistantService->reply(
            prompt: $validated['message'],
            request: $request,
            user: $request->user(),
        );

        return response()->json([
            'data' => [
                'reply' => $reply,
            ],
        ]);
    }
}
