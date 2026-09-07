<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Communication Center · Teams</h2>
                <p class="mt-1 text-xs text-slate-500">الفرق تجمع موظفي مساحة العمل الحاليين (`workspace_users`). لا يوجد نموذج موظف مستقل.</p>
            </div>
            @include('workspace.communication.partials.subnav')
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @include('partials.flash')

        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600">
            محادثات غير معيّنة في مساحة العمل: <span class="font-semibold text-slate-900">{{ $workspaceUnassignedCount }}</span>
        </div>

        <form method="POST" action="{{ route('workspace.communication.teams.store') }}" class="rounded-2xl border bg-white p-5 space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">Create Team</h3>
            <input name="name" required class="w-full rounded-xl border-gray-300 text-sm" placeholder="اسم الفريق">
            <label class="block text-xs text-slate-500">الأعضاء (اختياري)</label>
            <select name="user_ids[]" multiple class="w-full min-h-[120px] rounded-xl border-gray-300 text-sm">
                @foreach($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white">إنشاء</button>
        </form>

        <div class="space-y-4">
            @forelse($teams as $team)
                <article class="rounded-2xl border bg-white p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $team->name }}</h3>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2 py-1">{{ $team->members->count() }} members</span>
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-800">{{ (int) $team->active_conversations_count }} active conversations</span>
                                <span class="rounded-full bg-amber-50 px-2 py-1 text-amber-800">{{ (int) $team->unassigned_conversations_count }} unassigned (no agent)</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.communication.teams.update', $team) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $team->name }}" class="w-full rounded-xl border-gray-300 text-sm">
                        @foreach($team->members as $member)
                            <input type="hidden" name="user_ids[]" value="{{ $member->id }}">
                        @endforeach
                        <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Edit Team</button>
                    </form>

                    <div>
                        <h4 class="text-sm font-semibold text-slate-900">Members</h4>
                        <ul class="mt-2 space-y-2">
                            @forelse($team->members as $member)
                                <li class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2 text-sm">
                                    <span>{{ $member->name }}</span>
                                    <form method="POST" action="{{ route('workspace.communication.teams.members.destroy', [$team, $member->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-rose-700">Remove</button>
                                    </form>
                                </li>
                            @empty
                                <li class="text-sm text-slate-500">لا يوجد أعضاء في هذا الفريق.</li>
                            @endforelse
                        </ul>
                        <form method="POST" action="{{ route('workspace.communication.teams.members.store', $team) }}" class="mt-3 flex gap-2">
                            @csrf
                            <select name="user_id" class="flex-1 rounded-xl border-gray-300 text-sm" required>
                                <option value="">Add member</option>
                                @foreach($members as $member)
                                    @if(! $team->members->contains('id', $member->id))
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
                        </form>
                    </div>
                </article>
            @empty
                <x-empty-state title="No team" text="لا توجد فرق بعد. أنشئ فريقاً من موظفي مساحة العمل الحاليين." />
            @endforelse
        </div>
    </div>
</x-app-layout>
