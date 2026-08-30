@php
    $workspace = request()->attributes->get('workspace');
    $navLinks = [
        ['label' => 'الكاشير', 'route' => 'workspace.pos.cashier.index', 'active' => ['workspace.pos.cashier.*']],
        ['label' => 'الطاولات', 'route' => 'workspace.pos.tables.index', 'active' => ['workspace.pos.dashboard', 'workspace.pos.tables.*']],
        ['label' => 'Menu', 'route' => 'workspace.pos.menu.index', 'active' => 'workspace.pos.menu.*'],
        ['label' => 'إدارة الأصناف', 'route' => 'workspace.pos.items.index', 'active' => 'workspace.pos.items.*'],
        ['label' => 'الطلبات الجارية', 'route' => 'workspace.pos.orders.running', 'active' => 'workspace.pos.orders.*'],
        ['label' => 'الفواتير', 'route' => 'workspace.pos.invoices.index', 'active' => 'workspace.pos.invoices.*'],
        ['label' => 'التقارير اليومية', 'route' => 'workspace.pos.reports.daily', 'active' => 'workspace.pos.reports.*'],
    ];
@endphp

<nav class="border-b border-slate-200 bg-white px-3 sm:px-6">
    <div class="mx-auto flex max-w-[1500px] items-center gap-2 overflow-x-auto py-3">
        @foreach($navLinks as $link)
            @php
                $patterns = is_array($link['active']) ? $link['active'] : [$link['active']];
                $isActive = collect($patterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));
            @endphp
            <a
                href="{{ route($link['route']) }}"
                class="{{ $isActive ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }} whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition"
            >
                {{ $link['label'] }}
            </a>
        @endforeach

        @if($workspace)
            <a
                href="{{ route('menu.general', ['workspace' => $workspace->slug]) }}"
                target="_blank"
                rel="noopener"
                class="mr-auto whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
            >
                فتح المنيو العام ↗
            </a>
        @endif
    </div>
</nav>
