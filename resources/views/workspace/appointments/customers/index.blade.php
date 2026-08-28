@extends('layouts.appointments', ['pageTitle' => 'Customers'])

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-bold text-slate-900">Customers</h2>
            <form method="GET" action="{{ route('workspace.appointments.customers.index') }}" class="flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="بحث بالاسم أو الجوال أو البريد" class="rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">بحث</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-2 py-2 text-right">العميل</th>
                        <th class="px-2 py-2 text-right">الجوال</th>
                        <th class="px-2 py-2 text-right">البريد</th>
                        <th class="px-2 py-2 text-right">الحجوزات</th>
                        <th class="px-2 py-2 text-right">الطلبات</th>
                        <th class="px-2 py-2 text-right">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-2 py-2 font-semibold text-slate-900">{{ $customer->name }}</td>
                            <td class="px-2 py-2 text-slate-700">{{ $customer->phone ?: '—' }}</td>
                            <td class="px-2 py-2 text-slate-700">{{ $customer->email ?: '—' }}</td>
                            <td class="px-2 py-2 text-slate-700">{{ (int) ($bookingCounts[$customer->id] ?? 0) }}</td>
                            <td class="px-2 py-2 text-slate-700">{{ (int) ($requestCounts[$customer->id] ?? 0) }}</td>
                            <td class="px-2 py-2">
                                <a href="{{ route('workspace.appointments.customers.profile', $customer) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح الملف</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center">
                                <p class="text-sm font-semibold text-slate-700">لا يوجد عملاء</p>
                                <p class="mt-1 text-xs text-slate-500">عند إضافة أول عميل سيظهر هنا مع تاريخه في المواعيد.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $customers->links() }}</div>
    </div>
@endsection
