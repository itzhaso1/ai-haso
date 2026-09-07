<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Models\Conversation;
use App\Services\Communication\AssignmentService;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with(['customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where('external_id', 'like', '%'.$search.'%');
            })
            ->orderByDesc('last_message_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($conversations);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = $this->conversationService->create($request->validated());

        return response()->json(['data' => $conversation], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json([
            'data' => $conversation->load(['customer', 'messages']),
        ]);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'status' => ['nullable', 'in:open,closed,archived'],
            'ai_enabled' => ['nullable', 'boolean'],
            'assigned_team_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        if ($request->exists('assigned_team_id') || $request->exists('assigned_user_id') || $request->exists('priority')) {
            $this->authorize('assign', $conversation);
            $conversation = $this->assignmentService->assign(
                $conversation,
                $request->exists('assigned_team_id')
                    ? ($request->filled('assigned_team_id') ? (int) $request->input('assigned_team_id') : null)
                    : $conversation->assigned_team_id,
                $request->exists('assigned_user_id')
                    ? ($request->filled('assigned_user_id') ? (int) $request->input('assigned_user_id') : null)
                    : $conversation->assigned_user_id,
                $validated['priority'] ?? $conversation->priority,
                $request->user(),
            );
        }

        $conversation->update(array_filter(
            [
                'status' => $validated['status'] ?? null,
                'ai_enabled' => array_key_exists('ai_enabled', $validated) ? (bool) $validated['ai_enabled'] : null,
            ],
            fn ($value) => $value !== null,
        ));

        return response()->json(['data' => $conversation->refresh()]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return response()->json(status: 204);
    }
}
