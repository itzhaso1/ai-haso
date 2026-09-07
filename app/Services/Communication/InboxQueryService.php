<?php

namespace App\Services\Communication;

use App\Models\Communication\CommunicationTeam;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Communication\Channels\ChannelAdapterManager;
use App\Support\Communication\ChannelIdentifier;
use App\Support\Communication\ChannelPresentation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InboxQueryService
{
    public const PAGE_SIZE = 20;

    public const THREAD_MESSAGE_LIMIT = 80;

    public function __construct(
        private readonly UnreadService $unreadService,
        private readonly ChannelAdapterManager $adapters,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function queryState(Request $request): array
    {
        return array_filter([
            'search' => $request->string('search')->toString() ?: null,
            'channel' => $request->string('channel')->toString() ?: null,
            'filter' => $request->string('filter')->toString() ?: null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    public function paginate(Request $request, User $user): LengthAwarePaginator
    {
        $search = trim($request->string('search')->toString());
        $channelFilter = $this->normalizeChannelFilter($request->string('channel')->toString());
        $inboxFilter = $this->normalizeInboxFilter($request->string('filter')->toString());

        $query = Conversation::query()
            ->with([
                'customer:id,workspace_id,name,phone,email,whatsapp',
                'assignedUser:id,name',
                'assignedTeam:id,name',
                'channelConnection:id,workspace_id,channel,display_name,status',
                'latestMessage',
            ])
            ->withCount('messages')
            ->when($channelFilter, fn (Builder $builder) => $this->applyChannelFilter($builder, $channelFilter))
            ->when($inboxFilter !== 'all', fn (Builder $builder) => $this->applyInboxFilter($builder, $inboxFilter, $user))
            ->when($search !== '', fn (Builder $builder) => $this->applySearch($builder, $search))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $conversations = $query
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        $unreadCounts = $this->unreadService->unreadCountsForConversations(
            $user,
            $conversations->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $conversations->setCollection(
            $conversations->getCollection()->map(function (Conversation $conversation) use ($unreadCounts) {
                $displayChannel = $this->displayChannel($conversation);
                $conversation->setAttribute('display_channel', $displayChannel);
                $conversation->setAttribute('channel_label', ChannelPresentation::label($displayChannel));
                $conversation->setAttribute('channel_coming_soon', ChannelPresentation::isComingSoon($displayChannel));
                $conversation->setAttribute('unread_count', $unreadCounts[$conversation->id] ?? 0);

                return $conversation;
            })
        );

        return $conversations;
    }

    public function findThread(int $conversationId, User $user): ?Conversation
    {
        $conversation = Conversation::query()
            ->with([
                'customer.channelIdentities',
                'assignedUser:id,name',
                'assignedTeam:id,name',
                'channelConnection',
                'channelIdentities',
            ])
            ->find($conversationId);

        if (! $conversation) {
            return null;
        }

        $this->unreadService->markRead($conversation, $user);

        $displayChannel = $this->displayChannel($conversation);
        $conversation->setAttribute('display_channel', $displayChannel);
        $conversation->setAttribute('channel_label', ChannelPresentation::label($displayChannel));
        $conversation->setAttribute('channel_coming_soon', ChannelPresentation::isComingSoon($displayChannel));
        $conversation->setAttribute('composer', $this->composerState($conversation));
        $conversation->setRelation('threadMessages', $this->threadMessages($conversation));
        $firstContact = Message::query()->where('conversation_id', $conversation->id)->min('created_at');
        $conversation->setAttribute(
            'first_contact_at',
            $firstContact ? Carbon::parse($firstContact) : $conversation->created_at,
        );

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    public function composerState(Conversation $conversation): array
    {
        $channel = $this->displayChannel($conversation);
        $connection = $conversation->channelConnection;
        $canInternalNote = true;

        if ($channel === 'email') {
            return [
                'can_send_outbound' => false,
                'can_internal_note' => $canInternalNote,
                'block_reason' => 'email_not_wired',
                'block_message' => 'Email integration coming soon',
            ];
        }

        if (ChannelPresentation::isComingSoon($channel) && $channel !== 'manual') {
            return [
                'can_send_outbound' => false,
                'can_internal_note' => $canInternalNote,
                'block_reason' => 'coming_soon',
                'block_message' => ChannelPresentation::label($channel).' · Coming Soon',
            ];
        }

        if ($channel === 'whatsapp') {
            $canSend = $this->adapters->for('whatsapp')->canSend($connection);

            return [
                'can_send_outbound' => $canSend,
                'can_internal_note' => $canInternalNote,
                'block_reason' => $canSend ? null : 'whatsapp_disconnected',
                'block_message' => $canSend ? null : 'WhatsApp is not connected for this conversation.',
            ];
        }

        return [
            'can_send_outbound' => false,
            'can_internal_note' => $canInternalNote,
            'block_reason' => $channel === 'manual' ? 'internal_only' : 'coming_soon',
            'block_message' => $channel === 'manual'
                ? 'Internal conversations accept notes only. Customer send is not available.'
                : ChannelPresentation::label($channel).' · Coming Soon',
        ];
    }

    public function displayChannel(Conversation $conversation): string
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $channel = $metadata['channel_source'] ?? $conversation->channel ?? 'manual';

        return ChannelIdentifier::normalizeChannelName((string) $channel);
    }

    public function emptyStateForFilter(string $filter): array
    {
        return match ($this->normalizeInboxFilter($filter)) {
            'unread' => [
                'title' => 'No unread conversations',
                'text' => 'لا توجد محادثات غير مقروءة لك في هذا الصندوق.',
            ],
            'assigned_to_me' => [
                'title' => 'No assigned conversations',
                'text' => 'لا توجد محادثات معيّنة لك حالياً.',
            ],
            'unassigned' => [
                'title' => 'No unassigned conversations',
                'text' => 'لا توجد محادثات غير معيّنة في مساحة العمل هذه.',
            ],
            'my_team' => [
                'title' => 'No team conversations',
                'text' => 'لا توجد محادثات مرتبطة بفرقك، أو أنك لست عضواً في أي فريق.',
            ],
            'high_priority' => [
                'title' => 'No high-priority conversations',
                'text' => 'لا توجد محادثات ذات أولوية عالية أو عاجلة.',
            ],
            'open' => [
                'title' => 'No open conversations',
                'text' => 'لا توجد محادثات مفتوحة حالياً.',
            ],
            'closed' => [
                'title' => 'No closed conversations',
                'text' => 'لا توجد محادثات مغلقة حالياً.',
            ],
            default => [
                'title' => 'No conversations',
                'text' => 'لا توجد محادثات في Communication Center بعد.',
            ],
        };
    }

    private function threadMessages(Conversation $conversation)
    {
        $ids = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::THREAD_MESSAGE_LIMIT)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return Message::query()->whereRaw('1 = 0')->get();
        }

        return Message::query()
            ->with(['user:id,name', 'attachments'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();
    }

    private function normalizeChannelFilter(string $channel): ?string
    {
        if (trim($channel) === '') {
            return null;
        }

        return ChannelIdentifier::normalizeChannelName($channel);
    }

    private function normalizeInboxFilter(string $filter): string
    {
        $filter = strtolower(trim($filter));

        return array_key_exists($filter, ChannelPresentation::inboxFilters()) ? $filter : 'all';
    }

    private function applyChannelFilter(Builder $query, string $channel): void
    {
        $aliases = ChannelPresentation::channelAliases($channel);

        $query->where(function (Builder $channelQuery) use ($aliases): void {
            $channelQuery->whereIn('channel', $aliases);
            foreach ($aliases as $alias) {
                $channelQuery->orWhere('metadata->channel_source', $alias);
            }
        });
    }

    private function applyInboxFilter(Builder $query, string $filter, User $user): void
    {
        match ($filter) {
            'unread' => $this->applyUnreadFilter($query, $user),
            'assigned_to_me' => $query->where('assigned_user_id', $user->id),
            'unassigned' => $query->whereNull('assigned_user_id')->whereNull('assigned_team_id'),
            'my_team' => $this->applyMyTeamFilter($query, $user),
            'high_priority' => $query->whereIn('priority', ['high', 'urgent']),
            'open' => $query->where('status', 'open'),
            'closed' => $query->where('status', 'closed'),
            default => null,
        };
    }

    private function applyUnreadFilter(Builder $query, User $user): void
    {
        $query->whereRaw(
            'exists (
                select 1
                from messages
                left join conversation_user_states as cus
                    on cus.conversation_id = messages.conversation_id
                    and cus.user_id = ?
                where messages.conversation_id = conversations.id
                    and messages.direction = ?
                    and (cus.last_read_message_id is null or messages.id > cus.last_read_message_id)
            )',
            [$user->id, 'inbound'],
        );
    }

    private function applyMyTeamFilter(Builder $query, User $user): void
    {
        $teamIds = CommunicationTeam::query()
            ->whereHas('members', fn (Builder $members) => $members->where('users.id', $user->id))
            ->pluck('id');

        if ($teamIds->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('assigned_team_id', $teamIds);
    }

    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.addcslashes($search, '%_\\').'%';

        $query->where(function (Builder $inner) use ($search, $like): void {
            $inner->where('external_id', 'like', $like)
                ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                    $customerQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('whatsapp', 'like', $like);
                });

            if (ctype_digit($search)) {
                $inner->orWhere('id', (int) $search);
            }
        });
    }
}
