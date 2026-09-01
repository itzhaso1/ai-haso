@extends('layouts.pos', ['pageTitle' => 'واجهة الكاشير'])

@section('content')
    <div
        x-data="cashierPos({
            items: @js($items),
            categories: @js($categories),
            storeOrderUrl: @js($storeOrderUrl),
            cartEndpoints: {
                addItem: @js(route('workspace.pos.cart.items.store')),
                updateItem: @js(url('/workspace/pos/cart/items')),
                removeItem: @js(url('/workspace/pos/cart/items')),
                meta: @js(route('workspace.pos.cart.meta')),
                checkout: @js(route('workspace.pos.cart.checkout')),
                clear: @js(route('workspace.pos.cart.clear')),
                csrf: @js(csrf_token()),
            },
        })"
        class="space-y-3"
    >
        {{-- Desktop RTL: categories RIGHT | products CENTER | cart LEFT --}}
        <div class="grid gap-3 lg:grid-cols-12">
            {{-- Cart / order (LEFT in RTL = start) --}}
            <aside class="order-1 space-y-3 lg:col-span-3 lg:order-1">
                <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">السلة</h2>

                    <div class="mt-3 space-y-2">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold text-slate-500">العميل (اختياري)</label>
                            <select x-model="customerId" @change="syncMeta" class="w-full rounded-lg border-slate-200 bg-white text-sm">
                                <option value="">بدون عميل</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold text-slate-500">الطاولة (اختياري)</label>
                            <select x-model="diningTableId" @change="syncMeta" class="w-full rounded-lg border-slate-200 bg-white text-sm">
                                <option value="">بدون طاولة</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}">{{ $table->name }} — {{ $table->status === 'occupied' ? 'مشغولة' : 'متاحة' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold text-slate-500">الخصم</label>
                            <input x-model.number="discount" @change="syncMeta" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 bg-white text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold text-slate-500">ملاحظات</label>
                            <textarea x-model="notes" @change="syncMeta" rows="2" class="w-full rounded-lg border-slate-200 bg-white text-sm"></textarea>
                        </div>
                    </div>

                    <div class="mt-3 max-h-64 space-y-1.5 overflow-y-auto rounded-lg border border-slate-100 bg-white p-2">
                        <template x-if="cart.length === 0">
                            <p class="py-4 text-center text-xs text-slate-400">السلة فارغة.</p>
                        </template>
                        <template x-for="(line, index) in cart" :key="line.pos_menu_item_id">
                            <div class="rounded-lg border border-slate-100 bg-white px-2 py-1.5 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate font-semibold text-slate-800" x-text="line.name"></p>
                                    <button type="button" @click="removeLine(index)" class="text-rose-500 hover:text-rose-700">حذف</button>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" @click="decrease(index)" class="h-6 w-6 rounded border border-slate-200 bg-white text-slate-700">−</button>
                                        <span class="min-w-5 text-center font-semibold" x-text="line.quantity"></span>
                                        <button type="button" @click="increase(index)" class="h-6 w-6 rounded border border-slate-200 bg-white text-slate-700">+</button>
                                    </div>
                                    <p class="font-semibold text-slate-900">
                                        <span x-text="money(line.quantity * line.unit_price)"></span>
                                        <span class="text-[10px] text-slate-500" x-text="line.currency"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-600">
                        <div class="flex justify-between"><span>المجموع الفرعي</span><span class="font-semibold" x-text="money(subtotal)"></span></div>
                        <div class="flex justify-between"><span>الخصم</span><span class="font-semibold" x-text="money(discount || 0)"></span></div>
                        <div class="flex justify-between text-sm font-bold text-slate-900">
                            <span>الإجمالي</span>
                            <span>
                                <span x-text="money(total)"></span>
                                <span class="text-xs font-semibold text-slate-500" x-text="orderCurrencyLabel"></span>
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2">
                        <button
                            type="button"
                            @click="createOrder"
                            :disabled="submitting || cart.length === 0"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[var(--hs-brand,#06C2A4)] px-3 py-2.5 text-sm font-bold text-white hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-text="submitting ? 'جاري الإنشاء...' : 'إنشاء الطلب'"></span>
                        </button>
                        <button
                            type="button"
                            @click="checkoutViaCart"
                            :disabled="submitting || cart.length === 0"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                        >
                            طلب خارجي
                        </button>
                    </div>

                    <template x-if="errorMessage">
                        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700">
                            <p x-text="errorMessage"></p>
                            <button type="button" @click="createOrder" class="mt-2 font-semibold underline">إعادة المحاولة</button>
                        </div>
                    </template>
                </article>
            </aside>

            {{-- Products (CENTER) --}}
            <section class="order-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:col-span-7 lg:order-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-slate-900">المنتجات</h2>
                    <input
                        x-model="search"
                        type="search"
                        placeholder="ابحث عن صنف..."
                        class="w-full max-w-xs rounded-lg border-slate-200 bg-white text-sm sm:w-56"
                    />
                </div>

                {{-- Mobile/narrow categories: horizontal scroll (no dropdown) --}}
                <div class="mt-3 flex gap-1.5 overflow-x-auto pb-1 lg:hidden">
                    <button
                        type="button"
                        @click="selectedCategoryId = ''"
                        :class="selectedCategoryId === '' ? 'bg-[var(--hs-brand,#06C2A4)] text-white border-transparent' : 'bg-white text-slate-700 border-slate-200'"
                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                    >الكل</button>
                    <template x-for="category in categories" :key="'m-'+category.id">
                        <button
                            type="button"
                            @click="selectedCategoryId = String(category.id)"
                            :class="String(selectedCategoryId) === String(category.id) ? 'bg-[var(--hs-brand,#06C2A4)] text-white border-transparent' : 'bg-white text-slate-700 border-slate-200'"
                            class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                            x-text="category.name"
                        ></button>
                    </template>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                    <template x-for="item in filteredItems" :key="item.id">
                        <button
                            type="button"
                            @click="addItem(item)"
                            class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white text-right transition hover:border-[var(--hs-brand,#06C2A4)] hover:shadow-sm"
                        >
                            <div class="relative aspect-[4/3] w-full bg-slate-50">
                                <template x-if="item.image_path">
                                    <img :src="`/storage/${item.image_path}`" alt="" class="h-full w-full object-cover" />
                                </template>
                                <template x-if="!item.image_path">
                                    <div class="flex h-full w-full items-center justify-center text-slate-300">
                                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                </template>
                                <span class="absolute bottom-1 left-1 rounded bg-white/95 px-1.5 py-0.5 text-[10px] font-bold text-slate-900 shadow-sm">
                                    <span x-text="money(item.price)"></span>
                                    <span class="font-medium text-slate-500" x-text="item.currency"></span>
                                </span>
                            </div>
                            <div class="flex flex-1 flex-col gap-0.5 p-1.5">
                                <p class="line-clamp-2 text-[11px] font-semibold leading-snug text-slate-900" x-text="item.name"></p>
                                <p class="truncate text-[10px] text-slate-400" x-text="item.size_label || item.category?.name || ''"></p>
                                <span class="mt-auto pt-1 text-[10px] font-semibold text-[var(--hs-brand,#06C2A4)] opacity-0 transition group-hover:opacity-100">+ إضافة</span>
                            </div>
                        </button>
                    </template>
                </div>

                <template x-if="filteredItems.length === 0">
                    <div class="mt-8 text-center">
                        <p class="text-sm text-slate-500">لا توجد منتجات في هذا التصنيف.</p>
                        <button type="button" @click="selectedCategoryId = ''; search = ''" class="mt-2 text-sm font-semibold text-[var(--hs-brand,#06C2A4)] underline">عرض الكل</button>
                    </div>
                </template>
            </section>

            {{-- Categories sidebar (RIGHT in RTL = end) --}}
            <aside class="order-2 hidden lg:order-3 lg:col-span-2 lg:block">
                <nav class="sticky top-3 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="التصنيفات">
                    <p class="mb-2 px-2 text-[11px] font-bold tracking-wide text-slate-400">التصنيفات</p>
                    <button
                        type="button"
                        @click="selectedCategoryId = ''"
                        :class="selectedCategoryId === '' ? 'border-[var(--hs-brand,#06C2A4)] bg-[var(--hs-brand,#06C2A4)]/10 text-[var(--hs-brand,#067E6B)]' : 'border-transparent text-slate-700 hover:bg-slate-50'"
                        class="mb-1 flex w-full items-center justify-between rounded-lg border px-2.5 py-2 text-right text-xs font-semibold transition"
                    >
                        <span>الكل</span>
                        <span class="text-[10px] opacity-70" x-text="items.length"></span>
                    </button>
                    <template x-for="category in categories" :key="category.id">
                        <button
                            type="button"
                            @click="selectedCategoryId = String(category.id)"
                            :class="String(selectedCategoryId) === String(category.id) ? 'border-[var(--hs-brand,#06C2A4)] bg-[var(--hs-brand,#06C2A4)]/10 text-[var(--hs-brand,#067E6B)]' : 'border-transparent text-slate-700 hover:bg-slate-50'"
                            class="mb-1 flex w-full items-center justify-between rounded-lg border px-2.5 py-2 text-right text-xs font-semibold transition"
                        >
                            <span x-text="category.name"></span>
                            <span class="text-[10px] opacity-70" x-text="countInCategory(category.id)"></span>
                        </button>
                    </template>
                </nav>
            </aside>
        </div>

        {{-- Success dialog --}}
        <div
            x-show="successOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
            @keydown.escape.window="closeSuccess(false)"
        >
            <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-xl" @click.outside="closeSuccess(false)">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="mt-3 text-center text-base font-bold text-slate-900">تم إنشاء الطلب بنجاح</h3>
                <p class="mt-1 text-center text-sm text-slate-600" x-show="successOrderNumber">
                    رقم الطلب: #<span x-text="successOrderNumber"></span>
                </p>
                <div class="mt-5 grid gap-2">
                    <button
                        type="button"
                        @click="printInvoice"
                        :disabled="!successPrintUrl"
                        class="rounded-lg bg-[var(--hs-brand,#06C2A4)] px-3 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                    >طباعة الفاتورة</button>
                    <button type="button" @click="closeSuccess(false)" class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">بدون فاتورة</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cashierPos({ items, categories, cartEndpoints, storeOrderUrl }) {
            return {
                items,
                categories,
                cartEndpoints,
                storeOrderUrl,
                search: '',
                selectedCategoryId: '',
                cart: [],
                discount: 0,
                customerId: '',
                diningTableId: '',
                notes: '',
                submitting: false,
                errorMessage: '',
                successOpen: false,
                successOrderNumber: '',
                successPrintUrl: '',
                get filteredItems() {
                    return this.items.filter((item) => {
                        const matchesCategory = !this.selectedCategoryId || Number(item.pos_item_category_id) === Number(this.selectedCategoryId);
                        const term = this.search.trim().toLowerCase();
                        const matchesSearch = term === ''
                            || (item.name || '').toLowerCase().includes(term)
                            || (item.item_type || '').toLowerCase().includes(term)
                            || (item.category?.name || '').toLowerCase().includes(term);
                        return matchesCategory && matchesSearch;
                    });
                },
                countInCategory(categoryId) {
                    return this.items.filter((item) => Number(item.pos_item_category_id) === Number(categoryId)).length;
                },
                addItem(item) {
                    const existing = this.cart.find((line) => line.pos_menu_item_id === item.id);
                    if (existing) {
                        existing.quantity += 1;
                        this.syncCartAdd(item.id, 1);
                        return;
                    }
                    this.cart.push({
                        pos_menu_item_id: item.id,
                        key: `item_${item.id}`,
                        name: item.name,
                        unit_price: Number(item.price || 0),
                        currency: item.currency || '',
                        quantity: 1,
                    });
                    this.syncCartAdd(item.id, 1);
                },
                increase(index) {
                    this.cart[index].quantity += 1;
                    this.syncCartQty(this.cart[index]);
                },
                decrease(index) {
                    if (this.cart[index].quantity <= 1) {
                        this.removeLine(index);
                        return;
                    }
                    this.cart[index].quantity -= 1;
                    this.syncCartQty(this.cart[index]);
                },
                removeLine(index) {
                    const line = this.cart[index];
                    this.cart.splice(index, 1);
                    if (line?.key) {
                        this.cartFetch(`${this.cartEndpoints.removeItem}/${line.key}`, 'DELETE');
                    }
                },
                get subtotal() {
                    return this.cart.reduce((sum, line) => sum + (line.quantity * line.unit_price), 0);
                },
                get total() {
                    return Math.max(0, this.subtotal - Number(this.discount || 0));
                },
                get orderCurrencyLabel() {
                    const currencies = [...new Set(this.cart.map((line) => line.currency).filter(Boolean))];
                    if (currencies.length === 0) return '';
                    if (currencies.length === 1) return currencies[0];
                    return 'MIX';
                },
                money(amount) {
                    return Number(amount || 0).toFixed(2);
                },
                syncCartAdd(posMenuItemId, quantity) {
                    this.cartFetch(this.cartEndpoints.addItem, 'POST', {
                        pos_menu_item_id: posMenuItemId,
                        quantity,
                    });
                },
                syncCartQty(line) {
                    if (!line?.key) return;
                    this.cartFetch(`${this.cartEndpoints.updateItem}/${line.key}`, 'PATCH', {
                        quantity: line.quantity,
                    });
                },
                syncMeta() {
                    this.cartFetch(this.cartEndpoints.meta, 'POST', {
                        customer_id: this.customerId || null,
                        dining_table_id: this.diningTableId || null,
                        discount_amount: this.discount || 0,
                        notes: this.notes || null,
                    });
                },
                async cartFetch(url, method, body) {
                    try {
                        await fetch(url, {
                            method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.cartEndpoints.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: body ? JSON.stringify(body) : undefined,
                            credentials: 'same-origin',
                        });
                    } catch (error) {
                        console.warn('pos cart sync skipped', error);
                    }
                },
                clearLocalCart() {
                    this.cart = [];
                    this.discount = 0;
                    this.notes = '';
                    this.errorMessage = '';
                },
                openSuccess(payload) {
                    this.successOrderNumber = payload.order_number || payload.order_id || '';
                    this.successPrintUrl = payload.print_url || '';
                    this.successOpen = true;
                    this.clearLocalCart();
                    if (this.cartEndpoints.clear) {
                        this.cartFetch(this.cartEndpoints.clear, 'POST');
                    }
                },
                closeSuccess(shouldPrint) {
                    const url = this.successPrintUrl;
                    this.successOpen = false;
                    this.successPrintUrl = '';
                    this.successOrderNumber = '';
                    if (shouldPrint && url) {
                        window.open(url, '_blank', 'noopener');
                    }
                },
                printInvoice() {
                    this.closeSuccess(true);
                },
                async createOrder() {
                    if (this.submitting || this.cart.length === 0) return;
                    this.submitting = true;
                    this.errorMessage = '';
                    await this.syncMeta();

                    const body = {
                        customer_id: this.customerId || null,
                        dining_table_id: this.diningTableId || null,
                        discount_amount: this.discount || 0,
                        notes: this.notes || null,
                        items: this.cart.map((line) => ({
                            pos_menu_item_id: line.pos_menu_item_id,
                            quantity: line.quantity,
                        })),
                    };

                    try {
                        const response = await fetch(this.storeOrderUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.cartEndpoints.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(body),
                            credentials: 'same-origin',
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            this.errorMessage = payload.message || 'تعذر إنشاء الطلب، حاول مرة أخرى.';
                            return;
                        }
                        this.openSuccess(payload);
                    } catch (error) {
                        this.errorMessage = 'تعذر إنشاء الطلب، حاول مرة أخرى.';
                    } finally {
                        this.submitting = false;
                    }
                },
                async checkoutViaCart() {
                    if (this.submitting || this.cart.length === 0) return;
                    this.submitting = true;
                    this.errorMessage = '';
                    await this.syncMeta();
                    try {
                        const response = await fetch(this.cartEndpoints.checkout, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.cartEndpoints.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                customer_id: this.customerId || null,
                                dining_table_id: this.diningTableId || null,
                                discount_amount: this.discount || 0,
                                notes: this.notes || null,
                            }),
                            credentials: 'same-origin',
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            this.errorMessage = payload.message || 'تعذر إنشاء الطلب، حاول مرة أخرى.';
                            return;
                        }
                        this.openSuccess(payload);
                    } catch (error) {
                        this.errorMessage = 'تعذر إنشاء الطلب، حاول مرة أخرى.';
                    } finally {
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
@endsection
