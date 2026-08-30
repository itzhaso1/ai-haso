<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $workspace->name }} - Menu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    @php
        $groups = $items->groupBy(fn ($item) => $item->category?->name ?: ($item->item_type ?: 'عام'));
        $categoryKeys = $groups->keys()->values()->all();
        $defaultCategory = (string) ($categoryKeys[0] ?? '');
        $orderRoute = $table
            ? route('menu.table.order', ['workspace' => $workspace->slug, 'token' => $table->qr_token])
            : route('menu.general.order', ['workspace' => $workspace->slug]);
        $aiRoute = $table
            ? route('menu.table.ai', ['workspace' => $workspace->slug, 'token' => $table->qr_token])
            : route('menu.general.ai', ['workspace' => $workspace->slug]);
    @endphp

    <main
        class="mx-auto max-w-5xl px-3 py-5 sm:px-4"
        x-data="customerMenu({ items: @js($items), aiRoute: @js($aiRoute), defaultCategory: @js($defaultCategory) })"
    >
        @include('partials.flash')

        @if(session('payment_link'))
            <section class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                تم تجهيز رابط الدفع الإلكتروني:
                <a href="{{ session('payment_link') }}" class="font-bold underline" target="_blank" rel="noopener">{{ session('payment_link') }}</a>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-xl font-extrabold text-slate-900">{{ $workspace->name }}</h1>
            @if($table)
                <p class="mt-1 text-sm font-semibold text-slate-700">طلب الطاولة: {{ $table->name }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">General Menu</p>
            @endif
            <p class="mt-2 text-xs text-slate-500">اختر القسم، أضف الأصناف إلى السلة، ثم أكمل طريقة الدفع المناسبة.</p>
        </section>

        <section class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-3">
            <h2 class="text-sm font-bold text-indigo-900">مساعد المنيو (AI)</h2>
            <div class="mt-2 flex gap-2">
                <input x-model="aiQuestion" type="text" class="flex-1 rounded-lg border-indigo-300 text-sm" placeholder="مثلاً: شو عندكم مشروبات اليوم؟" />
                <button type="button" @click="askAi" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">اسأل</button>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-xs text-indigo-900" x-text="aiAnswer"></p>
        </section>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900">الأقسام</h2>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach($groups as $typeName => $groupItems)
                    <button
                        type="button"
                        @click="selectedCategory = @js($typeName)"
                        class="whitespace-nowrap rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === @js($typeName) ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'"
                    >
                        {{ $typeName }} ({{ $groupItems->count() }})
                    </button>
                @endforeach
            </div>

            <div class="mt-4">
                @forelse($groups as $typeName => $groupItems)
                    <section x-show="selectedCategory === @js($typeName)" x-cloak>
                        <h3 class="mb-3 text-sm font-bold text-slate-800">{{ $typeName }}</h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($groupItems as $item)
                                @php($imagePath = $item->image_path ? asset('storage/'.$item->image_path) : null)
                                <button
                                    type="button"
                                    @click='addItem({ id: {{ $item->id }}, name: @js($item->name), size: @js($item->size_label), price: {{ (float) $item->price }}, currency: @js($item->currency) })'
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-right transition hover:border-slate-300 hover:bg-slate-100"
                                >
                                    @if($imagePath)
                                        <img src="{{ $imagePath }}" alt="{{ $item->name }}" class="mb-2 h-32 w-full rounded-lg object-cover" />
                                    @endif
                                    <p class="text-sm font-semibold text-slate-900">{{ $item->name }}</p>
                                    @if($item->size_label)
                                        <p class="mt-1 text-xs text-slate-500">{{ $item->size_label }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500">{{ $item->description ?: 'بدون وصف' }}</p>
                                    <p class="mt-2 text-sm font-bold text-slate-900">{{ number_format((float) $item->price, 2) }} {{ $item->currency }}</p>
                                    <p class="mt-1 text-[11px] font-semibold text-emerald-700">إضافة إلى السلة</p>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500">
                        لا توجد منتجات متاحة في المنيو حاليًا.
                    </section>
                @endforelse
            </div>
        </section>

        <section class="sticky bottom-2 mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <h2 class="text-sm font-bold text-slate-900">السلة</h2>
            <template x-if="cart.length === 0">
                <p class="mt-2 text-xs text-slate-500">السلة فارغة.</p>
            </template>

            <div class="mt-2 max-h-52 space-y-2 overflow-y-auto">
                <template x-for="(line, index) in cart" :key="`${line.pos_menu_item_id}-${line.size || ''}`">
                    <div class="rounded-lg bg-slate-50 p-2 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-800" x-text="line.name"></p>
                            <button type="button" @click="removeLine(index)" class="text-rose-600">حذف</button>
                        </div>
                        <p class="text-[11px] text-slate-500" x-text="line.size || ''"></p>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="decrease(index)" class="rounded border border-slate-300 px-2">-</button>
                                <span x-text="line.quantity"></span>
                                <button type="button" @click="increase(index)" class="rounded border border-slate-300 px-2">+</button>
                            </div>
                            <p class="font-semibold">
                                <span x-text="money(line.quantity * line.unit_price)"></span>
                                <span x-text="line.currency"></span>
                            </p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-2 text-xs">
                <p>عدد الأصناف: <span class="font-bold" x-text="cart.length"></span></p>
                <p class="mt-1">الإجمالي: <span class="font-bold" x-text="money(total)"></span> <span x-text="currencyLabel"></span></p>
            </div>

            <form method="POST" action="{{ $orderRoute }}" @submit="prepareSubmit" class="mt-3 space-y-2">
                @csrf
                <input name="customer_name" class="w-full rounded-lg border-slate-300 text-sm" placeholder="اسم العميل (اختياري)" />
                <input name="customer_phone" class="w-full rounded-lg border-slate-300 text-sm" placeholder="رقم الجوال (اختياري)" />
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات الطلب"></textarea>

                <div class="rounded-lg border border-slate-200 p-2 text-xs text-slate-700">
                    <p class="mb-2 font-semibold">طريقة الدفع</p>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="payment_method" value="pay_later" checked>
                        الدفع عند الخروج (الطلب غير مدفوع)
                    </label>
                    <label class="mt-2 flex items-center gap-2">
                        <input type="radio" name="payment_method" value="pay_now">
                        الدفع الآن (جاهز للربط مع API الدفع)
                    </label>
                </div>

                <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    تأكيد الطلب
                </button>
            </form>
        </section>
    </main>

    <script>
        function customerMenu({ items, aiRoute, defaultCategory }) {
            return {
                items,
                aiRoute,
                selectedCategory: defaultCategory || '',
                aiQuestion: '',
                aiAnswer: '',
                cart: [],
                addItem(item) {
                    const existing = this.cart.find((line) => line.pos_menu_item_id === item.id && line.size === item.size);
                    if (existing) {
                        existing.quantity += 1;
                        return;
                    }

                    this.cart.push({
                        pos_menu_item_id: item.id,
                        name: item.name,
                        size: item.size,
                        unit_price: Number(item.price || 0),
                        currency: item.currency || '',
                        quantity: 1,
                    });
                },
                increase(index) {
                    this.cart[index].quantity += 1;
                },
                decrease(index) {
                    if (this.cart[index].quantity <= 1) {
                        this.cart.splice(index, 1);
                        return;
                    }
                    this.cart[index].quantity -= 1;
                },
                removeLine(index) {
                    this.cart.splice(index, 1);
                },
                get total() {
                    return this.cart.reduce((sum, line) => sum + (line.quantity * line.unit_price), 0);
                },
                get currencyLabel() {
                    const currencies = [...new Set(this.cart.map((line) => line.currency).filter(Boolean))];
                    if (currencies.length === 0) return '';
                    if (currencies.length === 1) return currencies[0];
                    return 'MIX';
                },
                money(amount) {
                    return Number(amount || 0).toFixed(2);
                },
                async askAi() {
                    if ((this.aiQuestion || '').trim() === '') return;
                    this.aiAnswer = 'جاري التحضير...';
                    try {
                        const response = await fetch(this.aiRoute, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ message: this.aiQuestion }),
                        });
                        const data = await response.json();
                        this.aiAnswer = data.answer || 'لا يوجد رد حالياً.';
                    } catch (e) {
                        this.aiAnswer = 'تعذر الوصول إلى المساعد الآن.';
                    }
                },
                prepareSubmit(event) {
                    if (this.cart.length === 0) {
                        event.preventDefault();
                        alert('اختر منتجًا واحدًا على الأقل.');
                        return;
                    }

                    event.target.querySelectorAll('[data-cart-input]').forEach((node) => node.remove());
                    this.cart.forEach((line, index) => {
                        const itemInput = document.createElement('input');
                        itemInput.type = 'hidden';
                        itemInput.name = `items[${index}][pos_menu_item_id]`;
                        itemInput.value = line.pos_menu_item_id;
                        itemInput.dataset.cartInput = '1';
                        event.target.appendChild(itemInput);

                        const quantityInput = document.createElement('input');
                        quantityInput.type = 'hidden';
                        quantityInput.name = `items[${index}][quantity]`;
                        quantityInput.value = line.quantity;
                        quantityInput.dataset.cartInput = '1';
                        event.target.appendChild(quantityInput);
                    });
                },
            };
        }
    </script>
</body>
</html>
