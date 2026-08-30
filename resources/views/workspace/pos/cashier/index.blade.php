@extends('layouts.pos', ['pageTitle' => 'واجهة الكاشير'])

@section('content')
    <div
        x-data="cashierPos({
            products: @js($products),
            categories: @js($categories),
        })"
        class="grid gap-4 xl:grid-cols-3"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">المنتجات</h2>
                    <p class="text-xs text-slate-500">نفس المنتجات الحالية في النظام، بدون نسخ أو تكرار.</p>
                </div>
                <div class="flex gap-2">
                    <input x-model="search" type="search" placeholder="ابحث عن منتج..." class="rounded-lg border-slate-300 text-sm" />
                    <select x-model.number="selectedCategoryId" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل التصنيفات</option>
                        <template x-for="category in categories" :key="category.id">
                            <option :value="category.id" x-text="category.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="product in filteredProducts" :key="product.id">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-sm font-semibold text-slate-900" x-text="product.name"></p>
                        <p class="mt-1 text-xs text-slate-500 line-clamp-2" x-text="product.description || 'بدون وصف'"></p>
                        <p class="mt-2 text-sm font-bold text-slate-900">
                            <span x-text="money(resolvePrice(product))"></span>
                            <span x-text="product.currency"></span>
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">المخزون: <span x-text="product.stock"></span></p>
                        <button type="button" @click="addProduct(product)" class="mt-3 w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">
                            إضافة للطلب
                        </button>
                    </article>
                </template>
            </div>
        </section>

        <aside class="space-y-4">
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
                        <input type="hidden" name="currency" value="USD" />
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <h3 class="text-xs font-bold text-slate-700">السلة</h3>
                        <template x-if="cart.length === 0">
                            <p class="mt-2 text-xs text-slate-500">السلة فارغة.</p>
                        </template>
                        <div class="mt-2 space-y-2">
                            <template x-for="(line, index) in cart" :key="line.product_id">
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
                                        <p class="font-semibold" x-text="money(line.quantity * line.unit_price)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-3 border-t border-slate-200 pt-2 text-xs text-slate-700">
                            <p>Subtotal: <span class="font-semibold" x-text="money(subtotal)"></span></p>
                            <p>Discount: <span class="font-semibold" x-text="money(discount || 0)"></span></p>
                            <p class="mt-1 text-sm font-bold">Total: <span x-text="money(total)"></span></p>
                        </div>
                    </div>

                    <button class="mt-3 w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        إنشاء Order
                    </button>
                </form>
            </article>
        </aside>
    </div>

    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-base font-bold text-slate-900">طلبات POS و QR</h2>
        <div class="space-y-3">
            @foreach($orders as $order)
                <article class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">#{{ $order->order_number }}</p>
                            <p class="text-xs text-slate-500">
                                المصدر: {{ strtoupper($order->source) }}
                                @if($order->table)
                                    • {{ $order->table->name }}
                                @endif
                            </p>
                        </div>
                        <p class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $posStatuses[$order->pos_status] ?? $order->pos_status }}</p>
                    </div>
                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                        @foreach($order->items as $item)
                            <li>{{ $item->product_name }} × {{ $item->quantity }} = {{ number_format((float) $item->total_amount, 2) }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('workspace.pos.orders.status', $order) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="pos_status" class="rounded-lg border-slate-300 text-xs">
                                @foreach($posStatuses as $key => $label)
                                    <option value="{{ $key }}" @selected($order->pos_status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث</button>
                        </form>
                        @if(!$order->finance_invoice_id)
                            <form method="POST" action="{{ route('workspace.pos.orders.invoice', $order) }}">
                                @csrf
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">تحويل لفاتورة</button>
                            </form>
                        @else
                            <a href="{{ route('workspace.finance.invoices.show', $order->finance_invoice_id) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">عرض الفاتورة</a>
                        @endif
                        <p class="mr-auto text-sm font-bold text-slate-900">Total: {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</p>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </section>

    <script>
        function cashierPos({ products, categories }) {
            return {
                products,
                categories,
                search: '',
                selectedCategoryId: '',
                cart: [],
                discount: 0,
                get filteredProducts() {
                    return this.products.filter((product) => {
                        const matchesCategory = !this.selectedCategoryId || product.category_id === this.selectedCategoryId;
                        const term = this.search.trim().toLowerCase();
                        const matchesSearch = term === ''
                            || (product.name || '').toLowerCase().includes(term)
                            || (product.description || '').toLowerCase().includes(term);
                        return matchesCategory && matchesSearch;
                    });
                },
                resolvePrice(product) {
                    return Number(product.sale_price ?? product.price ?? 0);
                },
                addProduct(product) {
                    const existing = this.cart.find((line) => line.product_id === product.id);
                    if (existing) {
                        existing.quantity += 1;
                        return;
                    }

                    this.cart.push({
                        product_id: product.id,
                        name: product.name,
                        unit_price: this.resolvePrice(product),
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
                money(amount) {
                    return Number(amount || 0).toFixed(2);
                },
                prepareSubmit(event) {
                    if (this.cart.length === 0) {
                        event.preventDefault();
                        alert('أضف منتجًا واحدًا على الأقل قبل إنشاء الطلب.');
                        return;
                    }

                    event.target.querySelectorAll('[data-cart-input]').forEach((node) => node.remove());

                    this.cart.forEach((line, index) => {
                        const productInput = document.createElement('input');
                        productInput.type = 'hidden';
                        productInput.name = `items[${index}][product_id]`;
                        productInput.value = line.product_id;
                        productInput.dataset.cartInput = '1';
                        event.target.appendChild(productInput);

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
