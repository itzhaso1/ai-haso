@php
    $links = [
        ['label' => 'نظرة عامة', 'route' => 'workspace.appointments.overview', 'active' => ['workspace.appointments.dashboard', 'workspace.appointments.overview']],
        ['label' => 'الحجوزات', 'route' => 'workspace.appointments.bookings.index', 'active' => 'workspace.appointments.bookings.*'],
        ['label' => 'التقويم', 'route' => 'workspace.appointments.calendar.index', 'active' => 'workspace.appointments.calendar.*'],
        ['label' => 'طلبات المواعيد', 'route' => 'workspace.appointments.requests.index', 'active' => 'workspace.appointments.requests.*'],
        ['label' => 'العملاء', 'route' => 'workspace.appointments.customers.index', 'active' => 'workspace.appointments.customers.*'],
        ['label' => 'الإعدادات', 'route' => 'workspace.appointments.settings.index', 'active' => 'workspace.appointments.settings.*'],
    ];
@endphp

<div class="h-full overflow-y-auto px-4 py-5">
    <div class="mb-5 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HASem</p>
        <h2 class="mt-1 text-xl font-extrabold text-slate-900">Appointments</h2>
        <p class="mt-1 text-xs text-slate-500">إدارة يومية واضحة للحجوزات والطلبات والعملاء</p>
    </div>

    <nav class="space-y-1">
        @foreach($links as $link)
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
