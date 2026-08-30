<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $workspace->name }} - Menu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    @php
        $groups = $items->groupBy(fn ($item) => $item->item_type ?: 'عام');
        $orderRoute = $table
            ? route('menu.table.order', ['workspace' => $workspace->slug, 'token' => $table->qr_token])
            : route('menu.general.order', ['workspace' => $workspace->slug]);
    @endphp

    <main
        class="mx-auto max-w-4xl px-3 py-5 sm:px-4"
        x-data="customerMenu({ items: @js($items) })"
    >
        @include('partials.flash')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-xl font-extrabold text-slate-900">{{ $workspace->name }}</h1>
            @if($table)
                <p class="mt-1 text-sm font-semibold text-slate-700">Table: {{ $table->name }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">General Menu</p>
            @endif
            <p class="mt-2 text-xs text-slate-500">اختر المنتجات ثم اضغط Place Order.</p>
        </section>

        <div class="mt-4 space-y-4">
            @forelse($groups as $typeName => $groupItems)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-bold text-slate-800">{{ $typeName }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($groupItems as $item)
                            @php($imagePath = $item->image_path ? asset('storage/'.$item->image_path) : null)
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                @if($imagePath)
                                    <img src="{{ $imagePath }}" alt="{{ $item->name }}" class="mb-2 h-32 w-full rounded-lg object-cover" />
                                @endif
                                <p class="text-sm font-semibold text-slate-900">{{ $item->name }}</p>
                                <p class="mt-2 text-sm font-bold text-slate-900">{{ number_format((float) $item->price, 2) }} {{ $item->currency }}</p>
                                <button
                                    type="button"
                                    @click='addItem({ id: {{ $item->id }}, name: @js($item->name), price: {{ (float) $item->price }}, currency: @js($item->currency) })'
                                    class="mt-3 w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                >
                                    إضافة للسلة
                                </button>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <section class="rounded-2xl border border-slate-200 bg-white p-5 text-center text-sm text-slate-500 shadow-sm">
                    لا توجد منتجات متاحة في المنيو حاليًا.
                </section>
            @endforelse
        </div>

        <section class="sticky bottom-2 mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-lg">
            <h2 class="text-sm font-bold text-slate-900">السلة</h2>
            <template x-if="cart.length === 0">
                <p class="mt-2 text-xs text-slate-500">السلة فارغة.</p>
            </template>
            <div class="mt-2 space-y-2">
                <template x-for="(line, index) in cart" :key="line.pos_menu_item_id">
                    <div class="rounded-lg bg-slate-50 p-2 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-800" x-text="line.name"></p>
                            <button type="button" @click="removeLine(index)" class="text-rose-600">حذف</button>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="decrease(index)" class="rounded border border-slate-300 px-2">-</button>
                                <span x-text="line.quantity"></span>
                                <button type="button" @click="increase(index)" class="rounded border border-slate-300 px-2">+</button>
                            </div>
                            <p class="font-semibold" x-text="money(line.quantity * line.unit_price)"></p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-2 text-xs">
                <p>Total: <span class="font-bold" x-text="money(total)"></span></p>
            </div>

            <form method="POST" action="{{ $orderRoute }}" @submit="prepareSubmit" class="mt-3 space-y-2">
                @csrf
                <input name="customer_name" class="w-full rounded-lg border-slate-300 text-sm" placeholder="اسم العميل (اختياري)" />
                <input name="customer_phone" class="w-full rounded-lg border-slate-300 text-sm" placeholder="رقم الجوال (اختياري)" />
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات الطلب"></textarea>
                <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Place Order
                </button>
            </form>
        </section>
    </main>

    <script>
        function customerMenu({ items }) {
            return {
                items,
                cart: [],
                addItem(item) {
                    const existing = this.cart.find((line) => line.pos_menu_item_id === item.id);
                    if (existing) {
                        existing.quantity += 1;
                        return;
                    }

                    this.cart.push({
                        pos_menu_item_id: item.id,
                        name: item.name,
                        unit_price: Number(item.price || 0),
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
                money(amount) {
                    return Number(amount || 0).toFixed(2);
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
                }
            };
        }
    </script>
</body>
</html>
