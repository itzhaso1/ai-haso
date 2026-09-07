<?php

namespace App\Http\Controllers\Workspace\Communication;

use App\Exceptions\ChannelUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Communication\AssignmentService;
use App\Services\Communication\InboxQueryService;
use App\Services\Communication\MessageService;
use App\Support\Communication\ChannelPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InboxController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(
        private readonly InboxQueryService $inboxQueryService,
        private readonly MessageService $messageService,
        private readonly AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $user = $request->user();
        abort_unless($user, 403);

        $conversations = $this->inboxQueryService->paginate($request, $user);
        $queryState = $this->inboxQueryService->queryState($request);
        $activeConversation = null;
        $canReply = false;
        $canAssign = false;

        $activeId = $request->integer('conversation');
        if ($activeId > 0) {
            $activeConversation = $this->inboxQueryService->findThread($activeId, $user);
            if ($activeConversation) {
                $this->authorize('view', $activeConversation);
                $canReply = $user->can('reply', $activeConversation);
                $canAssign = $user->can('assign', $activeConversation);
                $conversations->getCollection()->transform(function (Conversation $conversation) use ($activeConversation) {
                    if ((int) $conversation->id === (int) $activeConversation->id) {
                        $conversation->setAttribute('unread_count', 0);
                    }

                    return $conversation;
                });
            }
        }

        $teams = CommunicationTeam::query()->with('members:id')->orderBy('name')->get(['id', 'name']);

        return view('workspace.communication.inbox.index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'queryState' => $queryState,
            'channelFilter' => $request->string('channel')->toString(),
            'inboxFilter' => $request->string('filter')->toString() ?: 'all',
            'search' => $request->string('search')->toString(),
            'channelOptions' => ChannelPresentation::filterOptions(),
            'inboxFilters' => ChannelPresentation::inboxFilters(),
            'emptyState' => $this->inboxQueryService->emptyStateForFilter($request->string('filter')->toString()),
            'teams' => $teams,
            'agents' => $this->currentWorkspace()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'teamMemberMap' => $teams->mapWithKeys(
                fn (CommunicationTeam $team): array => [$team->id => $team->members->pluck('id')->all()]
            )->all(),
            'canReply' => $canReply,
            'canAssign' => $canAssign,
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('reply', $conversation);
        $conversation->loadMissing('channelConnection');

        $validated = $request->validate([
            'direction' => ['required', 'in:outbound,internal_note'],
            'content' => ['required', 'string'],
            'message_type' => ['nullable', 'in:text,image,file,system'],
        ]);

        $composer = $this->inboxQueryService->composerState($conversation);

        try {
            if ($validated['direction'] === 'internal_note') {
                $message = $this->messageService->recordInternalNote(
                    $conversation,
                    $request->user(),
                    $validated['content'],
                );
            } else {
                if (! $composer['can_send_outbound']) {
                    return $this->inboxRedirect($request, $conversation)
                        ->with('error', (string) ($composer['block_message'] ?: 'Cannot send on this channel.'));
                }

                $message = $this->messageService->recordOutbound($conversation, [
                    'content' => $validated['content'],
                    'message_type' => $validated['message_type'] ?? 'text',
                    'customer_id' => $conversation->customer_id,
                ], $request->user());
            }
        } catch (ChannelUnavailableException $exception) {
            return $this->inboxRedirect($request, $conversation)->with('error', $exception->getMessage());
        }

        $flash = match ($message->delivery_status) {
            Message::DELIVERY_FAILED => ['error', 'Failed: '.($message->delivery_error ?: 'Send failed.')],
            Message::DELIVERY_PENDING => ['success', 'Sending...'],
            Message::DELIVERY_SENT => ['success', 'Sent'],
            default => ['success', $validated['direction'] === 'internal_note' ? 'Internal note saved.' : 'Message recorded.'],
        };

        return $this->inboxRedirect($request, $conversation)->with($flash[0], $flash[1]);
    }

    public function retry(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        $this->authorize('reply', $conversation);
        $conversation->loadMissing('channelConnection');

        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);
        abort_unless((int) $message->workspace_id === (int) $conversation->workspace_id, 404);

        $composer = $this->inboxQueryService->composerState($conversation);
        if (! $composer['can_send_outbound'] || $this->inboxQueryService->displayChannel($conversation) !== 'whatsapp') {
            return $this->inboxRedirect($request, $conversation)
                ->with('error', 'Retry is only available for connected WhatsApp conversations.');
        }

        try {
            $retried = $this->messageService->retryOutbound($message, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->inboxRedirect($request, $conversation)->with('error', $exception->getMessage());
        } catch (ChannelUnavailableException $exception) {
            return $this->inboxRedirect($request, $conversation)->with('error', $exception->getMessage());
        }

        $flash = match ($retried->delivery_status) {
            Message::DELIVERY_FAILED => ['error', 'Retry failed: '.($retried->delivery_error ?: 'Send failed.')],
            Message::DELIVERY_SENT => ['success', 'Sent'],
            Message::DELIVERY_PENDING => ['success', 'Sending...'],
            default => ['success', 'Retry submitted.'],
        };

        return $this->inboxRedirect($request, $conversation)->with($flash[0], $flash[1]);
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('assign', $conversation);

        $validated = $request->validate([
            'assigned_team_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ]);

        try {
            $this->assignmentService->assign(
                $conversation,
                $request->filled('assigned_team_id') ? (int) $validated['assigned_team_id'] : null,
                $request->filled('assigned_user_id') ? (int) $validated['assigned_user_id'] : null,
                $validated['priority'] ?? $conversation->priority,
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->inboxRedirect($request, $conversation)->with('error', $exception->getMessage());
        }

        return $this->inboxRedirect($request, $conversation)->with('success', 'Assignment updated.');
    }

    public function unassign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('assign', $conversation);

        $this->assignmentService->unassign($conversation, $request->user());

        return $this->inboxRedirect($request, $conversation)->with('success', 'Conversation unassigned.');
    }

    public function priority(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('assign', $conversation);

        $validated = $request->validate([
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        try {
            $this->assignmentService->setPriority($conversation, $validated['priority'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->inboxRedirect($request, $conversation)->with('error', $exception->getMessage());
        }

        return $this->inboxRedirect($request, $conversation)->with('success', 'Priority updated.');
    }

    public function status(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'status' => ['required', 'in:open,closed,archived'],
        ]);

        $conversation->update(['status' => $validated['status']]);

        return $this->inboxRedirect($request, $conversation)->with('success', 'Status updated.');
    }

    private function inboxRedirect(Request $request, Conversation $conversation): RedirectResponse
    {
        return redirect()->route('workspace.conversations.index', array_filter([
            ...$this->inboxQueryService->queryState($request),
            'conversation' => $conversation->id,
        ]));
    }
}
