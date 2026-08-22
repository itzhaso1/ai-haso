@extends('layouts.financial', ['pageTitle' => 'عرض الفاتورة'])

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $invoice->invoice_number }}</h2>
                <p class="text-sm text-slate-500">{{ $invoice->type === 'sales' ? 'Sales Invoice' : 'Purchase Invoice' }} — {{ $invoice->status }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('workspace.finance.invoices.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
                <a href="{{ route('workspace.finance.invoices.pdf', $invoice) }}" target="_blank" class="rounded-lg border border-[#06C2A4] px-4 py-2 text-sm font-semibold text-[#06C2A4] hover:bg-[#E8FAF6]">PDF</a>
                @if(!in_array($invoice->status, ['cancelled', 'paid'], true))
                    <form method="POST" action="{{ route('workspace.finance.invoices.cancel', $invoice) }}" onsubmit="return confirm('تأكيد إلغاء الفاتورة؟');">
                        @csrf
                        <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">إلغاء الفاتورة</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <div class="grid gap-3 sm:grid-cols-2 text-sm">
                    <p><span class="font-semibold">العميل:</span> {{ $invoice->customer?->name ?? '-' }}</p>
                    <p><span class="font-semibold">المورد:</span> {{ $invoice->supplier?->name ?? '-' }}</p>
                    <p><span class="font-semibold">تاريخ الإصدار:</span> {{ $invoice->issue_date }}</p>
                    <p><span class="font-semibold">تاريخ الاستحقاق:</span> {{ $invoice->due_date ?? '-' }}</p>
                    <p><span class="font-semibold">VAT:</span> {{ number_format((float) $invoice->tax_rate, 2) }}%</p>
                    <p><span class="font-semibold">العملة:</span> {{ $invoice->currency }}</p>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-2 py-2 text-right">المنتج</th>
                                <th class="px-2 py-2 text-right">الكمية</th>
                                <th class="px-2 py-2 text-right">السعر</th>
                                <th class="px-2 py-2 text-right">الخصم</th>
                                <th class="px-2 py-2 text-right">VAT</th>
                                <th class="px-2 py-2 text-right">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-2 py-2">{{ $item->product_name }}</td>
                                    <td class="px-2 py-2">{{ number_format((float) $item->quantity, 3) }}</td>
                                    <td class="px-2 py-2">{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="px-2 py-2">{{ number_format((float) $item->discount, 2) }}</td>
                                    <td class="px-2 py-2">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                    <td class="px-2 py-2 font-semibold">{{ number_format((float) $item->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-sm">
                <h3 class="mb-2 text-sm font-bold">ملخص المبالغ</h3>
                <div class="space-y-1">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>{{ number_format((float) $invoice->discount, 2) }}</span></div>
                    <div class="flex justify-between"><span>Taxable</span><span>{{ number_format((float) $invoice->taxable_amount, 2) }}</span></div>
                    <div class="flex justify-between"><span>VAT</span><span>{{ number_format((float) $invoice->tax_amount, 2) }}</span></div>
                    <div class="flex justify-between border-t pt-2 font-bold"><span>Total</span><span>{{ number_format((float) $invoice->total, 2) }}</span></div>
                    <div class="flex justify-between"><span>Paid</span><span>{{ number_format((float) $invoice->amount_paid, 2) }}</span></div>
                    <div class="flex justify-between font-bold text-[#06C2A4]"><span>Due</span><span>{{ number_format((float) $invoice->amount_due, 2) }}</span></div>
                </div>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">تسجيل دفعة</h3>
                @if(in_array($invoice->status, ['unpaid', 'partial', 'overdue', 'sent'], true))
                    <form method="POST" action="{{ route('workspace.finance.invoices.payments.store', $invoice) }}" class="grid gap-2 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">تاريخ الدفعة</label>
                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">المبلغ</label>
                            <input type="number" step="0.01" min="0.01" max="{{ (float) $invoice->amount_due }}" name="amount" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">الطريقة</label>
                            <select name="method" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">حساب النقد/البنك</label>
                            <select name="treasury_account_id" class="w-full rounded-lg border-slate-300 text-sm">
                                <option value="">افتراضي</option>
                                @foreach($treasuryAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">المرجع/الملاحظة</label>
                            <input type="text" name="reference" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <button class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">حفظ الدفعة</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-slate-500">الفاتورة غير قابلة لتسجيل دفعات جديدة في حالتها الحالية.</p>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">سجل الدفعات</h3>
                <div class="space-y-2">
                    @forelse($invoice->payments as $payment)
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ number_format((float) $payment->amount, 2) }} — {{ $payment->method }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->payment_date }} | {{ $payment->treasuryAccount?->name ?? '-' }}</p>
                            @if($payment->reference)
                                <p class="mt-1 text-xs text-slate-500">Ref: {{ $payment->reference }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد دفعات مسجلة بعد.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>
@endsection
