@extends('layouts.appointments', ['pageTitle' => 'Overview'])

@section('content')
    <div class="space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">مواعيد اليوم</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) $todayCards['today']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">طلبات بانتظار المعالجة</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ number_format((int) $todayCards['pending_requests']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">المواعيد القادمة</p>
                <p class="mt-2 text-2xl font-bold text-blue-600">{{ number_format((int) $todayCards['upcoming']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">مكتملة اليوم</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format((int) $todayCards['completed']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">ملغاة اليوم</p>
                <p class="mt-2 text-2xl font-bold text-rose-600">{{ number_format((int) $todayCards['cancelled']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">لم يحضر</p>
                <p class="mt-2 text-2xl font-bold text-violet-600">{{ number_format((int) $todayCards['no_show']) }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">حجوزات اليوم</h2>
                    <a href="{{ route('workspace.appointments.bookings.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">فتح صفحة الحجوزات</a>
                </div>
                <div class="space-y-2">
                    @forelse($latestBookings as $booking)
                        <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="block rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $booking->customer_name }}</p>
                                @php($statusKey = (string) $booking->appointment_status)
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $statusLabels[$statusKey] ?? $statusKey,
                                    'tone' => match ($statusKey) {
                                        'confirmed', 'completed' => 'emerald',
                                        'cancelled', 'no_show' => 'rose',
                                        'checked_in', 'in_progress' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ $booking->service?->name ?: 'خدمة غير محددة' }} • {{ $booking->staff?->name ?: 'بدون موظف' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد حجوزات اليوم</p>
                            <p class="mt-1 text-xs text-slate-500">عندما يصل أول حجز سيظهر هنا مباشرة.</p>
                            <a href="{{ route('workspace.appointments.bookings.index') }}" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء أو مراجعة الحجوزات</a>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">طلبات المواعيد النشطة</h2>
                    <a href="{{ route('workspace.appointments.requests.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">فتح الطلبات</a>
                </div>
                <div class="space-y-2">
                    @forelse($latestRequests as $request)
                        <a href="{{ route('workspace.appointments.requests.show', $request) }}" class="block rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $request->customer_name }}</p>
                                @php($requestStatus = (string) $request->status)
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $requestStatusLabels[$requestStatus] ?? $requestStatus,
                                    'tone' => match ($requestStatus) {
                                        'approved' => 'emerald',
                                        'rejected', 'cancelled', 'expired' => 'rose',
                                        'awaiting_customer' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ $request->service?->name ?: 'خدمة غير محددة' }} • {{ $request->staff?->name ?: 'بدون موظف' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $request->created_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد طلبات حالية</p>
                            <p class="mt-1 text-xs text-slate-500">أي طلب جديد من AI أو واتساب أو الموقع سيظهر هنا.</p>
                            <a href="{{ route('workspace.appointments.requests.index') }}" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إدارة الطلبات</a>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
