<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Jobs\ProcessAIResponse;
use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Communication\AssignmentService;
use App\Services\Communication\UnreadService;
use App\Services\Conversation\ConversationService;
use App\Support\Communication\ChannelIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly UnreadService $unreadService,
        private readonly AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $search = $request->string('search')->toString();
        $channelFilter = $this->normalizeChannelFilter($request->string('channel')->toString());
        $statusFilter = $request->string('status')->toString();
        $assigneeFilter = $request->string('assignee')->toString();
        $user = $request->user();

        $conversations = Conversation::query()
            ->with([
                'customer',
                'assignedUser:id,name',
                'assignedTeam:id,name',
                'messages' => fn ($query) => $query->latest()->limit(1),
            ])
            ->withCount('messages')
            ->when($channelFilter, function ($query, $channelFilter): void {
                $query->where(function ($channelQuery) use ($channelFilter): void {
                    $channelQuery->where('channel', $channelFilter)
                        ->orWhere('metadata->channel_source', $channelFilter);
                });
            })
            ->when($statusFilter !== '' && in_array($statusFilter, ['open', 'closed', 'archived'], true), function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            })
            ->when($assigneeFilter === 'unassigned', function ($query): void {
                $query->whereNull('assigned_user_id')->whereNull('assigned_team_id');
            })
            ->when($assigneeFilter === 'me' && $user, function ($query) use ($user): void {
                $query->where('assigned_user_id', $user->id);
            })
            ->when($search, function ($query, $search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('external_id', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('last_message_at')
            ->paginate(12)
            ->withQueryString();

        $conversations->setCollection(
            $conversations->getCollection()
                ->map(function (Conversation $conversation) use ($user) {
                    $conversation->setAttribute('display_channel', $this->resolveDisplayChannel($conversation));
                    $conversation->setAttribute(
                        'unread_count',
                        $user ? $this->unreadService->unreadCountForConversation($conversation, $user) : 0,
                    );

                    return $conversation;
                })
                ->values()
        );

        $activeConversationId = $request->integer('conversation');
        if (! $activeConversationId && $conversations->count() > 0) {
            $activeConversationId = (int) $conversations->first()->id;
        }

        $activeConversation = null;
        if ($activeConversationId) {
            $activeConversation = Conversation::query()
                ->with([
                    'customer',
                    'assignedUser:id,name',
                    'assignedTeam:id,name',
                    'channelConnection',
                    'messages' => fn ($query) => $query->with('user')->latest()->limit(80),
                ])
                ->find($activeConversationId);

            if ($activeConversation && $user) {
                $this->unreadService->markRead($activeConversation, $user);
                $activeConversation->setAttribute('display_channel', $this->resolveDisplayChannel($activeConversation));
                $activeConversation->setRelation(
                    'messages',
                    $activeConversation->messages->sortBy('created_at')->values()
                );
            }
        }

        return view('workspace.conversations.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'channelFilter' => $channelFilter,
            'statusFilter' => $statusFilter,
            'assigneeFilter' => $assigneeFilter,
            'teams' => CommunicationTeam::query()->orderBy('name')->get(['id', 'name']),
            'agents' => $this->currentWorkspace()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'availableChannels' => [
                'whatsapp' => 'WhatsApp',
                'instagram' => 'Instagram',
                'facebook_messenger' => 'Facebook Messenger',
                'email' => 'Email',
                'web' => 'Web',
                'manual' => 'Manual',
            ],
        ]);
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
            $this->assignmentService->assign(
                $conversation,
                $request->filled('assigned_team_id') ? (int) $request->input('assigned_team_id') : null,
                $request->filled('assigned_user_id') ? (int) $request->input('assigned_user_id') : null,
                $payload['priority'] ?? $conversation->priority,
                $request->user(),
            );
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
        $this->authorize('update', $conversation);

        $payload = $request->validated();
        $payload['conversation_id'] = $conversation->id;
        $payload['customer_id'] = $payload['customer_id'] ?? $conversation->customer_id;
        $payload['message_type'] = $payload['message_type'] ?? 'text';
        $payload['metadata'] = $this->parseJsonField($request, 'metadata_json');

        $sentMessage = $this->conversationService->addMessage($conversation, $payload, $request->user());

        if ($sentMessage->direction === 'inbound' && $conversation->ai_enabled) {
            ProcessAIResponse::dispatch($conversation->id, $sentMessage->id);
        }

        $flash = match ($sentMessage->delivery_status) {
            Message::DELIVERY_FAILED => ['error', 'تعذر إرسال الرسالة: '.($sentMessage->delivery_error ?: 'فشل الإرسال.')],
            Message::DELIVERY_PENDING => ['success', 'جاري إرسال الرسالة.'],
            default => ['success', 'تم إرسال الرسالة.'],
        };

        return redirect()->route('workspace.conversations.index', [
            'conversation' => $conversation->id,
        ])->with($flash[0], $flash[1]);
    }

    private function normalizeChannelFilter(string $channel): ?string
    {
        if (trim($channel) === '') {
            return null;
        }

        return ChannelIdentifier::normalizeChannelName($channel);
    }

    private function resolveDisplayChannel(Conversation $conversation): string
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $channel = $metadata['channel_source'] ?? $conversation->channel ?? 'manual';

        return ChannelIdentifier::normalizeChannelName((string) $channel);
    }
}
