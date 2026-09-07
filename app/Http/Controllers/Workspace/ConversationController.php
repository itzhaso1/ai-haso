<?php

namespace App\Http\Controllers\Workspace;

use App\Exceptions\ChannelUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Communication\InboxController;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Jobs\ProcessAIResponse;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Communication\AssignmentService;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ConversationController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): View
    {
        return app(InboxController::class)->index($request);
    }

    public function create(): View
    {
        $this->authorize('create', Conversation::class);

        return view('workspace.conversations.create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $payload = $request->validated();
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json');
        $payload['status'] = $payload['status'] ?? 'open';
        $payload['ai_enabled'] = (bool) ($payload['ai_enabled'] ?? true);

        $conversation = $this->conversationService->create($payload);

        return redirect()->route('workspace.conversations.index', [
            'conversation' => $conversation->id,
        ])->with('success', 'تم إنشاء المحادثة.');
    }

    public function edit(Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        return redirect()->route('workspace.conversations.index', [
            'conversation' => $conversation->id,
        ]);
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $payload = $request->validate([
            'status' => ['nullable', 'in:open,closed,archived'],
            'ai_enabled' => ['nullable', 'boolean'],
            'metadata_json' => ['nullable', 'string'],
            'assigned_team_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        if ($request->hasAny(['assigned_team_id', 'assigned_user_id', 'priority'])) {
            $this->authorize('assign', $conversation);
            try {
                $this->assignmentService->assign(
                    $conversation,
                    $request->filled('assigned_team_id') ? (int) $request->input('assigned_team_id') : null,
                    $request->filled('assigned_user_id') ? (int) $request->input('assigned_user_id') : null,
                    $payload['priority'] ?? $conversation->priority,
                    $request->user(),
                );
            } catch (InvalidArgumentException $exception) {
                return redirect()->route('workspace.conversations.index', [
                    'conversation' => $conversation->id,
                ])->with('error', $exception->getMessage());
            }
        }

        $conversation->update([
            'status' => $payload['status'] ?? $conversation->status,
            'ai_enabled' => array_key_exists('ai_enabled', $payload) ? (bool) $payload['ai_enabled'] : $conversation->ai_enabled,
            'metadata' => $this->parseJsonField($request, 'metadata_json', $conversation->metadata ?? []),
        ]);

        return redirect()->route('workspace.conversations.index', [
            'conversation' => $conversation->id,
        ])->with('success', 'تم تحديث المحادثة.');
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('workspace.conversations.index')->with('success', 'تم حذف المحادثة.');
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('reply', $conversation);

        $payload = $request->validated();
        if (($payload['direction'] ?? '') === 'inbound') {
            return redirect()->route('workspace.conversations.index', [
                'conversation' => $conversation->id,
            ])->with('error', 'لا يمكن إنشاء رسالة واردة من Inbox.');
        }

        $payload['conversation_id'] = $conversation->id;
        $payload['customer_id'] = $payload['customer_id'] ?? $conversation->customer_id;
        $payload['message_type'] = $payload['message_type'] ?? 'text';
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json');

        try {
            $sentMessage = $this->conversationService->addMessage($conversation, $payload, $request->user());
        } catch (ChannelUnavailableException $exception) {
            return redirect()->route('workspace.conversations.index', [
                'conversation' => $conversation->id,
            ])->with('error', $exception->getMessage());
        }

        if ($sentMessage->direction === 'inbound' && $conversation->ai_enabled) {
            ProcessAIResponse::dispatch($conversation->id, $sentMessage->id);
        }

        $flash = match ($sentMessage->delivery_status) {
            Message::DELIVERY_FAILED => ['error', 'Failed: '.($sentMessage->delivery_error ?: 'Send failed.')],
            Message::DELIVERY_PENDING => ['success', 'Sending...'],
            Message::DELIVERY_SENT => ['success', 'Sent'],
            default => ['success', $sentMessage->direction === 'internal_note' ? 'Internal note saved.' : 'Message recorded.'],
        };

        return redirect()->route('workspace.conversations.index', [
            'conversation' => $conversation->id,
        ])->with($flash[0], $flash[1]);
    }
}
