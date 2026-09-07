<?php

namespace App\Services\Mobile;

use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Communication\MessageService;
use App\Services\Communication\UnreadService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class ConversationInboxService
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly UnreadService $unreadService,
    ) {}

    /**
     * @param  array{filter?:string,channel?:string,search?:string,per_page?:int}  $filters
     */
    public function listForUser(User $user, Workspace $workspace, array $filters = []): CursorPaginator
    {
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 20)));
        $filter = (string) ($filters['filter'] ?? 'all');
        $channel = trim((string) ($filters['channel'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Conversation::query()
            ->with([
                'customer:id,name,phone,email',
                'messages' => fn ($q) => $q->latest('id')->limit(1),
            ])
            ->leftJoin('conversation_user_states as cus', function ($join) use ($user): void {
                $join->on('cus.conversation_id', '=', 'conversations.id')
                    ->where('cus.user_id', '=', $user->id);
            })
            ->select('conversations.*')
            ->addSelect([
                'cus.last_read_message_id',
                'cus.muted_at as user_muted_at',
                'cus.archived_at as user_archived_at',
            ]);

        if ($filter === 'archived') {
            $query->whereNotNull('cus.archived_at');
        } else {
            $query->where(function (Builder $q): void {
                $q->whereNull('cus.archived_at');
            });
        }

        if ($filter === 'unread') {
            $query->where(function (Builder $q): void {
                $q->whereNull('cus.last_read_message_id')
                    ->orWhereRaw('cus.last_read_message_id < (select max(m.id) from messages m where m.conversation_id = conversations.id and m.direction = ?)', ['inbound']);
            });
        }

        if ($channel !== '') {
            $query->where('conversations.channel', $channel);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('conversations.external_id', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function (Builder $customer) use ($search): void {
                        $customer->where('name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('messages', function (Builder $messages) use ($search): void {
                        $messages->where('content', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id')
            ->cursorPaginate($perPage);
    }

    public function unreadConversationCount(User $user, Workspace $workspace): int
    {
        return (int) Conversation::query()
            ->leftJoin('conversation_user_states as cus', function ($join) use ($user): void {
                $join->on('cus.conversation_id', '=', 'conversations.id')
                    ->where('cus.user_id', '=', $user->id);
            })
            ->where(function (Builder $q): void {
                $q->whereNull('cus.archived_at');
            })
            ->where(function (Builder $q): void {
                $q->whereNull('cus.last_read_message_id')
                    ->orWhereRaw('cus.last_read_message_id < (select max(m.id) from messages m where m.conversation_id = conversations.id and m.direction = ?)', ['inbound']);
            })
            ->whereExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('messages.direction', 'inbound');
            })
            ->count('conversations.id');
    }

    /**
     * @param  array{direction?:string,message_type?:string,content?:string,metadata?:array,idempotency_key?:string}  $data
     */
    public function sendMessage(Conversation $conversation, User $actor, array $data): Message
    {
        $payload = [
            'direction' => $data['direction'] ?? 'outbound',
            'message_type' => $data['message_type'] ?? 'text',
            'content' => $data['content'] ?? '',
            'customer_id' => $conversation->customer_id,
            'metadata' => array_filter([
                ...((array) ($data['metadata'] ?? [])),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'channel' => $conversation->channel,
            ], fn ($v) => $v !== null),
        ];

        if (! empty($data['idempotency_key'])) {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('metadata->idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $message = $this->messageService->recordOutbound($conversation, $payload, $actor);

        return $message->load(['user', 'customer', 'attachments']);
    }

    public function markRead(Conversation $conversation, User $user, ?int $messageId = null): ConversationUserState
    {
        return $this->unreadService->markRead($conversation, $user, $messageId);
    }

    public function archive(Conversation $conversation, User $user, bool $archived = true): ConversationUserState
    {
        return $this->unreadService->archive($conversation, $user, $archived);
    }

    public function mute(Conversation $conversation, User $user, bool $muted = true): ConversationUserState
    {
        return $this->unreadService->mute($conversation, $user, $muted);
    }

    public function messages(Conversation $conversation, int $perPage = 30): CursorPaginator
    {
        $perPage = max(1, min(50, $perPage));

        return Message::query()
            ->with(['user:id,name', 'customer:id,name', 'attachments'])
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function unreadCountForConversation(Conversation $conversation, User $user): int
    {
        return $this->unreadService->unreadCountForConversation($conversation, $user);
    }
}
