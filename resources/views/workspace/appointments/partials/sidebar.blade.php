@php
    $links = [
        ['label' => 'لوحة المواعيد', 'route' => 'workspace.appointments.dashboard', 'active' => 'workspace.appointments.dashboard'],
    ];
@endphp

<div class="h-full overflow-y-auto px-4 py-5">
    <div class="mb-5 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HASem</p>
        <h2 class="mt-1 text-xl font-extrabold text-slate-900">المواعيد</h2>
        <p class="mt-1 text-xs text-slate-500">تطبيق مستقل لإدارة حجوزات الصيدلية والعيادة وغيرها</p>
    </div>

    <nav class="space-y-1">
        @foreach($links as $link)
            @php
                $isActive = request()->routeIs($link['active']);
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
