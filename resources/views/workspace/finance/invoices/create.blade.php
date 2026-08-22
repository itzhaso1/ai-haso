@extends('layouts.financial', ['pageTitle' => 'إنشاء فاتورة'])

@section('content')
    @php
        $statusLabels = [
            'draft' => 'مسودة',
            'sent' => 'مرسلة',
            'unpaid' => 'غير مدفوعة',
        ];
    @endphp
    <div x-data="financeInvoiceBuilder()" class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">منشئ الفواتير</h2>
            <a href="{{ route('workspace.finance.invoices.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
        </div>

        <form method="POST" action="{{ route('workspace.finance.invoices.store') }}" class="space-y-4">
            @csrf

            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">نوع الفاتورة</label>
                    <select name="type" x-model="form.type" class="w-full rounded-lg border-slate-300 text-sm" required>
                        <option value="sales">فاتورة مبيعات</option>
                        <option value="purchase">فاتورة شراء</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">رقم الفاتورة (اختياري)</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="INV-000001">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الحالة المبدئية</label>
                    <select name="status" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach(['draft', 'sent', 'unpaid'] as $status)
                            <option value="{{ $status }}" @selected(old('status', 'unpaid') === $status)>{{ $statusLabels[$status] }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="form.type === 'sales'">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">العميل المسجل (اختياري)</label>
                    <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">اختر عميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} ({{ $customer->phone }})</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="form.type === 'sales'">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">اسم عميل نقدي/عابر (اختياري)</label>
                    <input
                        type="text"
                        name="customer_name"
                        value="{{ old('customer_name') }}"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        placeholder="مثال: عميل نقدي - نقطة البيع"
                    >
                    @error('customer_name')
                        <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="form.type === 'purchase'">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">المورد</label>
                    <select name="supplier_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">اختر مورد</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">تاريخ الإصدار</label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الشروط</label>
                    <input type="text" name="payment_terms" value="{{ old('payment_terms') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="مثال: صافي 30 يوم">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">العملة</label>
                    <input type="text" name="currency" value="{{ old('currency', 'SAR') }}" class="w-full rounded-lg border-slate-300 text-sm" maxlength="3">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">نوع الضريبة</label>
                    <select x-model="form.tax_profile_type" name="tax_profile_type" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($taxRates as $rate)
                            <option value="{{ $rate->type }}">{{ $rate->name }} ({{ number_format((float) $rate->rate, 2) }}%)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">نسبة VAT %</label>
                    <input type="number" step="0.01" min="0" max="100" x-model.number="form.tax_rate" name="tax_rate" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-right">المنتج</th>
                                <th class="px-3 py-2 text-right">الوصف</th>
                                <th class="px-3 py-2 text-right">الكمية</th>
                                <th class="px-3 py-2 text-right">سعر الوحدة</th>
                                <th class="px-3 py-2 text-right">الخصم</th>
                                <th class="px-3 py-2 text-right">نسبة الضريبة %</th>
                                <th class="px-3 py-2 text-right">الإجمالي</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, idx) in items" :key="idx">
                                <tr>
                                    <td class="px-3 py-2">
                                        <select class="w-40 rounded-md border-slate-300 text-xs" @change="applyProduct(idx, $event)">
                                            <option value="">اختيار</option>
                                            @foreach($products as $product)
                                                <option
                                                    value="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-price="{{ $product->price }}"
                                                >
                                                    {{ $product->name }} ({{ $product->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" x-model="item.product_id">
                                        <input type="hidden" x-model="item.product_name">
                                    </td>
                                    <td class="px-3 py-2">
                                        <textarea x-model="item.description" class="w-52 rounded-md border-slate-300 text-xs" rows="2"></textarea>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.001" min="0.001" x-model.number="item.quantity" @input="recalculate()" class="w-20 rounded-md border-slate-300 text-xs">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" x-model.number="item.unit_price" @input="recalculate()" class="w-24 rounded-md border-slate-300 text-xs">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" x-model.number="item.discount" @input="recalculate()" class="w-24 rounded-md border-slate-300 text-xs">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" step="0.01" min="0" max="100" x-model.number="item.tax_rate" @input="recalculate()" class="w-20 rounded-md border-slate-300 text-xs">
                                    </td>
                                    <td class="px-3 py-2 font-semibold text-slate-900" x-text="format(item.total)"></td>
                                    <td class="px-3 py-2">
                                        <button type="button" @click="removeItem(idx)" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-600">حذف</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 p-3">
                    <button type="button" @click="addItem()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        + إضافة سطر منتج
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">ملخص الحساب</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>الإجمالي قبل الضريبة</span><span x-text="format(summary.subtotal)"></span></div>
                        <div class="flex justify-between"><span>الخصم</span><span x-text="format(summary.discount)"></span></div>
                        <div class="flex justify-between"><span>المبلغ الخاضع للضريبة</span><span x-text="format(summary.taxable_amount)"></span></div>
                        <div class="flex justify-between"><span>ضريبة القيمة المضافة</span><span x-text="format(summary.tax_amount)"></span></div>
                        <div class="flex justify-between border-t pt-2 font-bold"><span>الإجمالي النهائي</span><span x-text="format(summary.total)"></span></div>
                        <div class="flex justify-between"><span>المدفوع</span><span x-text="format(0)"></span></div>
                        <div class="flex justify-between font-bold text-[#06C2A4]"><span>المتبقي</span><span x-text="format(summary.total)"></span></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">حفظ الفاتورة</h3>
                    <input type="hidden" name="items_json" :value="serializedItems">
                    @error('items_json')
                        <p class="mb-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-slate-500">
                        سيتم إعادة حساب جميع القيم في الـBackend قبل الحفظ لضمان الدقة المحاسبية.
                    </p>
                    <button type="submit" class="mt-4 rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">
                        حفظ الفاتورة
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function financeInvoiceBuilder() {
            return {
                form: {
                    type: 'sales',
                    tax_profile_type: 'standard',
                    tax_rate: 15,
                },
                items: [
                    {product_id: '', product_name: '', description: '', quantity: 1, unit_price: 0, discount: 0, tax_rate: 15, total: 0}
                ],
                summary: {
                    subtotal: 0,
                    discount: 0,
                    taxable_amount: 0,
                    tax_amount: 0,
                    total: 0
                },
                get serializedItems() {
                    return JSON.stringify(this.items.map((item) => ({
                        product_id: item.product_id || null,
                        product_name: item.product_name || '',
                        description: item.description || '',
                        quantity: Number(item.quantity || 0),
                        unit_price: Number(item.unit_price || 0),
                        discount: Number(item.discount || 0),
                        tax_rate: Number(item.tax_rate || this.form.tax_rate || 0),
                        tax_type: this.form.tax_profile_type,
                    })));
                },
                addItem() {
                    this.items.push({product_id: '', product_name: '', description: '', quantity: 1, unit_price: 0, discount: 0, tax_rate: Number(this.form.tax_rate || 0), total: 0});
                    this.recalculate();
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                    if (this.items.length === 0) this.addItem();
                    this.recalculate();
                },
                applyProduct(index, event) {
                    const option = event.target.selectedOptions[0];
                    this.items[index].product_id = option.value || '';
                    this.items[index].product_name = option.dataset.name || '';
                    if (!this.items[index].description) {
                        this.items[index].description = option.dataset.name || '';
                    }
                    if (option.dataset.price) {
                        this.items[index].unit_price = Number(option.dataset.price);
                    }
                    this.recalculate();
                },
                recalculate() {
                    let subtotal = 0;
                    let discount = 0;
                    let taxable = 0;
                    let tax = 0;
                    let total = 0;
                    this.items = this.items.map((item) => {
                        const qty = Math.max(0.001, Number(item.quantity || 0));
                        const unitPrice = Math.max(0, Number(item.unit_price || 0));
                        const lineDiscount = Math.max(0, Number(item.discount || 0));
                        const taxRate = Math.max(0, Number(item.tax_rate ?? this.form.tax_rate ?? 0));
                        const lineSubtotal = this.money(qty * unitPrice);
                        const boundedDiscount = Math.min(this.money(lineDiscount), lineSubtotal);
                        const taxableAmount = this.money(lineSubtotal - boundedDiscount);
                        const taxAmount = this.form.tax_profile_type === 'standard' ? this.money(taxableAmount * (taxRate / 100)) : 0;
                        const lineTotal = this.money(taxableAmount + taxAmount);

                        subtotal += lineSubtotal;
                        discount += boundedDiscount;
                        taxable += taxableAmount;
                        tax += taxAmount;
                        total += lineTotal;

                        return {...item, quantity: qty, unit_price: unitPrice, discount: boundedDiscount, tax_rate: taxRate, total: lineTotal};
                    });

                    this.summary = {
                        subtotal: this.money(subtotal),
                        discount: this.money(discount),
                        taxable_amount: this.money(taxable),
                        tax_amount: this.money(tax),
                        total: this.money(total),
                    };
                },
                money(value) {
                    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
                },
                format(value) {
                    return this.money(value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                },
                init() {
                    this.recalculate();
                    this.$watch('form.tax_profile_type', () => this.recalculate());
                    this.$watch('form.tax_rate', () => this.recalculate());
                }
            };
        }
    </script>
@endsection
