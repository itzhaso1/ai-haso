<aside
    class="min-h-[70vh] overflow-y-auto bg-[#FCFFFE] px-4 py-4 lg:border-l lg:border-slate-200"
    :class="pane === 'details' ? 'block' : 'hidden lg:block'"
>
    @if($activeConversation)
        <div class="mb-3 lg:hidden">
            <button type="button" @click="pane = 'thread'" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">العودة للمحادثة</button>
        </div>

        @php
            $customer = $activeConversation->customer;
            $identities = $customer?->channelIdentities ?? collect();
            $conversationIdentity = $activeConversation->channelIdentities->first();
        @endphp

        <section class="space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Customer</h3>
                <p class="mt-1 text-xs text-slate-500">مصدر البيانات: CRM Customer. لا يتم إنشاء عميل من Communication Center.</p>
            </div>
            @if($customer)
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-[11px] text-slate-500">Name</dt>
                        <dd class="font-semibold text-slate-900">{{ $customer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-slate-500">Phone</dt>
                        <dd>{{ $customer->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-slate-500">Email</dt>
                        <dd>{{ $customer->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-slate-500">WhatsApp</dt>
                        <dd>{{ $customer->whatsapp ?: '—' }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-slate-500">لا يوجد عميل CRM مرتبط بهذه المحادثة.</p>
            @endif
        </section>

        <section class="mt-6 space-y-2">
            <h3 class="text-sm font-semibold text-slate-900">Channel identities</h3>
            @if($conversationIdentity)
                <div class="rounded-xl border border-[#BDEFE5] bg-[#F3FCFA] px-3 py-2 text-sm">
                    <p class="text-[11px] font-semibold text-[#067e6b]">Identity used for this conversation</p>
                    <p class="mt-1 font-semibold">{{ \App\Support\Communication\ChannelPresentation::label($conversationIdentity->channel) }}</p>
                    <p class="text-xs text-slate-600">{{ $conversationIdentity->identifier_raw ?: $conversationIdentity->identifier }}</p>
                </div>
            @endif
            @forelse($identities as $identity)
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <p class="font-semibold">{{ \App\Support\Communication\ChannelPresentation::label($identity->channel) }}</p>
                    <p class="text-xs text-slate-600">{{ $identity->identifier_raw ?: $identity->identifier }}</p>
                </div>
            @empty
                <p class="text-xs text-slate-500">لا توجد هويات قنوات مرتبطة بهذا العميل.</p>
            @endforelse
        </section>

        <section class="mt-6 space-y-2 text-sm">
            <h3 class="text-sm font-semibold text-slate-900">Conversation</h3>
            <dl class="space-y-2">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">First contact</dt><dd>{{ optional($activeConversation->first_contact_at)->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Last message</dt><dd>{{ $activeConversation->last_message_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Channel</dt><dd>{{ $activeConversation->channel_label ?? $activeConversation->channel }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Connection</dt><dd>{{ $activeConversation->channelConnection?->display_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Team</dt><dd>{{ $activeConversation->assignedTeam?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Agent</dt><dd>{{ $activeConversation->assignedUser?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Priority</dt><dd class="capitalize">{{ $activeConversation->priority ?? 'normal' }}</dd></div>
            </dl>
        </section>

        @if($canAssign)
            <section class="mt-6 space-y-3 rounded-2xl border border-slate-200 bg-white p-3">
                <h3 class="text-sm font-semibold text-slate-900">Assignment</h3>
                <form method="POST" action="{{ route('workspace.communication.inbox.assign', $activeConversation) }}" class="space-y-2">
                    @csrf
                    @foreach($queryState as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="priority" value="{{ $activeConversation->priority ?? 'normal' }}">
                    <label class="block text-[11px] text-slate-500">Team</label>
                    <select name="assigned_team_id" x-model="teamId" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون فريق</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" @selected((int) $activeConversation->assigned_team_id === (int) $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <label class="block text-[11px] text-slate-500">Agent</label>
                    <select name="assigned_user_id" x-model="agentId" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون موظف</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((int) $activeConversation->assigned_user_id === (int) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500">إذا اخترت فريقاً وموظفاً معاً، يجب أن يكون الموظف عضواً في الفريق.</p>
                    <div class="flex flex-wrap gap-2">
                        <button class="rounded-lg bg-[#06C2A4] px-3 py-1.5 text-xs font-semibold text-white">{{ $activeConversation->assigned_user_id || $activeConversation->assigned_team_id ? 'Reassign' : 'Assign' }}</button>
                    </div>
                </form>
                @if($activeConversation->assigned_user_id || $activeConversation->assigned_team_id)
                    <form method="POST" action="{{ route('workspace.communication.inbox.unassign', $activeConversation) }}">
                        @csrf
                        @foreach($queryState as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Unassign</button>
                    </form>
                @endif
            </section>
        @endif

        @if($canAssign)
            <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                <h3 class="text-sm font-semibold text-slate-900">Priority</h3>
                <form method="POST" action="{{ route('workspace.communication.inbox.priority', $activeConversation) }}" class="mt-2 flex gap-2">
                    @csrf
                    @method('PUT')
                    @foreach($queryState as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="priority" class="flex-1 rounded-lg border-slate-300 text-sm">
                        @foreach(['low','normal','high','urgent'] as $priority)
                            <option value="{{ $priority }}" @selected(($activeConversation->priority ?? 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">حفظ</button>
                </form>
            </section>
        @endif

        @can('update', $activeConversation)
            <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                <h3 class="text-sm font-semibold text-slate-900">Status</h3>
                <form method="POST" action="{{ route('workspace.communication.inbox.status', $activeConversation) }}" class="mt-2 flex gap-2">
                    @csrf
                    @method('PUT')
                    @foreach($queryState as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="status" class="flex-1 rounded-lg border-slate-300 text-sm">
                        @foreach(['open','closed','archived'] as $status)
                            <option value="{{ $status }}" @selected($activeConversation->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold">حفظ</button>
                </form>
            </section>
        @endcan
    @else
        <x-empty-state title="لا توجد تفاصيل" text="اختر محادثة لعرض بيانات العميل من CRM." />
    @endif
</aside>
