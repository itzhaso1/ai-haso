@extends('layouts.appointments', ['pageTitle' => 'Appointment Requests'])

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <section class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Appointment Requests</h2>
                <a href="{{ route('workspace.appointments.bookings.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح الحجوزات</a>
            </div>

            <form method="GET" action="{{ route('workspace.appointments.requests.index') }}" class="mb-3 grid gap-2 md:grid-cols-5">
                <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">كل الحالات</option>
                    @foreach($requestStatusLabels as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="source" class="rounded-lg border-slate-300 text-sm">
                    <option value="">كل المصادر</option>
                    @foreach($sourceLabels as $source => $label)
                        <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="بحث بالعميل أو الجوال أو البريد" class="rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">فلترة</button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-2 py-2 text-right">Customer</th>
                            <th class="px-2 py-2 text-right">Requested Service</th>
                            <th class="px-2 py-2 text-right">Requested Time</th>
                            <th class="px-2 py-2 text-right">Source</th>
                            <th class="px-2 py-2 text-right">Status</th>
                            <th class="px-2 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($appointmentRequests as $appointmentRequest)
                            <tr>
                                <td class="px-2 py-2">
                                    <p class="font-semibold text-slate-900">{{ $appointmentRequest->customer_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $appointmentRequest->customer_phone ?: $appointmentRequest->customer_email }}</p>
                                </td>
                                <td class="px-2 py-2 text-slate-700">
                                    <p>{{ $appointmentRequest->service?->name ?: 'غير محددة' }}</p>
                                    <p class="text-xs text-slate-500">{{ $appointmentRequest->staff?->name ?: 'بدون موظف' }}</p>
                                </td>
                                <td class="px-2 py-2 text-xs text-slate-600">
                                    @if($appointmentRequest->requested_date)
                                        <p>{{ $appointmentRequest->requested_date->locale('ar')->translatedFormat('l، j F') }}</p>
                                        <p>{{ $appointmentRequest->requested_time ?: 'أي وقت' }}</p>
                                    @else
                                        <p>غير محدد</p>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-xs text-slate-600">{{ $sourceLabels[$appointmentRequest->source] ?? $appointmentRequest->source }}</td>
                                <td class="px-2 py-2">
                                    @php($status = (string) $appointmentRequest->status)
                                    @include('workspace.appointments.partials.status-badge', [
                                        'label' => $requestStatusLabels[$status] ?? $status,
                                        'tone' => match ($status) {
                                            'approved' => 'emerald',
                                            'rejected', 'cancelled', 'expired' => 'rose',
                                            'awaiting_customer' => 'blue',
                                            default => 'amber',
                                        }
                                    ])
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('workspace.appointments.requests.show', $appointmentRequest) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">تفاصيل</a>
                                        @if($canManageRequests && ! in_array($appointmentRequest->status, ['approved', 'rejected', 'cancelled', 'expired'], true))
                                            <form method="POST" action="{{ route('workspace.appointments.requests.approve', $appointmentRequest) }}">
                                                @csrf
                                                <button class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white">Approve</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center">
                                    <p class="text-sm font-semibold text-slate-700">لا توجد طلبات مواعيد حالياً</p>
                                    <p class="mt-1 text-xs text-slate-500">عند وصول أول طلب من AI أو واتساب أو الموقع سيظهر هنا.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $appointmentRequests->links() }}</div>
        </section>

        @if($canManageRequests)
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-base font-bold text-slate-900">إضافة طلب جديد</h3>
            <form method="POST" action="{{ route('workspace.appointments.requests.store') }}" class="space-y-3">
                @csrf
                <select name="request_type" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="new">طلب جديد</option>
                    <option value="reschedule">إعادة جدولة</option>
                    <option value="cancellation">إلغاء</option>
                    <option value="information">استفسار</option>
                </select>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">عميل محفوظ (اختياري)</label>
                    <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون ربط</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} • {{ $customer->phone }}</option>
                        @endforeach
                    </select>
                </div>

                <input type="text" name="customer_name" placeholder="اسم العميل" class="w-full rounded-lg border-slate-300 text-sm" required>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="customer_phone" placeholder="الجوال" class="rounded-lg border-slate-300 text-sm">
                    <input type="email" name="customer_email" placeholder="البريد" class="rounded-lg border-slate-300 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <select name="requested_service_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">الخدمة</option>
                        @foreach($allServices as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <select name="requested_staff_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">الموظف</option>
                        @foreach($allStaff as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="requested_date" class="rounded-lg border-slate-300 text-sm">
                    <input type="time" name="requested_time" class="rounded-lg border-slate-300 text-sm">
                </div>
                <input type="time" name="requested_time_end" class="w-full rounded-lg border-slate-300 text-sm" placeholder="إلى">

                <select name="source" class="w-full rounded-lg border-slate-300 text-sm">
                    @foreach($sourceLabels as $source => $label)
                        <option value="{{ $source }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="رسالة العميل / ملاحظات"></textarea>
                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تسجيل الطلب</button>
            </form>
        </section>
        @endif
    </div>
@endsection
