@extends('layouts.pos', ['pageTitle' => 'التقارير اليومية'])

@section('content')
    <section class="space-y-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">ملخص اليوم</h2>
                    <p class="text-xs text-slate-500">تقرير الكاشير حسب التاريخ المحدد.</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="date" value="{{ $date }}" class="rounded-lg border-slate-300 text-sm" />
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث</button>
                </form>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">إجمالي مبيعات الفواتير</p>
                    <p class="mt-1 text-xl font-bold">{{ number_format((float) $summary['invoice_sales_total'], 2) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">عدد الفواتير</p>
                    <p class="mt-1 text-xl font-bold">{{ $summary['invoices_count'] }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">إجمالي الكميات</p>
                    <p class="mt-1 text-xl font-bold">{{ number_format((int) $summary['total_quantity']) }}</p>
                </div>
            </div>
        </article>

        <div class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">إجمالي الكميات حسب النوع</h3>
                <table class="w-full text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 text-right">النوع</th>
                            <th class="py-2 text-right">الكمية</th>
                            <th class="py-2 text-right">المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quantityByType as $row)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $row->item_type }}</td>
                                <td class="py-2">{{ (int) $row->quantity }}</td>
                                <td class="py-2">{{ number_format((float) $row->sales, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-center text-slate-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">أكثر الأنواع مبيعًا</h3>
                <table class="w-full text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 text-right">النوع</th>
                            <th class="py-2 text-right">العدد</th>
                            <th class="py-2 text-right">المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topTypes as $row)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $row->item_type }}</td>
                                <td class="py-2">{{ (int) $row->quantity }}</td>
                                <td class="py-2">{{ number_format((float) $row->sales, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-center text-slate-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">أكثر الأصناف مبيعًا بالتفصيل</h3>
                <table class="w-full text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 text-right">الصنف</th>
                            <th class="py-2 text-right">الكمية</th>
                            <th class="py-2 text-right">المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topItems as $row)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $row->product_name }}</td>
                                <td class="py-2">{{ (int) $row->quantity }}</td>
                                <td class="py-2">{{ number_format((float) $row->sales, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-center text-slate-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-slate-900">آخر العمليات</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 text-right">الوقت</th>
                            <th class="py-2 text-right">المستخدم</th>
                            <th class="py-2 text-right">العملية</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOperations as $log)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $log->occurred_at?->format('H:i:s') }}</td>
                                <td class="py-2">{{ $log->user?->name ?: 'النظام' }}</td>
                                <td class="py-2">{{ $log->action }} - {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-center text-slate-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-slate-900">الفواتير المغلقة</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="py-2 text-right">الفاتورة</th>
                            <th class="py-2 text-right">الطاولة</th>
                            <th class="py-2 text-right">الموظف</th>
                            <th class="py-2 text-right">وقت الإغلاق</th>
                            <th class="py-2 text-right">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($closedOrders as $order)
                            <tr class="border-t border-slate-100">
                                <td class="py-2">{{ $order->financeInvoice?->invoice_number ?: '—' }}</td>
                                <td class="py-2">{{ $order->table?->name ?: '—' }}</td>
                                <td class="py-2">{{ data_get($order->metadata, 'created_by_name', '—') }}</td>
                                <td class="py-2">{{ $order->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-2">{{ number_format((float) $order->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-center text-slate-500">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
@endsection
