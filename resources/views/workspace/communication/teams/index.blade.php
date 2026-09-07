<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Communication Center · Teams</h2>
            <p class="mt-1 text-xs text-slate-500">الفرق تجمّع موظفي مساحة العمل الحاليين. لا يوجد نظام موظفين مستقل.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @include('workspace.partials.nav')
        @include('partials.flash')

        <form method="POST" action="{{ route('workspace.communication.teams.store') }}" class="rounded-2xl border bg-white p-5 space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">فريق جديد</h3>
            <input name="name" required class="w-full rounded-xl border-gray-300 text-sm" placeholder="اسم الفريق">
            <select name="user_ids[]" multiple class="w-full rounded-xl border-gray-300 text-sm min-h-[120px]">
                @foreach($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white">إنشاء</button>
        </form>

        <div class="space-y-4">
            @forelse($teams as $team)
                <form method="POST" action="{{ route('workspace.communication.teams.update', $team) }}" class="rounded-2xl border bg-white p-5 space-y-3">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $team->name }}" class="w-full rounded-xl border-gray-300 text-sm">
                    <select name="user_ids[]" multiple class="w-full rounded-xl border-gray-300 text-sm min-h-[120px]">
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected($team->members->contains('id', $member->id))>{{ $member->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">حفظ</button>
                </form>
            @empty
                <p class="text-sm text-slate-500">لا توجد فرق بعد.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
