@extends('layouts.pos', ['pageTitle' => 'واجهة الكاشير'])

@section('content')
    <div
        x-data="cashierPos({
            items: @js($items),
            categories: @js($categories),
        })"
        class="grid gap-4 xl:grid-cols-12"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">أصناف الكاشير</h2>
                    <p class="text-xs text-slate-500">مصدر مستقل عن Products / Inventory الخارجية.</p>
                </div>
                <div class="flex gap-2">
                    <input x-model="search" type="search" placeholder="ابحث عن صنف..." class="rounded-lg border-slate-300 text-sm" />
                    <select x-model="selectedCategoryId" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل التصنيفات</option>
                        <template x-for="category in categories" :key="category.id">
                            <option :value="category.id" x-text="category.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="item in filteredItems" :key="item.id">
                    <button type="button" @click="addItem(item)" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-right transition hover:border-slate-300 hover:bg-slate-100">
                        <template x-if="item.image_path">
                            <img :src="`/storage/${item.image_path}`" alt="" class="mb-2 h-28 w-full rounded-lg object-cover" />
                        </template>
                        <p class="text-sm font-semibold text-slate-900" x-text="item.name"></p>
                        <p class="mt-1 text-[11px] text-slate-500" x-text="item.category?.name || item.item_type || 'عام'"></p>
                        <p class="text-[11px] text-slate-500" x-text="item.size_label || ''"></p>
                        <p class="mt-2 text-sm font-bold text-slate-900">
                            <span x-text="money(item.price)"></span>
                            <span x-text="item.currency"></span>
                        </p>
                        <p class="mt-2 text-[11px] font-semibold text-emerald-700">اضغط لإضافة الصنف مباشرة</p>
                    </button>
                </template>
            </div>
        </section>

        <aside class="space-y-4 xl:col-span-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">طلب جديد</h2>
                <form method="POST" action="{{ route('workspace.pos.orders.store') }}" @submit="prepareSubmit">
                    @csrf
                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">العميل (اختياري)</label>
                            <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="">بدون عميل</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">الطاولة (اختياري)</label>
                            <select name="dining_table_id" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="">بدون طاولة</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}">{{ $table->name }} - {{ $table->status === 'occupied' ? 'Occupied' : 'Available' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">الخصم</label>
                            <input x-model.number="discount" type="number" name="discount_amount" min="0" step="0.01" class="w-full rounded-lg border-slate-300 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظات</label>
                            <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <h3 class="text-xs font-bold text-slate-700">ملخص الطلب</h3>
                        <template x-if="cart.length === 0">
                            <p class="mt-2 text-xs text-slate-500">السلة فارغة.</p>
                        </template>
                        <div class="mt-2 space-y-2">
                            <template x-for="(line, index) in cart" :key="line.pos_menu_item_id">
                                <div class="rounded-lg bg-white p-2 text-xs">
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
                                        <p class="font-semibold">
                                            <span x-text="money(line.quantity * line.unit_price)"></span>
                                            <span x-text="line.currency"></span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 border-t border-slate-200 pt-2 text-xs text-slate-700">
                            <p>عدد الأصناف: <span class="font-semibold" x-text="cart.length"></span></p>
                            <p>Subtotal: <span class="font-semibold" x-text="money(subtotal)"></span></p>
                            <p>Discount: <span class="font-semibold" x-text="money(discount || 0)"></span></p>
                            <p class="mt-1 text-sm font-bold">
                                إجمالي المبلغ المطلوب دفعه:
                                <span x-text="money(total)"></span>
                                <span x-text="orderCurrencyLabel"></span>
                            </p>
                        </div>
                    </div>

                    <button class="mt-3 w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        إنشاء Order
                    </button>
                </form>
            </article>
        </aside>
    </div>

    <script>
        function cashierPos({ items, categories }) {
            return {
                items,
                categories,
                search: '',
                selectedCategoryId: '',
                cart: [],
                discount: 0,
                get filteredItems() {
                    return this.items.filter((item) => {
                        const matchesCategory = !this.selectedCategoryId || Number(item.pos_item_category_id) === Number(this.selectedCategoryId);
                        const term = this.search.trim().toLowerCase();
                        const matchesSearch = term === ''
                            || (item.name || '').toLowerCase().includes(term)
                            || (item.item_type || '').toLowerCase().includes(term);
                        return matchesCategory && matchesSearch;
                    });
                },
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
                        currency: item.currency || '---',
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
                get subtotal() {
                    return this.cart.reduce((sum, line) => sum + (line.quantity * line.unit_price), 0);
                },
                get total() {
                    return Math.max(0, this.subtotal - Number(this.discount || 0));
                },
                get orderCurrencyLabel() {
                    const currencies = [...new Set(this.cart.map((line) => line.currency).filter(Boolean))];
                    if (currencies.length === 0) {
                        return '';
                    }

                    if (currencies.length === 1) {
                        return currencies[0];
                    }

                    return 'MIX';
                },
                money(amount) {
                    return Number(amount || 0).toFixed(2);
                },
                prepareSubmit(event) {
                    if (this.cart.length === 0) {
                        event.preventDefault();
                        alert('أضف صنفًا واحدًا على الأقل قبل إنشاء الطلب.');
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
@endsection
