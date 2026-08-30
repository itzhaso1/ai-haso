@extends('layouts.pos', ['pageTitle' => $table->name])

@section('content')
    @php($workspace = request()->attributes->get('workspace'))
    @php($menuUrl = route('menu.table', ['workspace' => $workspace->slug, 'token' => $table->qr_token]))
    @php($runningStatuses = ['new', 'accepted', 'preparing', 'ready'])
    @php($sessionSubtotal = (float) $sessionOrders->sum('subtotal'))
    @php($sessionDiscount = (float) $sessionOrders->sum('discount_amount'))
    @php($sessionTotal = (float) $sessionOrders->sum('total_amount'))
    @php($menuGroups = $menuItems->groupBy(fn ($item) => $item->category?->name ?: ($item->item_type ?: 'عام')))

    <section class="grid gap-4 xl:grid-cols-12">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-8">
            @if(session('payment_link'))
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    رابط الدفع: <a href="{{ session('payment_link') }}" target="_blank" class="font-bold underline">{{ session('payment_link') }}</a>
                </div>
            @endif
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ $table->name }}</h2>
                    <p class="text-sm {{ $table->status === 'occupied' ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $table->status === 'occupied' ? '🔴 OCCUPIED' : '🟢 AVAILABLE' }}
                    </p>
                    @if($currentSession)
                        <p class="mt-1 text-xs text-slate-600" data-opened-at="{{ optional($currentSession->opened_at)->toIso8601String() }}">00:00:00</p>
                    @endif
                </div>
                <a href="{{ route('workspace.pos.tables.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
            </div>

            @if($currentSession)
                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs text-slate-500">المجموع</p>
                        <p class="mt-1 font-bold">{{ number_format($sessionSubtotal, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs text-slate-500">الخصم</p>
                        <p class="mt-1 font-bold">{{ number_format($sessionDiscount, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="text-xs text-slate-500">الإجمالي النهائي</p>
                        <p class="mt-1 font-bold">{{ number_format($sessionTotal, 2) }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($sessionOrders as $order)
                        <article class="rounded-xl border border-slate-200 p-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $posStatuses[$order->pos_status] ?? $order->pos_status }} • {{ strtoupper($order->source) }} •
                                        {{ $order->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('workspace.pos.orders.status', $order) }}">
                                        @csrf
                                        <select name="pos_status" class="rounded-lg border-slate-300 text-xs">
                                            @foreach($posStatuses as $key => $label)
                                                <option value="{{ $key }}" @selected($order->pos_status === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="mt-1 w-full rounded-lg bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white">تحديث</button>
                                    </form>
                                    <a href="{{ route('workspace.pos.orders.print', $order) }}" target="_blank" class="h-fit rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">طباعة</a>
                                </div>
                            </div>

                            <ul class="mb-2 space-y-1 text-xs text-slate-600">
                                @foreach($order->items as $item)
                                    <li>{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }} × {{ $item->quantity }} = {{ number_format((float) $item->total_amount, 2) }}</li>
                                @endforeach
                            </ul>

                            @if(!in_array($order->pos_status, ['completed', 'cancelled'], true))
                                <form method="POST" action="{{ route('workspace.pos.orders.update-items', $order) }}">
                                    @csrf
                                    <div class="space-y-2">
                                        @foreach($order->items as $item)
                                            <div class="grid grid-cols-12 gap-2 rounded-lg bg-slate-50 p-2 text-xs">
                                                <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}" />
                                                <div class="col-span-5">
                                                    <p class="font-semibold">{{ $item->product_name }}</p>
                                                    <p class="text-[10px] text-slate-500">{{ $item->item_type ?: 'عام' }}</p>
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="mb-1 block text-[10px] text-slate-500">الكمية</label>
                                                    <input type="number" min="1" name="items[{{ $loop->index }}][quantity]" value="{{ $item->quantity }}" class="w-full rounded border-slate-300 p-1 text-xs" />
                                                </div>
                                                <div class="col-span-3">
                                                    <label class="mb-1 block text-[10px] text-slate-500">سعر الوحدة</label>
                                                    <input type="number" min="0" step="0.01" name="items[{{ $loop->index }}][unit_price]" value="{{ $item->unit_price }}" class="w-full rounded border-slate-300 p-1 text-xs" />
                                                </div>
                                                <div class="col-span-2 flex items-end justify-end">
                                                    <label class="inline-flex items-center gap-1 text-[10px] text-rose-600">
                                                        <input type="checkbox" name="items[{{ $loop->index }}][remove]" value="1">
                                                        حذف
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-xs text-slate-500">خصم الطلب</label>
                                            <input type="number" min="0" step="0.01" name="discount_amount" value="{{ $order->discount_amount }}" class="w-full rounded border-slate-300 text-xs" />
                                        </div>
                                        <div class="flex items-end gap-2">
                                            <button class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white">حفظ تعديلات الفاتورة</button>
                                        </div>
                                    </div>
                                </form>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @if($order->payment_status !== 'paid')
                                    <form method="POST" action="{{ route('workspace.pos.orders.payment-link', $order) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">رابط دفع</button>
                                    </form>
                                @endif
                                @if($order->pos_cashier_invoice_id)
                                    <a href="{{ route('workspace.pos.invoices.show', $order->pos_cashier_invoice_id) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">عرض الفاتورة</a>
                                @endif
                                <p class="mr-auto text-sm font-bold text-slate-900">Total: {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</p>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد طلبات في الجلسة الحالية.</p>
                    @endforelse
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    لا توجد جلسة مفتوحة حاليًا. افتح جلسة جديدة للبدء.
                </div>
            @endif
        </article>

        <aside class="space-y-4 xl:col-span-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">إدارة الجلسة</h3>
                @if($currentSession)
                    @php($hasRunningOrders = $sessionOrders->contains(fn ($order) => in_array($order->pos_status, $runningStatuses, true)))
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $currentSession]) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white" @disabled($hasRunningOrders)>
                            إغلاق الجلسة وإصدار فاتورة نهائية
                        </button>
                        @if($hasRunningOrders)
                            <p class="mt-2 text-[11px] text-rose-600">أنهِ الطلبات (حتى تم التسليم) قبل الإغلاق.</p>
                        @endif
                    </form>
                @else
                    <form method="POST" action="{{ route('workspace.pos.tables.sessions.open', $table) }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">فتح جلسة جديدة</button>
                    </form>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">إضافة طلب جديد للطاولة</h3>
                @if($currentSession)
                    <form method="POST" action="{{ route('workspace.pos.tables.orders.store', $table) }}" class="mt-3 space-y-3">
                        @csrf
                        <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-2">
                            @foreach($menuGroups as $categoryName => $groupItems)
                                <div>
                                    <p class="mb-1 text-xs font-bold text-slate-700">{{ $categoryName }}</p>
                                    @foreach($groupItems as $item)
                                        <label class="mb-1 flex items-center justify-between gap-2 rounded bg-slate-50 px-2 py-1 text-xs">
                                            <span>{{ $item->name }} {{ $item->size_label ? ' - '.$item->size_label : '' }} ({{ number_format((float) $item->price, 2) }})</span>
                                            <span class="flex items-center gap-2">
                                                <input type="number" min="0" max="30" value="0" name="qty[{{ $item->id }}]" class="w-14 rounded border-slate-300 p-1 text-xs" />
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات الطلب (اختياري)"></textarea>
                        <button type="button" onclick="prepareTableOrder(this.form)" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">إضافة إلى الجلسة</button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-slate-500">افتح جلسة أولاً لإضافة طلبات.</p>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">QR Menu</h3>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($menuUrl) }}" alt="QR {{ $table->name }}" class="mx-auto mt-3 h-44 w-44 rounded" />
                <a href="{{ $menuUrl }}" target="_blank" rel="noopener" class="mt-3 block truncate text-center text-xs font-semibold text-slate-700 hover:text-slate-900">{{ $menuUrl }}</a>
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

        function prepareTableOrder(form) {
            form.querySelectorAll('[data-item-line]').forEach((n) => n.remove());
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
