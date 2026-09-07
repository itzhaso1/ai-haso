<form method="GET" action="{{ route('workspace.communication.inbox') }}" class="space-y-3">
    @if(($inboxFilter ?? 'all') !== 'all')
        <input type="hidden" name="filter" value="{{ $inboxFilter }}">
    @endif
    <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <input
                name="search"
                value="{{ $search }}"
                class="w-full rounded-xl border-slate-300 py-2.5 pr-3 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4]"
                placeholder="بحث: اسم العميل، الجوال، البريد، External ID، أو رقم المحادثة"
            >
        </div>
        <select name="channel" class="rounded-xl border-slate-300 text-sm focus:border-[#06C2A4] focus:ring-[#06C2A4] lg:w-48">
            @foreach($channelOptions as $value => $label)
                <option value="{{ $value }}" @selected($channelFilter === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-[#06C2A4] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#04a98e]">بحث</button>
        <a href="{{ route('workspace.conversations.create') }}" class="rounded-xl border border-[#06C2A4] px-4 py-2.5 text-center text-sm font-semibold text-[#06C2A4] hover:bg-[#E8FAF6]">محادثة جديدة</a>
    </div>
    <div class="flex flex-wrap gap-1.5">
        @foreach($inboxFilters as $value => $label)
            <a
                href="{{ route('workspace.communication.inbox', array_filter(['filter' => $value === 'all' ? null : $value, 'channel' => $channelFilter ?: null, 'search' => $search ?: null])) }}"
                class="{{ ($inboxFilter ?: 'all') === $value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }} rounded-full px-3 py-1 text-xs font-semibold transition"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</form>
