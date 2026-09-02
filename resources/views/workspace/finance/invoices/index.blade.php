@extends('layouts.financial', ['pageTitle' => 'الفواتير'])

@section('content')
    @php
        $invoiceStatusLabels = [
            'draft' => 'مسودة',
            'issued' => 'معتمدة',
            'cancelled' => 'ملغاة',
        ];
        $paymentStatusLabels = [
            'unpaid' => 'غير مدفوعة',
            'partial' => 'مدفوعة جزئيًا',
            'paid' => 'مدفوعة',
            'overdue' => 'متأخرة',
        ];
        $sort = request('sort', 'id');
        $direction = request('direction', 'desc');
        $nextDirection = $direction === 'asc' ? 'desc' : 'asc';
        $sortUrl = function (string $column) use ($nextDirection) {
            return request()->fullUrlWithQuery(['sort' => $column, 'direction' => request('sort') === $column ? $nextDirection : 'desc']);
        };
    @endphp
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-slate-900">الفواتير</h2>
                <p class="text-xs text-slate-500">بحث، تصفية، وإجراءات الفوترة المحاسبية</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('workspace.finance.billing.dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">لوحة الفوترة</a>
                <a href="{{ route('workspace.finance.invoices.create') }}" class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">إنشاء فاتورة جديدة</a>
            </div>
        </div>

        <form method="GET" action="{{ route('workspace.finance.invoices.index') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم الفاتورة أو الاسم" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
            <select name="type" class="rounded-lg border-slate-300 text-sm">
                <option value="">كل الأنواع</option>
                <option value="sales" @selected(request('type') === 'sales')>مبيعات</option>
                <option value="purchase" @selected(request('type') === 'purchase')>مشتريات</option>
            </select>
            <select name="customer_id" class="rounded-lg border-slate-300 text-sm">
                <option value="">كل العملاء</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <select name="invoice_status" class="rounded-lg border-slate-300 text-sm">
                <option value="">حالة الفاتورة: الكل</option>
                @foreach(['draft', 'issued', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('invoice_status') === $status)>{{ $invoiceStatusLabels[$status] }}</option>
                @endforeach
            </select>
            <select name="payment_status" class="rounded-lg border-slate-300 text-sm">
                <option value="">حالة الدفع: الكل</option>
                @foreach(['unpaid', 'partial', 'paid', 'overdue'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ $paymentStatusLabels[$status] }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-slate-300 text-sm" aria-label="من تاريخ">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-slate-300 text-sm" aria-label="إلى تاريخ">
            <input type="text" name="currency" value="{{ request('currency') }}" maxlength="3" placeholder="العملة" class="rounded-lg border-slate-300 text-sm">
            <div class="flex gap-2 xl:col-span-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">تطبيق الفلاتر</button>
                <a href="{{ route('workspace.finance.invoices.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">إعادة ضبط</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-3 text-right font-semibold"><a href="{{ $sortUrl('invoice_number') }}" class="hover:underline">رقم الفاتورة</a></th>
                            <th class="px-3 py-3 text-right font-semibold">النوع</th>
                            <th class="px-3 py-3 text-right font-semibold">العميل/المورد</th>
                            <th class="px-3 py-3 text-right font-semibold"><a href="{{ $sortUrl('issue_date') }}" class="hover:underline">الإصدار</a></th>
                            <th class="px-3 py-3 text-right font-semibold"><a href="{{ $sortUrl('due_date') }}" class="hover:underline">الاستحقاق</a></th>
                            <th class="px-3 py-3 text-right font-semibold"><a href="{{ $sortUrl('total') }}" class="hover:underline">الإجمالي</a></th>
                            <th class="px-3 py-3 text-right font-semibold"><a href="{{ $sortUrl('amount_due') }}" class="hover:underline">المتبقي</a></th>
                            <th class="px-3 py-3 text-right font-semibold">حالة الفاتورة</th>
                            <th class="px-3 py-3 text-right font-semibold">حالة الدفع</th>
                            <th class="px-3 py-3 text-right font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $invoice)
                            @php
                                $invoiceState = $invoice->invoice_status
                                    ?? (in_array($invoice->status, ['draft', 'cancelled'], true) ? $invoice->status : 'issued');
                                $paymentState = $invoice->payment_status
                                    ?? (in_array($invoice->status, ['unpaid', 'partial', 'paid', 'overdue'], true) ? $invoice->status : 'unpaid');
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $invoice->invoice_number }}</td>
                                <td class="px-3 py-3">{{ $invoice->type === 'sales' ? 'مبيعات' : 'مشتريات' }}</td>
                                <td class="px-3 py-3">{{ $invoice->customer?->name ?? $invoice->customer_name ?? $invoice->supplier?->name ?? '-' }}</td>
                                <td class="px-3 py-3">{{ optional($invoice->issue_date)->format('Y-m-d') }}</td>
                                <td class="px-3 py-3">{{ optional($invoice->due_date)->format('Y-m-d') ?: '—' }}</td>
                                <td class="px-3 py-3">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
                                <td class="px-3 py-3 font-semibold">{{ number_format((float) $invoice->amount_due, 2) }}</td>
                                <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $invoiceStatusLabels[$invoiceState] ?? $invoiceState }}</span></td>
                                <td class="px-3 py-3"><span class="rounded-full {{ $paymentState === 'overdue' ? 'bg-rose-100 text-rose-700' : ($paymentState === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700') }} px-2 py-1 text-xs font-semibold">{{ $paymentStatusLabels[$paymentState] ?? $paymentState }}</span></td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                        <a href="{{ route('workspace.finance.invoices.show', $invoice) }}" class="text-[#06C2A4] hover:underline">عرض</a>
                                        @if($invoiceState === 'draft')
                                            <a href="{{ route('workspace.finance.invoices.edit', $invoice) }}" class="text-slate-700 hover:underline">تعديل</a>
                                        @endif
                                        <a href="{{ route('workspace.finance.invoices.pdf', $invoice) }}" class="text-slate-700 hover:underline">PDF</a>
                                        @if($invoiceState === 'issued' && in_array($paymentState, ['unpaid', 'partial', 'overdue'], true))
                                            <a href="{{ route('workspace.finance.invoices.show', $invoice) }}#payments" class="text-slate-700 hover:underline">دفعة</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-10 text-center text-slate-500">
                                    لا توجد فواتير مطابقة للفلاتر الحالية.
                                    <div class="mt-3">
                                        <a href="{{ route('workspace.finance.invoices.create') }}" class="text-sm font-semibold text-[#06C2A4] hover:underline">إنشاء أول فاتورة</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $invoices->links() }}</div>
    </div>
@endsection
