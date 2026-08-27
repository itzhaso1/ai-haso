<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Appointments\AppointmentAiActionService;
use App\Services\Workspace\WorkspaceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentAiActionController extends Controller
{
    public function __construct(
        private readonly AppointmentAiActionService $appointmentAiActionService,
        private readonly WorkspaceResolverService $workspaceResolverService,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('appointments.manage')
            || $request->user()?->can('appointments.requests.manage')
            || $request->user()?->can('workspace.manage'),
            403
        );

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(AppointmentAiActionService::ALLOWED_ACTIONS)],
            'payload' => ['nullable', 'array'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $workspace = $this->workspaceResolverService->resolveFromRequest($request, $request->user());
        abort_unless($workspace, 422, 'لا يمكن تحديد مساحة العمل.');

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = Conversation::query()->whereKey((int) $validated['conversation_id'])->first();
        }

        $result = $this->appointmentAiActionService->execute(
            workspace: $workspace,
            action: (string) $validated['action'],
            payload: is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
            actor: $request->user(),
            conversation: $conversation,
        );

        return response()->json(['data' => $result]);
    }
}
