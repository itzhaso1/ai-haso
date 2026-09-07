<aside
    class="flex min-h-[70vh] flex-col border-b border-slate-200 bg-[#F8FBFA] lg:border-b-0 lg:border-l"
    :class="pane === 'list' ? 'flex' : 'hidden lg:flex'"
>
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Conversations</h3>
            <p class="text-[11px] text-slate-500">{{ $conversations->total() }} محادثة</p>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @forelse($conversations as $conversation)
            @php
                $isActive = $activeConversation && (int) $activeConversation->id === (int) $conversation->id;
                $preview = $conversation->latestMessage;
                $unreadCount = (int) ($conversation->unread_count ?? 0);
                $initial = mb_substr($conversation->customer?->name ?? '?', 0, 1);
                $listQuery = array_filter([...$queryState, 'conversation' => $conversation->id]);
            @endphp
            <a
                href="{{ route('workspace.communication.inbox', $listQuery) }}"
                class="{{ $isActive ? 'bg-[#E8FAF6]' : 'hover:bg-white' }} block border-b border-slate-100 px-4 py-3 transition"
                @click="pane = 'thread'"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">{{ $initial }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $conversation->customer?->name ?? 'عميل غير معروف' }}</p>
                            <span class="shrink-0 text-[11px] text-slate-500">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs {{ $unreadCount > 0 ? 'font-semibold text-slate-800' : 'text-slate-500' }}">
                            {{ $preview?->direction === 'internal_note' ? 'ملاحظة داخلية: ' : '' }}{{ $preview?->content ?: 'لا توجد رسائل بعد' }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            @include('workspace.communication.inbox.partials.channel-chip', [
                                'channel' => $conversation->display_channel ?? $conversation->channel,
                                'comingSoon' => (bool) ($conversation->channel_coming_soon ?? false),
                            ])
                            @if($conversation->channelConnection?->display_name)
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600">{{ $conversation->channelConnection->display_name }}</span>
                            @endif
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] capitalize text-slate-600">{{ $conversation->status }}</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] capitalize text-slate-600">{{ $conversation->priority ?? 'normal' }}</span>
                            @if($conversation->assignedUser)
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600">{{ $conversation->assignedUser->name }}</span>
                            @endif
                            @if($conversation->assignedTeam)
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-600">{{ $conversation->assignedTeam->name }}</span>
                            @endif
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{{ (int) $conversation->messages_count }} msgs</span>
                            @if($unreadCount > 0)
                                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="p-6">
                <x-empty-state :title="$emptyState['title']" :text="$emptyState['text']" />
            </div>
        @endforelse
    </div>

    <div class="border-t border-slate-200 px-4 py-3">
        {{ $conversations->onEachSide(1)->links() }}
    </div>
</aside>
