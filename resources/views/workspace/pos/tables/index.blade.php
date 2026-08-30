@extends('layouts.pos', ['pageTitle' => 'لوحة الطاولات'])

@section('content')
    @php($workspace = request()->attributes->get('workspace'))
    <section class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">الطاولات</h2>
                <a href="{{ route('workspace.pos.cashier.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                    فتح الكاشير
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($tables as $table)
                    @php($openSession = $table->sessions->first())
                    @php($menuUrl = route('menu.table', ['workspace' => $workspace->slug, 'token' => $table->qr_token]))
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ $table->name }}</h3>
                                <p class="mt-1 text-xs {{ $table->status === 'occupied' ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $table->status === 'occupied' ? '🔴 Occupied' : '🟢 Available' }}
                                </p>
                                @if($openSession)
                                    <p class="mt-1 text-xs text-slate-600" data-opened-at="{{ optional($openSession->opened_at)->toIso8601String() }}">
                                        00:00:00
                                    </p>
                                @endif
                            </div>
                            <a href="{{ route('workspace.pos.tables.show', $table) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">تفاصيل</a>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div class="rounded-lg bg-white p-2 text-[11px] text-slate-600">
                                طلبات نشطة: {{ $table->open_orders_count }} / إجمالي: {{ $table->orders_count }}
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                @if($openSession)
                                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $openSession]) }}">
                                        @csrf
                                        <button class="w-full rounded-lg bg-rose-600 px-2 py-2 text-[11px] font-semibold text-white">إغلاق الجلسة</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.open', $table) }}">
                                        @csrf
                                        <button class="w-full rounded-lg bg-slate-900 px-2 py-2 text-[11px] font-semibold text-white">فتح جلسة</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('workspace.pos.tables.qr.regenerate', $table) }}">
                                    @csrf
                                    <button class="w-full rounded-lg border border-slate-300 px-2 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">تجديد QR</button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-3 rounded-lg border border-slate-200 bg-white p-2">
                            <img
                                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($menuUrl) }}"
                                alt="QR {{ $table->name }}"
                                class="mx-auto h-28 w-28 rounded"
                            />
                            <p class="mt-2 truncate text-center text-[10px] text-slate-500">{{ $menuUrl }}</p>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">لا توجد طاولات حتى الآن.</p>
                @endforelse
            </div>
            <div class="mt-4">
                {{ $tables->links() }}
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">إضافة طاولة</h2>
            <form method="POST" action="{{ route('workspace.pos.tables.store') }}" class="mt-3 space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">اسم/رقم الطاولة</label>
                    <input name="name" required class="w-full rounded-lg border-slate-300 text-sm" placeholder="Table 1" />
                </div>
                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">إنشاء</button>
            </form>
            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                <p>المنيو العام: <a class="font-semibold text-slate-800" href="{{ route('menu.general', ['workspace' => $workspace->slug]) }}" target="_blank" rel="noopener">فتح الرابط</a></p>
            </div>
        </article>
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

        const timerNodes = document.querySelectorAll('[data-opened-at]');
        const tick = () => {
            timerNodes.forEach((node) => {
                node.textContent = formatDuration(node.dataset.openedAt);
            });
        };

        tick();
        setInterval(tick, 1000);
    </script>
@endsection
