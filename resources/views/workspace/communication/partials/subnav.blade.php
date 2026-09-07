@php
    $tabs = [
        ['label' => 'Inbox', 'route' => 'workspace.communication.inbox', 'active' => ['workspace.communication.inbox', 'workspace.conversations.*']],
        ['label' => 'Teams', 'route' => 'workspace.communication.teams.index', 'active' => ['workspace.communication.teams.*']],
        ['label' => 'Connections', 'route' => 'workspace.communication.connections.index', 'active' => ['workspace.communication.connections.*']],
        ['label' => 'Templates', 'route' => 'workspace.communication.templates.index', 'active' => ['workspace.communication.templates.*']],
    ];
@endphp

<nav class="flex flex-wrap gap-2">
    @foreach($tabs as $tab)
        @php
            $isActive = collect($tab['active'])->contains(fn (string $pattern): bool => request()->routeIs($pattern));
        @endphp
        <a
            href="{{ route($tab['route']) }}"
            class="{{ $isActive ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }} rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-semibold transition"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
