@extends('layouts.pos', ['pageTitle' => $table->name])

@section('content')
    @php($runningStatuses = ['new', 'accepted', 'preparing', 'ready'])
    @php($menuGroups = $menuItems->groupBy(fn ($item) => $item->category?->name ?: ($item->item_type ?: 'عام')))
    @php($defaultCategory = (string) ($menuGroups->keys()->first() ?? ''))
    @php($hasCurrentSession = (bool) $currentSession)
    @php($openedAtIso = $currentSession?->opened_at?->toIso8601String())
    @php($tableStatusLabel = $table->status === 'occupied' ? 'مشغولة' : 'متاحة')
    @php($billableOrders = $sessionOrders->reject(fn ($order) => $order->pos_status === 'cancelled'))
    @php($sessionSubtotal = (float) $billableOrders->sum('subtotal'))
    @php($sessionDiscount = (float) $billableOrders->sum('discount_amount'))
    @php($sessionTotal = (float) $billableOrders->sum('total_amount'))
    @php($runningSessionOrders = $sessionOrders->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready', 'delivered']))
    @php($hasRunningOrders = $hasCurrentSession && $sessionOrders->contains(fn ($order) => in_array($order->pos_status, $runningStatuses, true)))
    @php($sessionPaymentStatus = $billableOrders->isNotEmpty() && $billableOrders->every(fn ($order) => $order->payment_status === 'paid') ? 'paid' : 'unpaid')

    <section class="grid gap-4 xl:grid-cols-12">
        <div class="space-y-4 xl:col-span-7">
            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold">الحساب والإغلاق</h2>
                        <p class="mt-1 text-xs text-slate-300">ملخص كامل للجلسة الحالية على مستوى الطاولة</p>
                    </div>
                    <a href="{{ route('workspace.pos.tables.index') }}" class="rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-700">
                        رجوع
                    </a>
                </div>

                <div class="grid gap-2 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الطاولة</p>
                        <p class="mt-1 text-sm font-bold">{{ $table->name }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الحالة</p>
                        <p class="mt-1 text-sm font-bold {{ $table->status === 'occupied' ? 'text-rose-300' : 'text-emerald-300' }}">{{ $tableStatusLabel }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">مدة الجلسة</p>
                        <p class="mt-1 text-sm font-bold" data-opened-at="{{ $openedAtIso }}">00:00:00</p>
                    </div>
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">حالة الدفع</p>
                        <p class="mt-1 text-sm font-bold {{ $sessionPaymentStatus === 'paid' ? 'text-emerald-300' : 'text-amber-300' }}">
                            {{ $sessionPaymentStatus === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-700 bg-slate-900/50 p-2">
                        <p class="text-[11px] text-slate-400">المجموع</p>
                        <p class="mt-1 text-sm font-bold">{{ number_format($sessionSubtotal, 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/50 p-2">
                        <p class="text-[11px] text-slate-400">الخصم</p>
                        <p class="mt-1 text-sm font-bold">{{ number_format($sessionDiscount, 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/50 p-2">
                        <p class="text-[11px] text-slate-400">الإجمالي النهائي</p>
                        <p class="mt-1 text-sm font-bold text-emerald-300">{{ number_format($sessionTotal, 2) }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" onclick="window.print()" class="rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold hover:bg-slate-700">
                        طباعة
                    </button>
                    <span class="rounded-lg border border-slate-600 bg-slate-900/70 px-3 py-2 text-xs">
                        {{ $hasCurrentSession ? 'الجلسة مفتوحة' : 'لا توجد جلسة نشطة' }}
                    </span>
                </div>

                @if($currentSession)
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $currentSession]) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-emerald-500 px-3 py-2 text-sm font-bold text-emerald-950 disabled:cursor-not-allowed disabled:opacity-40" @disabled($hasRunningOrders)>
                            إغلاق الجلسة
                        </button>
                        @if($hasRunningOrders)
                            <p class="mt-2 text-[11px] text-amber-300">أنهِ الطلبات الجارية أولاً قبل إغلاق الجلسة.</p>
                        @endif
                    </form>
                @else
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.open', $table) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-blue-500 px-3 py-2 text-sm font-bold text-blue-950">فتح جلسة جديدة</button>
                    </form>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <h3 class="mb-3 text-sm font-extrabold">الطلبات الجارية</h3>
                <div class="space-y-3">
                    @forelse($runningSessionOrders as $order)
                        <article class="rounded-xl border border-slate-700 bg-slate-900/50 p-3">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-white">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-slate-300">
                                        {{ $posStatuses[$order->pos_status] ?? $order->pos_status }} •
                                        {{ $order->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                                    </p>
                                </div>
                                <p class="text-xs text-slate-400">{{ optional($order->created_at)->format('H:i') }}</p>
                            </div>

                            <ul class="space-y-1 text-xs text-slate-300">
                                @foreach($order->items as $item)
                                    <li class="flex items-center justify-between gap-2">
                                        <span>{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }} × {{ $item->quantity }}</span>
                                        <span>{{ number_format((float) $item->total_amount, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-2 border-t border-slate-700 pt-2 text-xs">
                                <p class="flex items-center justify-between text-slate-300">
                                    <span>المجموع الفرعي</span>
                                    <span>{{ number_format((float) $order->subtotal, 2) }}</span>
                                </p>
                                <p class="mt-1 flex items-center justify-between text-slate-300">
                                    <span>الخصم</span>
                                    <span>{{ number_format((float) $order->discount_amount, 2) }}</span>
                                </p>
                                <p class="mt-1 flex items-center justify-between text-sm font-bold text-emerald-300">
                                    <span>الإجمالي</span>
                                    <span>{{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</span>
                                </p>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-300">لا توجد طلبات جارية في هذه الجلسة.</p>
                    @endforelse
                </div>
            </article>
        </div>

        <div class="space-y-4 xl:col-span-5" x-data="tableQuickMenu({ defaultCategory: @js($defaultCategory) })">
            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <h3 class="text-sm font-extrabold">منيو الطاولة</h3>
                <p class="mt-1 text-[11px] text-slate-300">اضغط مباشرة على الصنف لإضافته للطلب</p>

                @if($currentSession)
                    <form method="POST" action="{{ route('workspace.pos.tables.orders.store', $table) }}" x-ref="quickAddForm" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="items[0][pos_menu_item_id]" x-model="selectedItemId" />
                        <input type="hidden" name="items[0][quantity]" value="1" />
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-600 bg-slate-950 text-sm text-white placeholder:text-slate-500" placeholder="ملاحظة سريعة (اختياري)"></textarea>
                    </form>

                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach($menuGroups as $categoryName => $groupItems)
                            <button
                                type="button"
                                @click="selectedCategory = @js($categoryName)"
                                class="whitespace-nowrap rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                                :class="selectedCategory === @js($categoryName) ? 'border-cyan-300 bg-cyan-300 text-cyan-950' : 'border-slate-600 bg-slate-900 text-slate-200 hover:bg-slate-700'"
                            >
                                {{ $categoryName }} ({{ $groupItems->count() }})
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-3 max-h-[560px] overflow-y-auto">
                        @foreach($menuGroups as $categoryName => $groupItems)
                            <section x-show="selectedCategory === @js($categoryName)" x-cloak class="space-y-2">
                                @foreach($groupItems as $item)
                                    <button
                                        type="button"
                                        @click="addItem({{ $item->id }})"
                                        class="w-full rounded-xl border border-slate-700 bg-slate-900/70 p-3 text-right transition hover:border-cyan-300 hover:bg-slate-900"
                                    >
                                        <p class="text-sm font-semibold text-slate-100">{{ $item->name }}{{ $item->size_label ? ' - '.$item->size_label : '' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $item->description ?: 'بدون وصف' }}</p>
                                        <p class="mt-2 text-sm font-bold text-cyan-300">{{ number_format((float) $item->price, 2) }} {{ $item->currency }}</p>
                                    </button>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-xs text-slate-300">افتح جلسة أولاً ثم أضف الأصناف بالضغط المباشر.</p>
                @endif
            </article>
        </div>
    </section>

    <script>
        function tableQuickMenu({ defaultCategory }) {
            return {
                selectedCategory: defaultCategory || '',
                selectedItemId: '',
                addItem(itemId) {
                    this.selectedItemId = String(itemId);
                    this.$nextTick(() => this.$refs.quickAddForm.submit());
                },
            };
        }

        const formatDuration = (openedAt) => {
            if (!openedAt) return '00:00:00';
            const start = new Date(openedAt).getTime();
            if (Number.isNaN(start)) return '00:00:00';
            const diff = Math.max(0, Math.floor((Date.now() - start) / 1000));
            const hours = String(Math.floor(diff / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const seconds = String(diff % 60).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        };

        const timerNodes = document.querySelectorAll('[data-opened-at]');
        if (timerNodes.length > 0) {
            const tick = () => {
                timerNodes.forEach((node) => {
                    node.textContent = formatDuration(node.dataset.openedAt);
                });
            };

            tick();
            setInterval(tick, 1000);
        }
    </script>
@endsection
