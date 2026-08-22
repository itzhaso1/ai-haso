@extends('layouts.financial', ['pageTitle' => 'التقارير المالية'])

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-bold text-slate-900">التقارير</h2>
            <form method="GET" action="{{ route('workspace.finance.reports.index') }}" class="flex items-center gap-2">
                <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-slate-300 text-sm">
                <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث</button>
            </form>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">إجمالي المبيعات</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((float) ($salesSummary->total_sales ?? 0), 2) }}</p>
                <p class="text-xs text-slate-500">الفواتير: {{ $salesSummary->invoices_count ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">إجمالي المشتريات</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((float) ($purchaseSummary->total_purchases ?? 0), 2) }}</p>
                <p class="text-xs text-slate-500">الفواتير: {{ $purchaseSummary->invoices_count ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">إجمالي المصروفات</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((float) ($expenseSummary->total_expenses ?? 0), 2) }}</p>
                <p class="text-xs text-slate-500">العناصر: {{ $expenseSummary->expenses_count ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">صافي VAT</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((float) $vat['net'], 2) }}</p>
                <p class="text-xs text-slate-500">Output {{ number_format((float) $vat['output'], 2) }} / Input {{ number_format((float) $vat['input'], 2) }}</p>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">المبيعات حسب العملاء</h3>
                <div class="space-y-2">
                    @forelse($salesByCustomer as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->customer_name }}</span>
                            <span>{{ number_format((float) $row->total, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد بيانات.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">المصروفات حسب التصنيف</h3>
                <div class="space-y-2">
                    @forelse($expensesByCategory as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>{{ $row->category_name }}</span>
                            <span>{{ number_format((float) $row->total, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد بيانات.</p>
                    @endforelse
                </div>
            </article>
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold">ملخص ضريبة القيمة المضافة</h3>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-3 text-sm">ضريبة المخرجات: <strong>{{ number_format((float) $vat['output'], 2) }}</strong></div>
                <div class="rounded-lg bg-slate-50 p-3 text-sm">ضريبة المدخلات: <strong>{{ number_format((float) $vat['input'], 2) }}</strong></div>
                <div class="rounded-lg bg-slate-50 p-3 text-sm">صافي الضريبة: <strong>{{ number_format((float) $vat['net'], 2) }}</strong></div>
            </div>
        </article>
    </div>
@endsection
