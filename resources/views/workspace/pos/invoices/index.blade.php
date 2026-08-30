@extends('layouts.pos', ['pageTitle' => 'فواتير الكاشير'])

@section('content')
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">الفواتير المغلقة</h2>
                <p class="text-xs text-slate-500">الطلبات المكتملة المرتبطة بالفوترة الحالية.</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" class="rounded-lg border-slate-300 text-sm" />
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">عرض</button>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-right">الفاتورة</th>
                        <th class="px-3 py-2 text-right">الطاولة</th>
                        <th class="px-3 py-2 text-right">الموظف</th>
                        <th class="px-3 py-2 text-right">وقت الإغلاق</th>
                        <th class="px-3 py-2 text-right">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-2">
                                @if($order->financeInvoice)
                                    <a href="{{ route('workspace.finance.invoices.show', $order->financeInvoice) }}" class="font-semibold text-slate-900">
                                        {{ $order->financeInvoice->invoice_number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $order->table?->name ?: '—' }}</td>
                            <td class="px-3 py-2">{{ data_get($order->metadata, 'created_by_name', '—') }}</td>
                            <td class="px-3 py-2">{{ $order->updated_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 font-semibold">{{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">لا توجد فواتير لهذا التاريخ.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </section>
@endsection
