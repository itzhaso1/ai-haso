<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(private readonly ConversationService $conversationService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->with('customer')
            ->withCount('messages')
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where('external_id', 'like', '%'.$search.'%');
            })
            ->latest('last_message_at')
            ->paginate(12)
            ->withQueryString();

        return view('workspace.conversations.index', compact('conversations'));
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

        return redirect()->route('workspace.conversations.edit', $conversation)->with('success', 'تم إنشاء المحادثة.');
    }

    public function edit(Conversation $conversation): View
    {
        $this->authorize('update', $conversation);

        return view('workspace.conversations.edit', [
            'conversation' => $conversation->load(['customer', 'messages.user']),
            'metadataJson' => json_encode($conversation->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $payload = $request->validate([
            'status' => ['nullable', 'in:open,closed,archived'],
            'ai_enabled' => ['nullable', 'boolean'],
            'metadata_json' => ['nullable', 'string'],
        ]);

        $conversation->update([
            'status' => $payload['status'] ?? $conversation->status,
            'ai_enabled' => array_key_exists('ai_enabled', $payload) ? (bool) $payload['ai_enabled'] : $conversation->ai_enabled,
            'metadata' => $this->parseJsonField($request, 'metadata_json', $conversation->metadata ?? []),
        ]);

        return redirect()->route('workspace.conversations.edit', $conversation)->with('success', 'تم تحديث المحادثة.');
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('workspace.conversations.index')->with('success', 'تم حذف المحادثة.');
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $payload = $request->validated();
        $payload['conversation_id'] = $conversation->id;
        $payload['customer_id'] = $payload['customer_id'] ?? $conversation->customer_id;
        $payload['message_type'] = $payload['message_type'] ?? 'text';
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json');

        $this->conversationService->addMessage($conversation, $payload, $request->user());

        return redirect()->route('workspace.conversations.edit', $conversation)->with('success', 'تم إرسال الرسالة.');
    }
}
