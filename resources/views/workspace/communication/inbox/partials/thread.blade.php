<section
    class="flex min-h-[70vh] flex-col bg-white lg:border-l lg:border-slate-200"
    :class="pane === 'thread' ? 'flex' : 'hidden lg:flex'"
>
    @if($activeConversation)
        @php
            $composer = $activeConversation->composer ?? ['can_send_outbound' => false, 'can_internal_note' => true, 'block_message' => null];
            $threadMessages = $activeConversation->threadMessages ?? collect();
        @endphp
        <header class="border-b border-slate-200 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="mb-2 flex items-center gap-2 lg:hidden">
                        <button type="button" @click="pane = 'list'" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">القائمة</button>
                        <button type="button" @click="pane = 'details'" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">التفاصيل</button>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900">{{ $activeConversation->customer?->name ?? 'عميل بدون اسم' }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                        @include('workspace.communication.inbox.partials.channel-chip', [
                            'channel' => $activeConversation->display_channel ?? $activeConversation->channel,
                            'comingSoon' => (bool) ($activeConversation->channel_coming_soon ?? false),
                        ])
                        @if($activeConversation->channelConnection?->display_name)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $activeConversation->channelConnection->display_name }}</span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 capitalize">{{ $activeConversation->status }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 capitalize">{{ $activeConversation->priority ?? 'normal' }}</span>
                        @if($activeConversation->assignedTeam)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $activeConversation->assignedTeam->name }}</span>
                        @endif
                        @if($activeConversation->assignedUser)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $activeConversation->assignedUser->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 space-y-3 overflow-y-auto bg-[#F4F7F7] px-4 py-5">
            @forelse($threadMessages as $message)
                @include('workspace.communication.inbox.partials.message', ['message' => $message, 'composer' => $composer])
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    لا توجد رسائل في هذه المحادثة حتى الآن.
                </div>
            @endforelse
        </div>

        @include('workspace.communication.inbox.partials.composer')
    @else
        <div class="flex h-full flex-1 items-center justify-center px-6">
            <x-empty-state title="اختر محادثة" text="افتح محادثة من القائمة لعرض السلسلة، التفاصيل، والإرسال." />
        </div>
    @endif
</section>
