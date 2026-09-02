@extends('layouts.financial', ['pageTitle' => 'فاتورة '.$invoice->invoice_number])

@php
    $invoiceStatus = $invoice->invoice_status ?: $invoice->status;
    $paymentStatus = $invoice->payment_status ?: $invoice->status;
    $isDraft = $invoiceStatus === 'draft';
    $isCancelled = $invoiceStatus === 'cancelled';
    $statusClass = match ($invoiceStatus) {
        'cancelled' => 'bg-slate-100 text-slate-600',
        'draft' => 'bg-slate-50 text-slate-500',
        'issued' => 'bg-indigo-50 text-indigo-700',
        default => 'bg-amber-50 text-amber-700',
    };
    $payClass = match ($paymentStatus) {
        'paid' => 'bg-emerald-50 text-emerald-700',
        'partial' => 'bg-sky-50 text-sky-700',
        'overdue' => 'bg-rose-50 text-rose-700',
        default => 'bg-amber-50 text-amber-700',
    };
    $invoiceStatusLabels = ['draft' => 'مسودة', 'issued' => 'معتمدة', 'cancelled' => 'ملغاة'];
    $paymentStatusLabels = ['unpaid' => 'غير مدفوعة', 'partial' => 'مدفوعة جزئيًا', 'paid' => 'مدفوعة', 'overdue' => 'متأخرة'];
    $dueToday = $invoice->due_date && $invoice->due_date->isToday() && in_array($paymentStatus, ['unpaid', 'partial'], true);
    $canPay = (float) $invoice->amount_due > 0.009 && ! $isDraft && ! $isCancelled;
@endphp

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">فاتورة {{ $invoice->invoice_number }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $invoice->customer_name ?: optional($invoice->customer)->name ?: optional($invoice->supplier)->name }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">مستند: {{ $invoiceStatusLabels[$invoiceStatus] ?? $invoiceStatus }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $payClass }}">دفع: {{ $paymentStatusLabels[$paymentStatus] ?? $paymentStatus }}</span>
                    @if($dueToday)
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-orange-700">مستحقة اليوم</span>
                    @endif
                    <span class="text-sm text-slate-500">{{ $invoice->type === 'purchase' ? 'مشتريات' : 'مبيعات' }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('workspace.finance.invoices.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
                <a href="{{ route('workspace.finance.invoices.pdf', $invoice) }}" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">PDF</a>
                @if($isDraft)
                    <a href="{{ route('workspace.finance.invoices.edit', $invoice) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">تعديل</a>
                    <form method="POST" action="{{ route('workspace.finance.invoices.issue', $invoice) }}" onsubmit="return confirm('إصدار الفاتورة وترحيل القيد المحاسبي؟')">
                        @csrf
                        <button class="rounded-lg bg-[#06C2A4] px-3 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">إصدار</button>
                    </form>
                @endif
                @if(! $isCancelled)
                    <form method="POST" action="{{ route('workspace.finance.invoices.cancel', $invoice) }}" onsubmit="return confirm('إلغاء الفاتورة؟ لن يُحذف القيد المحاسبي بل يُعكس بقيد جديد. يجب عكس الدفعات القائمة أولاً.')">
                        @csrf
                        <button class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">إلغاء</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold text-slate-500">الإجمالي</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold text-slate-500">المدفوع</p>
                <p class="mt-1 text-2xl font-black text-emerald-700">{{ number_format((float) $invoice->amount_paid, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold text-slate-500">المتبقي</p>
                <p class="mt-1 text-2xl font-black {{ (float) $invoice->amount_due > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ number_format((float) $invoice->amount_due, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold text-slate-500">الاستحقاق</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ optional($invoice->due_date)->format('Y-m-d') ?: '—' }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">الملخص</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">رقم الفاتورة</dt><dd class="font-semibold">{{ $invoice->invoice_number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">تاريخ الإصدار</dt><dd>{{ optional($invoice->issue_date)->format('Y-m-d') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">الشروط</dt><dd>{{ $invoice->payment_terms ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">الإجمالي قبل الضريبة</dt><dd>{{ number_format((float) $invoice->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">الخصم</dt><dd>{{ number_format((float) $invoice->discount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">الخاضع للضريبة</dt><dd>{{ number_format((float) $invoice->taxable_amount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">الضريبة</dt><dd>{{ number_format((float) $invoice->tax_amount, 2) }} ({{ number_format((float) $invoice->tax_rate, 2) }}%)</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">دائن / مدين</dt><dd>{{ number_format((float) ($invoice->amount_credited ?? 0), 2) }} / {{ number_format((float) ($invoice->amount_debited ?? 0), 2) }}</dd></div>
                    @if($invoice->contract)
                        <div class="flex justify-between"><dt class="text-slate-500">العقد</dt><dd><a class="font-semibold text-[#06C2A4] hover:underline" href="{{ route('workspace.finance.contracts.show', $invoice->contract) }}">{{ $invoice->contract->contract_number }}</a></dd></div>
                    @endif
                </dl>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">العميل / المورد</h3>
                @if($invoice->type === 'purchase')
                    <p class="mt-3 text-sm font-semibold">{{ optional($invoice->supplier)->name ?: '—' }}</p>
                    <p class="text-xs text-slate-500">{{ optional($invoice->supplier)->vat_number }}</p>
                    <p class="text-xs text-slate-500">{{ optional($invoice->supplier)->email }}</p>
                @else
                    <p class="mt-3 text-sm font-semibold">{{ $invoice->customer_name ?: optional($invoice->customer)->name }}</p>
                    <p class="text-xs text-slate-500">{{ optional($invoice->customer)->email }}</p>
                    <p class="text-xs text-slate-500">{{ optional($invoice->customer)->phone }}</p>
                    <p class="text-xs text-slate-500">الرقم الضريبي: {{ optional($invoice->customer)->vat_number ?: '—' }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">ملاحظات</h3>
                <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $invoice->notes ?: 'لا توجد ملاحظات.' }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3 text-sm font-bold">البنود</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-right">الوصف</th>
                            <th class="px-4 py-2 text-right">الكمية</th>
                            <th class="px-4 py-2 text-right">السعر</th>
                            <th class="px-4 py-2 text-right">الخصم</th>
                            <th class="px-4 py-2 text-right">الضريبة</th>
                            <th class="px-4 py-2 text-right">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-2">{{ $item->product_name ?: $item->description }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->discount, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                <td class="px-4 py-2 font-semibold">{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">لا توجد بنود.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="payments" class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold">الدفعات</h3>
                    @if($canPay)
                        <span class="text-xs text-slate-500">سجل دفعة أدناه</span>
                    @endif
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="py-1 text-right">التاريخ</th>
                                <th class="py-1 text-right">المبلغ</th>
                                <th class="py-1 text-right">الطريقة</th>
                                <th class="py-1 text-right">المرجع</th>
                                <th class="py-1 text-right">الحالة</th>
                                <th class="py-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->payments as $payment)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2">{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                                    <td class="py-2 font-semibold">{{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="py-2">{{ $payment->method }}</td>
                                    <td class="py-2 text-xs">{{ $payment->reference ?: '—' }}</td>
                                    <td class="py-2">{{ $payment->status ?: 'posted' }}</td>
                                    <td class="py-2">
                                        @if(($payment->status ?: 'posted') === 'posted' && ! $isCancelled)
                                            <form method="POST" action="{{ route('workspace.finance.invoices.payments.reverse', [$invoice, $payment]) }}" onsubmit="return confirm('عكس هذه الدفعة بقيد محاسبي جديد؟')">
                                                @csrf
                                                <input type="hidden" name="reversal_reason" value="عكس دفعة من واجهة الفاتورة">
                                                <button class="text-xs font-semibold text-rose-600">عكس</button>
                                            </form>
                                        @endif
                                        <p class="text-[11px] text-slate-400">{{ optional($payment->creator)->name }}</p>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-500">لا توجد دفعات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold">إشعارات دائن / مدين</h3>
                    @if(! $isDraft && ! $isCancelled)
                        <a href="{{ route('workspace.finance.invoices.credit-notes.create', $invoice) }}" class="text-xs font-semibold text-[#06C2A4] hover:underline">إنشاء</a>
                    @endif
                </div>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse($invoice->creditNotes as $note)
                        <li class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ $note->note_number }} · {{ $note->type === 'debit' ? 'مدين' : 'دائن' }} · {{ $note->status }}</span>
                                <span class="font-semibold">{{ number_format((float) $note->total, 2) }}</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ $note->reason }}</p>
                            <div class="mt-2 flex gap-2">
                                @if($note->status === 'draft')
                                    <form method="POST" action="{{ route('workspace.finance.invoices.credit-notes.issue', [$invoice, $note]) }}" onsubmit="return confirm('إصدار الإشعار وترحيله محاسبياً؟')">
                                        @csrf
                                        <button class="text-xs font-semibold text-[#06C2A4]">إصدار</button>
                                    </form>
                                @endif
                                @if($note->status !== 'cancelled')
                                    <form method="POST" action="{{ route('workspace.finance.invoices.credit-notes.cancel', [$invoice, $note]) }}" onsubmit="return confirm('إلغاء هذا الإشعار؟')">
                                        @csrf
                                        <button class="text-xs font-semibold text-rose-600">إلغاء</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-slate-500">لا توجد إشعارات.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if($canPay)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold">تسجيل دفعة</h3>
                <form method="POST" action="{{ route('workspace.finance.invoices.payments.store', $invoice) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">المبلغ</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $invoice->amount_due }}" name="amount" value="{{ old('amount', $invoice->amount_due) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                        @error('amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">طريقة الدفع</label>
                        <select name="method" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach(['cash' => 'نقد', 'bank_transfer' => 'تحويل بنكي', 'card' => 'بطاقة', 'other' => 'أخرى'] as $method => $label)
                                <option value="{{ $method }}" @selected(old('method') === $method)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">حساب الخزينة</label>
                        <select name="treasury_account_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">بدون / الافتراضي</option>
                            @foreach($treasuryAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('treasury_account_id') === (string) $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">المرجع</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">تاريخ الدفع</label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظات</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="md:col-span-3">
                        <button class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">حفظ الدفعة</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold">المستندات</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="font-semibold text-[#06C2A4] hover:underline" href="{{ route('workspace.finance.invoices.pdf', $invoice) }}">تحميل PDF</a></li>
                    @foreach($invoice->attachments as $attachment)
                        <li class="flex items-center justify-between gap-2">
                            <a class="text-slate-700 hover:underline" href="{{ route('workspace.finance.invoices.attachments.download', [$invoice, $attachment]) }}">{{ $attachment->file_name ?: ('مرفق #'.$attachment->id) }}</a>
                            @if($isDraft)
                                <form method="POST" action="{{ route('workspace.finance.invoices.attachments.destroy', [$invoice, $attachment]) }}" onsubmit="return confirm('حذف المرفق؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-rose-600">حذف</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($isDraft)
                    <form method="POST" action="{{ route('workspace.finance.invoices.attachments.store', $invoice) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                        @csrf
                        <input type="file" name="attachments[]" multiple class="text-sm">
                        <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">رفع</button>
                    </form>
                @endif
            </div>

            @if($canViewAccounting)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold">القيود المحاسبية</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse($journalEntries as $entry)
                            <li class="rounded-lg bg-slate-50 px-3 py-2">
                                <div class="flex justify-between">
                                    <span class="font-semibold">{{ $entry->entry_number }}</span>
                                    <span>{{ $entry->type }}</span>
                                </div>
                                <p class="text-xs text-slate-500">مدين {{ number_format((float) $entry->lines->sum('debit'), 2) }} / دائن {{ number_format((float) $entry->lines->sum('credit'), 2) }}</p>
                            </li>
                        @empty
                            <li class="text-slate-500">لا توجد قيود مرتبطة.</li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-bold">التسلسل الزمني / التدقيق</h3>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="text-slate-600">أنشأها {{ optional($invoice->creator)->name ?: '—' }} في {{ optional($invoice->created_at)->format('Y-m-d H:i') }}</li>
                @if($invoice->issued_at)
                    <li class="text-slate-600">أصدرها {{ optional($invoice->issuer)->name ?: '—' }} في {{ optional($invoice->issued_at)->format('Y-m-d H:i') }}</li>
                @endif
                @if($invoice->cancelled_at)
                    <li class="text-slate-600">أُلغيت في {{ optional($invoice->cancelled_at)->format('Y-m-d H:i') }}</li>
                @endif
                @forelse($auditLogs as $log)
                    <li class="rounded-lg bg-slate-50 px-3 py-2">
                        <span class="font-semibold">{{ $log->action }}</span>
                        <span class="text-xs text-slate-500">{{ optional($log->created_at)->format('Y-m-d H:i') }} · مستخدم #{{ $log->user_id }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">لا توجد سجلات تدقيق إضافية.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
