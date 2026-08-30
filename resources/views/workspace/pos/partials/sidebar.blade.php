@php
    $operationsLinks = [
        ['label' => 'لوحة الطاولات', 'route' => 'workspace.pos.tables.index', 'active' => ['workspace.pos.dashboard', 'workspace.pos.tables.*']],
        ['label' => 'الكاشير', 'route' => 'workspace.pos.cashier.index', 'active' => 'workspace.pos.cashier.*'],
    ];
@endphp

<div class="h-full overflow-y-auto px-4 py-5">
    <div class="mb-5 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HASem</p>
        <h2 class="mt-1 text-xl font-extrabold text-slate-900">POS / Cashier</h2>
        <p class="mt-1 text-xs text-slate-500">إدارة الطاولات والطلبات من نفس المنتجات الحالية.</p>
    </div>

    <nav class="space-y-1">
        @foreach($operationsLinks as $link)
            @php
                $patterns = is_array($link['active']) ? $link['active'] : [$link['active']];
                $isActive = collect($patterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));
            @endphp
            <a href="{{ route($link['route']) }}"
               class="{{ $isActive ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }} flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition">
                <span>{{ $link['label'] }}</span>
                @if($isActive)
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                @endif
            </a>
        @endforeach
    </nav>
</div>
