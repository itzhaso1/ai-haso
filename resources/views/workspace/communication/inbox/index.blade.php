<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Communication Center</h2>
                <p class="mt-1 text-xs text-slate-500">Inbox موحّد للعمليات اليومية — WhatsApp يعمل الآن، وباقي القنوات تظهر Coming Soon حتى تُربط فعلياً.</p>
            </div>
            @include('workspace.communication.partials.subnav')
        </div>
    </x-slot>

    @include('partials.flash')

    @php
        $activePane = $activeConversation ? 'thread' : 'list';
    @endphp

    <div
        class="mx-auto max-w-[1680px]"
        x-data="{
            pane: '{{ $activePane }}',
            sending: false,
            teamId: '{{ $activeConversation?->assigned_team_id }}',
            agentId: '{{ $activeConversation?->assigned_user_id }}',
            composerMode: '{{ data_get($activeConversation?->composer, 'can_send_outbound') ? 'outbound' : 'internal_note' }}',
            teamMembers: {{ \Illuminate\Support\Js::from($teamMemberMap) }}
        }"
    >
        <div class="mb-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            @include('workspace.communication.inbox.partials.filters')
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid min-h-[78vh] lg:grid-cols-[minmax(280px,340px)_minmax(0,1fr)_minmax(260px,320px)]">
                @include('workspace.communication.inbox.partials.conversation-list')
                @include('workspace.communication.inbox.partials.thread')
                @include('workspace.communication.inbox.partials.sidebar')
            </div>
        </div>
    </div>
</x-app-layout>
