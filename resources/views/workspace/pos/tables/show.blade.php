@extends('layouts.pos', ['pageTitle' => $table->name])

@section('content')
    @php($workspace = request()->attributes->get('workspace'))
    @php($menuUrl = route('menu.table', ['workspace' => $workspace->slug, 'token' => $table->qr_token]))
    <section class="grid gap-4 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ $table->name }}</h2>
                    <p class="text-sm {{ $table->status === 'occupied' ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $table->status === 'occupied' ? '🔴 Occupied' : '🟢 Available' }}
                    </p>
                    @if($currentSession)
                        <p class="mt-1 text-xs text-slate-600" data-opened-at="{{ optional($currentSession->opened_at)->toIso8601String() }}">00:00:00</p>
                    @endif
                </div>
                <a href="{{ route('workspace.pos.tables.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
            </div>

            <div class="space-y-3">
                @forelse($table->orders as $order)
                    <article class="rounded-xl border border-slate-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">#{{ $order->order_number }}</p>
                            <p class="text-xs text-slate-500">{{ $posStatuses[$order->pos_status] ?? $order->pos_status }} • {{ strtoupper($order->source) }}</p>
                        </div>
                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                            @foreach($order->items as $item)
                                <li>{{ $item->product_name }} × {{ $item->quantity }} = {{ number_format((float) $item->total_amount, 2) }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-sm font-bold text-slate-900">Total: {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</p>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">لا توجد طلبات على هذه الطاولة حتى الآن.</p>
                @endforelse
            </div>
        </article>

        <aside class="space-y-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">تعديل الطاولة</h3>
                <form method="POST" action="{{ route('workspace.pos.tables.update', $table) }}" class="mt-3 space-y-2">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $table->name }}" class="w-full rounded-lg border-slate-300 text-sm" />
                    <select name="status" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="available" @selected($table->status === 'available')>AVAILABLE</option>
                        <option value="occupied" @selected($table->status === 'occupied')>OCCUPIED</option>
                    </select>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">حفظ</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">إدارة الجلسة</h3>
                @if($currentSession)
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $currentSession]) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white">إغلاق الجلسة الحالية</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.open', $table) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">فتح جلسة جديدة</button>
                    </form>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">QR Menu</h3>
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($menuUrl) }}"
                    alt="QR {{ $table->name }}"
                    class="mx-auto mt-3 h-44 w-44 rounded"
                />
                <a href="{{ $menuUrl }}" target="_blank" rel="noopener" class="mt-3 block truncate text-center text-xs font-semibold text-slate-700 hover:text-slate-900">
                    {{ $menuUrl }}
                </a>
                <form method="POST" action="{{ route('workspace.pos.tables.qr.regenerate', $table) }}" class="mt-3">
                    @csrf
                    <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">تجديد رمز QR</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">جلسات الطاولة</h3>
                <div class="mt-3 space-y-2">
                    @foreach($table->sessions as $session)
                        <div class="rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                            <p>الحالة: {{ $session->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</p>
                            <p>بدأت: {{ optional($session->opened_at)->format('Y-m-d H:i') }}</p>
                            <p>أغلقت: {{ optional($session->closed_at)->format('Y-m-d H:i') ?: '—' }}</p>
                        </div>
                    @endforeach
                </div>
            </article>
        </aside>
    </section>

    <script>
        const formatDuration = (openedAt) => {
            const start = new Date(openedAt).getTime();
            if (Number.isNaN(start)) return '00:00:00';
            const diff = Math.max(0, Math.floor((Date.now() - start) / 1000));
            const hours = String(Math.floor(diff / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const seconds = String(diff % 60).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        };

        const timerNode = document.querySelector('[data-opened-at]');
        if (timerNode) {
            const tick = () => timerNode.textContent = formatDuration(timerNode.dataset.openedAt);
            tick();
            setInterval(tick, 1000);
        }
    </script>
@endsection
