@extends('layouts.financial', ['pageTitle' => 'الفواتير'])

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-bold text-slate-900">الفواتير</h2>
            <a href="{{ route('workspace.finance.invoices.create') }}" class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">
                إنشاء فاتورة جديدة
            </a>
        </div>

        <form method="GET" action="{{ route('workspace.finance.invoices.index') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم/حالة الفاتورة" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
            <select name="type" class="rounded-lg border-slate-300 text-sm">
                <option value="">كل الأنواع</option>
                <option value="sales" @selected(request('type') === 'sales')>مبيعات</option>
                <option value="purchase" @selected(request('type') === 'purchase')>مشتريات</option>
            </select>
            <select name="status" class="rounded-lg border-slate-300 text-sm">
                <option value="">كل الحالات</option>
                @foreach(['draft', 'sent', 'unpaid', 'partial', 'paid', 'overdue', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white sm:col-span-4">تطبيق الفلاتر</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-3 text-right font-semibold">رقم الفاتورة</th>
                        <th class="px-3 py-3 text-right font-semibold">النوع</th>
                        <th class="px-3 py-3 text-right font-semibold">العميل/المورد</th>
                        <th class="px-3 py-3 text-right font-semibold">الإجمالي</th>
                        <th class="px-3 py-3 text-right font-semibold">المدفوع</th>
                        <th class="px-3 py-3 text-right font-semibold">المتبقي</th>
                        <th class="px-3 py-3 text-right font-semibold">الحالة</th>
                        <th class="px-3 py-3 text-right font-semibold">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-3 py-3 font-semibold text-slate-900">{{ $invoice->invoice_number }}</td>
                            <td class="px-3 py-3">{{ $invoice->type === 'sales' ? 'مبيعات' : 'مشتريات' }}</td>
                            <td class="px-3 py-3">{{ $invoice->customer?->name ?? $invoice->supplier?->name ?? '-' }}</td>
                            <td class="px-3 py-3">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
                            <td class="px-3 py-3">{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                            <td class="px-3 py-3">{{ number_format((float) $invoice->amount_due, 2) }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ $invoice->status }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <a href="{{ route('workspace.finance.invoices.show', $invoice) }}" class="text-[#06C2A4] hover:underline">عرض</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-slate-500">لا توجد فواتير حتى الآن.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $invoices->links() }}</div>
    </div>
@endsection
