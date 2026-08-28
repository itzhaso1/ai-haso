@extends('layouts.financial', ['pageTitle' => 'HASem Financial Dashboard'])

@section('content')
    @php
        $metricCards = [
            ['label' => 'إجمالي المبيعات', 'value' => $cards['sales_total'] ?? 0],
            ['label' => 'إجمالي المشتريات', 'value' => $cards['purchases_total'] ?? 0],
            ['label' => 'إجمالي المصروفات', 'value' => $cards['expenses_total'] ?? 0],
            ['label' => 'صافي الربح', 'value' => $cards['net_profit'] ?? 0],
            ['label' => 'مستحقات العملاء', 'value' => $cards['receivables_total'] ?? 0],
            ['label' => 'مستحقات الموردين', 'value' => $cards['payables_total'] ?? 0],
            ['label' => 'الفواتير غير المدفوعة', 'value' => $cards['unpaid_invoices'] ?? 0],
            ['label' => 'الفواتير المتأخرة', 'value' => $cards['overdue_invoices'] ?? 0],
            ['label' => 'VAT المخرجة', 'value' => $cards['output_vat'] ?? 0],
            ['label' => 'VAT المدخلة', 'value' => $cards['input_vat'] ?? 0],
            ['label' => 'صافي VAT', 'value' => $cards['net_vat'] ?? 0],
            ['label' => 'رصيد الصندوق', 'value' => $cards['cash_balance'] ?? 0],
            ['label' => 'رصيد البنوك', 'value' => $cards['bank_balance'] ?? 0],
            ['label' => 'عدد موظفي الشركة', 'value' => $cards['company_employees'] ?? 0],
            ['label' => 'إجمالي الرواتب المدفوعة', 'value' => $cards['payroll_paid_total'] ?? 0],
            ['label' => 'إجمالي البدلات والمكافآت', 'value' => $cards['allowances_bonuses_total'] ?? 0],
            ['label' => 'إجمالي الخصومات', 'value' => $cards['deductions_total'] ?? 0],
            ['label' => 'إجمالي السلف المفتوحة', 'value' => $cards['open_advances_total'] ?? 0],
            ['label' => 'العقود الفعالة', 'value' => $cards['active_contracts_count'] ?? 0],
        ];

        $seriesMap = [
            'Sales' => $charts['sales'] ?? [],
            'Expenses' => $charts['expenses'] ?? [],
            'Profit' => $charts['profit'] ?? [],
            'VAT' => $charts['vat'] ?? [],
            'Cash Flow' => $charts['cash_flow'] ?? [],
        ];
    @endphp

    <div class="space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($metricCards as $card)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">
                        {{ is_numeric($card['value']) ? number_format((float) $card['value'], 2) : $card['value'] }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @foreach($seriesMap as $title => $series)
                @php
                    $maxValue = max(1, collect($series)->max('value') ?? 1);
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">{{ $title }}</h3>
                    <div class="space-y-2">
                        @forelse($series as $point)
                            @php
                                $width = min(100, ((float) $point['value'] / $maxValue) * 100);
                            @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                    <span>{{ $point['month'] }}</span>
                                    <span>{{ number_format((float) $point['value'], 2) }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-[#06C2A4]" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">لا توجد بيانات رسم بياني بعد.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">آخر الفواتير</h3>
                <div class="space-y-2">
                    @forelse(($latest['invoices'] ?? []) as $invoice)
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $invoice->invoice_number }} — {{ $invoice->type }}</p>
                            <p class="text-xs text-slate-500">فاتورة: {{ $invoice->invoice_status ?? $invoice->status }} | دفع: {{ $invoice->payment_status ?? $invoice->status }} | {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد فواتير.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">آخر المدفوعات</h3>
                <div class="space-y-2">
                    @forelse(($latest['payments'] ?? []) as $payment)
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $payment->invoice?->invoice_number ?? 'N/A' }} — {{ $payment->method }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->payment_date }} | {{ number_format((float) $payment->amount, 2) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مدفوعات.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">آخر المصروفات</h3>
                <div class="space-y-2">
                    @forelse(($latest['expenses'] ?? []) as $expense)
                        <div class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $expense->expense_number }} — {{ $expense->status }}</p>
                            <p class="text-xs text-slate-500">{{ $expense->expense_date }} | {{ number_format((float) $expense->total, 2) }} {{ $expense->currency }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مصروفات.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">الفواتير المتأخرة</h3>
                <div class="space-y-2">
                    @forelse(($latest['overdue_invoices'] ?? []) as $invoice)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm">
                            <p class="font-semibold">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs text-red-700">تاريخ الاستحقاق: {{ $invoice->due_date }} | المتبقي: {{ number_format((float) $invoice->amount_due, 2) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد فواتير متأخرة.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
