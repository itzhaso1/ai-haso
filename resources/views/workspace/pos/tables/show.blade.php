@extends('layouts.pos', ['pageTitle' => $table->name])

@section('content')
    @php($workspace = request()->attributes->get('workspace'))
    @php($menuUrl = route('menu.table', ['workspace' => $workspace->slug, 'token' => $table->qr_token]))
    @php($runningStatuses = ['new', 'accepted', 'preparing', 'ready'])
    @php($sessionSubtotal = (float) $sessionOrders->sum('subtotal'))
    @php($sessionDiscount = (float) $sessionOrders->sum('discount_amount'))
    @php($sessionTotal = (float) $sessionOrders->sum('total_amount'))
    @php($menuGroups = $menuItems->groupBy(fn ($item) => $item->category?->name ?: ($item->item_type ?: 'عام')))
    @php($tableStatusLabel = $table->status === 'occupied' ? 'مشغولة' : 'متاحة')
    @php($hasCurrentSession = (bool) $currentSession)
    @php($openedAtIso = $currentSession?->opened_at?->toIso8601String())
    @php($hasRunningOrders = $hasCurrentSession && $sessionOrders->contains(fn ($order) => in_array($order->pos_status, $runningStatuses, true)))

    <section class="grid gap-4 xl:grid-cols-12">
        <div class="space-y-4 xl:col-span-6">
            @if(session('payment_link'))
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    رابط الدفع:
                    <a href="{{ session('payment_link') }}" target="_blank" class="font-bold underline">{{ session('payment_link') }}</a>
                </div>
            @endif

            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold">الحساب والإغلاق</h2>
                        <p class="mt-1 text-xs text-slate-300">تحكم سريع قبل إنهاء الجلسة</p>
                    </div>
                    <a href="{{ route('workspace.pos.tables.index') }}" class="rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-700">
                        رجوع
                    </a>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الطاولة</p>
                        <p class="mt-1 text-sm font-bold">{{ $table->name }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الحالة</p>
                        <p class="mt-1 text-sm font-bold {{ $table->status === 'occupied' ? 'text-rose-300' : 'text-emerald-300' }}">
                            {{ $tableStatusLabel }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-600 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">مدة الجلسة</p>
                        <p class="mt-1 text-sm font-bold" data-opened-at="{{ $openedAtIso }}">00:00:00</p>
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
                        طباعة الفاتورة
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
                            <p class="mt-2 text-[11px] text-amber-300">أنهِ الطلبات (الجديد/التحضير/الجاهز) قبل إغلاق الجلسة.</p>
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
                    @forelse($sessionOrders as $order)
                        <article class="rounded-xl border border-slate-700 bg-slate-900/50 p-3">
                            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-white">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-slate-300">
                                        {{ $posStatuses[$order->pos_status] ?? $order->pos_status }} • {{ strtoupper($order->source) }} •
                                        {{ $order->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('workspace.pos.orders.status', $order) }}">
                                        @csrf
                                        <select name="pos_status" class="rounded-lg border-slate-600 bg-slate-900 text-xs text-white">
                                            @foreach($posStatuses as $key => $label)
                                                <option value="{{ $key }}" @selected($order->pos_status === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="mt-1 w-full rounded-lg bg-blue-500 px-2 py-1 text-[11px] font-semibold text-blue-950">تحديث</button>
                                    </form>
                                    <a href="{{ route('workspace.pos.orders.print', $order) }}" target="_blank" class="h-fit rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-700">
                                        طباعة
                                    </a>
                                </div>
                            </div>

                            <ul class="mb-2 space-y-1 text-xs text-slate-300">
                                @foreach($order->items as $item)
                                    <li>{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }} × {{ $item->quantity }} = {{ number_format((float) $item->total_amount, 2) }}</li>
                                @endforeach
                            </ul>

                            @if(!in_array($order->pos_status, ['completed', 'cancelled'], true))
                                <form method="POST" action="{{ route('workspace.pos.orders.update-items', $order) }}">
                                    @csrf
                                    <div class="space-y-2">
                                        @foreach($order->items as $item)
                                            <div class="grid grid-cols-12 gap-2 rounded-lg border border-slate-700 bg-slate-900/70 p-2 text-xs">
                                                <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}" />
                                                <div class="col-span-5">
                                                    <p class="font-semibold">{{ $item->product_name }}</p>
                                                    <p class="text-[10px] text-slate-400">{{ $item->item_type ?: 'عام' }}</p>
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="mb-1 block text-[10px] text-slate-400">الكمية</label>
                                                    <input type="number" min="1" name="items[{{ $loop->index }}][quantity]" value="{{ $item->quantity }}" class="w-full rounded border-slate-600 bg-slate-950 p-1 text-xs text-white" />
                                                </div>
                                                <div class="col-span-3">
                                                    <label class="mb-1 block text-[10px] text-slate-400">سعر الوحدة</label>
                                                    <input type="number" min="0" step="0.01" name="items[{{ $loop->index }}][unit_price]" value="{{ $item->unit_price }}" class="w-full rounded border-slate-600 bg-slate-950 p-1 text-xs text-white" />
                                                </div>
                                                <div class="col-span-2 flex items-end justify-end">
                                                    <label class="inline-flex items-center gap-1 text-[10px] text-rose-300">
                                                        <input type="checkbox" name="items[{{ $loop->index }}][remove]" value="1">
                                                        حذف
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-300">خصم الطلب</label>
                                            <input type="number" min="0" step="0.01" name="discount_amount" value="{{ $order->discount_amount }}" class="w-full rounded border-slate-600 bg-slate-950 text-xs text-white" />
                                        </div>
                                        <div class="flex items-end">
                                            <button class="rounded-lg bg-amber-400 px-3 py-2 text-xs font-semibold text-amber-950">حفظ تعديلات الفاتورة</button>
                                        </div>
                                    </div>
                                </form>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @if($order->payment_status !== 'paid')
                                    <form method="POST" action="{{ route('workspace.pos.orders.payment-link', $order) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-700">رابط دفع</button>
                                    </form>
                                @endif
                                @if($order->pos_cashier_invoice_id)
                                    <a href="{{ route('workspace.pos.invoices.show', $order->pos_cashier_invoice_id) }}" class="rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-700">
                                        عرض الفاتورة
                                    </a>
                                @endif
                                <p class="mr-auto text-sm font-bold text-emerald-300">Total: {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</p>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-300">لا توجد طلبات في الجلسة الحالية.</p>
                    @endforelse
                </div>
            </article>
        </div>

        <div class="space-y-4 xl:col-span-6">
            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-extrabold">{{ $hasCurrentSession ? 'جلسة #'.$currentSession->id : 'لا توجد جلسة مفتوحة' }}</h3>
                        <p class="mt-1 text-xs text-slate-300">
                            @if($hasCurrentSession)
                                {{ $table->name }} • {{ optional($currentSession->opened_at)->format('Y-m-d H:i') }} • الحالة: {{ $tableStatusLabel }}
                            @else
                                افتح جلسة للطاولة لتفعيل المنيو والطلبات.
                            @endif
                        </p>
                    </div>
                    <span class="rounded-full border border-slate-500 bg-slate-900/70 px-3 py-1 text-[11px] font-semibold">
                        {{ $hasCurrentSession ? 'جلسة مفتوحة' : 'جلسة غير نشطة' }}
                    </span>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">مدة الجلسة</p>
                        <p class="mt-1 text-sm font-bold" data-opened-at="{{ $openedAtIso }}">00:00:00</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الطلبات</p>
                        <p class="mt-1 text-sm font-bold">{{ $sessionOrders->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الخصم</p>
                        <p class="mt-1 text-sm font-bold">{{ number_format($sessionDiscount, 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-900/70 p-2 text-center">
                        <p class="text-[11px] text-slate-400">الإجمالي النهائي</p>
                        <p class="mt-1 text-sm font-bold text-emerald-300">{{ number_format($sessionTotal, 2) }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <h3 class="text-sm font-extrabold">المنيو</h3>
                <p class="mt-1 text-[11px] text-slate-300">اختيار المنتجات والإضافة للطاولة فورًا</p>

                @if($currentSession)
                    <form method="POST" action="{{ route('workspace.pos.tables.orders.store', $table) }}" class="mt-3 space-y-3">
                        @csrf
                        <div class="max-h-[430px] space-y-2 overflow-y-auto rounded-lg border border-slate-700 bg-slate-900/50 p-2">
                            @foreach($menuGroups as $categoryName => $groupItems)
                                <details class="rounded-lg border border-slate-700 bg-slate-800/80" @if($loop->first) open @endif>
                                    <summary class="cursor-pointer list-none px-3 py-2 text-sm font-semibold text-slate-100">
                                        <span class="flex items-center justify-between">
                                            <span>{{ $categoryName }}</span>
                                            <span class="text-[11px] text-slate-400">{{ $groupItems->count() }} أصناف</span>
                                        </span>
                                    </summary>
                                    <div class="space-y-1 border-t border-slate-700 px-2 py-2">
                                        @foreach($groupItems as $item)
                                            <label class="flex items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-900/60 px-2 py-2 text-xs">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate font-semibold text-slate-100">
                                                        {{ $item->name }}{{ $item->size_label ? ' - '.$item->size_label : '' }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-400">{{ number_format((float) $item->price, 2) }} {{ $item->currency }}</span>
                                                </span>
                                                <input type="number" min="0" max="30" value="0" name="qty[{{ $item->id }}]" class="w-16 rounded border-slate-600 bg-slate-950 p-1 text-center text-xs text-white" />
                                            </label>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>

                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-600 bg-slate-950 text-sm text-white placeholder:text-slate-500" placeholder="ملاحظات الطلب (اختياري)"></textarea>
                        <button type="button" onclick="prepareTableOrder(this.form)" class="w-full rounded-lg bg-cyan-400 px-3 py-2 text-sm font-bold text-cyan-950">إضافة</button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-slate-300">افتح جلسة أولاً لإضافة طلبات من المنيو.</p>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-700 bg-slate-800 p-4 text-slate-100 shadow-xl">
                <h3 class="text-sm font-extrabold">QR Menu</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($menuUrl) }}" alt="QR {{ $table->name }}" class="mx-auto h-36 w-36 rounded-lg border border-slate-700 bg-white p-1" />

                    <div class="space-y-2">
                        <a href="{{ $menuUrl }}" target="_blank" rel="noopener" class="block truncate rounded-lg border border-slate-600 bg-slate-900/70 px-2 py-2 text-xs font-semibold text-slate-100">
                            {{ $menuUrl }}
                        </a>
                        <form method="POST" action="{{ route('workspace.pos.tables.qr.regenerate', $table) }}">
                            @csrf
                            <button class="w-full rounded-lg border border-slate-500 px-3 py-2 text-xs font-semibold hover:bg-slate-700">تجديد رمز QR</button>
                        </form>
                        <div class="max-h-24 space-y-1 overflow-y-auto rounded-lg border border-slate-700 bg-slate-900/60 p-2 text-[11px] text-slate-300">
                            @foreach($table->sessions as $session)
                                <p>
                                    {{ $session->status === 'open' ? 'مفتوحة' : 'مغلقة' }} •
                                    {{ optional($session->opened_at)->format('m/d H:i') }}
                                    @if($session->closed_at)
                                        → {{ optional($session->closed_at)->format('m/d H:i') }}
                                    @endif
                                </p>
                            @endforeach
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <script>
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

        function prepareTableOrder(form) {
            form.querySelectorAll('[data-item-line]').forEach((node) => node.remove());
            let index = 0;
            const qtyInputs = form.querySelectorAll('input[name^="qty["]');

            qtyInputs.forEach((input) => {
                const qty = Number(input.value || 0);
                if (qty <= 0) return;
                const match = input.name.match(/^qty\[(\d+)\]$/);
                if (!match) return;
                const itemId = match[1];

                const itemInput = document.createElement('input');
                itemInput.type = 'hidden';
                itemInput.name = `items[${index}][pos_menu_item_id]`;
                itemInput.value = itemId;
                itemInput.dataset.itemLine = '1';
                form.appendChild(itemInput);

                const qtyHidden = document.createElement('input');
                qtyHidden.type = 'hidden';
                qtyHidden.name = `items[${index}][quantity]`;
                qtyHidden.value = String(qty);
                qtyHidden.dataset.itemLine = '1';
                form.appendChild(qtyHidden);
                index += 1;
            });

            if (index === 0) {
                alert('اختر صنفًا واحدًا على الأقل بكمية أكبر من صفر.');
                return;
            }

            form.submit();
        }
    </script>
@endsection
