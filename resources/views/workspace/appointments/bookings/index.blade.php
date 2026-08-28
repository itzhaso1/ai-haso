@extends('layouts.appointments', ['pageTitle' => 'Bookings'])

@section('content')
    <div class="space-y-4">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Today</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format((int) $bookingStats['today']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Upcoming</p>
                <p class="mt-1 text-xl font-bold text-blue-600">{{ number_format((int) $bookingStats['upcoming']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Completed</p>
                <p class="mt-1 text-xl font-bold text-emerald-600">{{ number_format((int) $bookingStats['completed']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Cancelled</p>
                <p class="mt-1 text-xl font-bold text-rose-600">{{ number_format((int) $bookingStats['cancelled']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">No Show</p>
                <p class="mt-1 text-xl font-bold text-violet-600">{{ number_format((int) $bookingStats['noShow']) }}</p>
            </article>
        </section>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-900">الحجوزات</h2>
                    <a href="{{ route('workspace.appointments.calendar.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">عرض Calendar</a>
                </div>

                <form method="GET" action="{{ route('workspace.appointments.bookings.index') }}" class="mb-3 grid gap-2 md:grid-cols-4 xl:grid-cols-8">
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                    <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" placeholder="من">
                    <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" placeholder="إلى">

                    <select name="staff_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الموظفين</option>
                        @foreach($allStaff as $staff)
                            <option value="{{ $staff->id }}" @selected((int) ($filters['staff_id'] ?? 0) === (int) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>

                    <select name="service_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الخدمات</option>
                        @foreach($allServices as $service)
                            <option value="{{ $service->id }}" @selected((int) ($filters['service_id'] ?? 0) === (int) $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل حالات الموعد</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="payment_status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل حالات الدفع</option>
                        @foreach($paymentStatusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="source" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل المصادر</option>
                        @foreach($sourceLabels as $source => $label)
                            <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="بحث بالاسم / الجوال / رقم الحجز" class="rounded-lg border-slate-300 text-sm">

                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white md:col-span-2 xl:col-span-8">تطبيق الفلاتر</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-2 text-right">العميل</th>
                                <th class="px-2 py-2 text-right">الهاتف</th>
                                <th class="px-2 py-2 text-right">الخدمة</th>
                                <th class="px-2 py-2 text-right">الموظف</th>
                                <th class="px-2 py-2 text-right">التاريخ والوقت</th>
                                <th class="px-2 py-2 text-right">المدة</th>
                                <th class="px-2 py-2 text-right">حالة الموعد</th>
                                <th class="px-2 py-2 text-right">حالة الدفع</th>
                                <th class="px-2 py-2 text-right">المصدر</th>
                                <th class="px-2 py-2 text-right">آخر تحديث</th>
                                <th class="px-2 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bookings as $booking)
                                <tr>
                                    <td class="px-2 py-2">
                                        <p class="font-semibold text-slate-900">{{ $booking->customer_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $booking->booking_number }}</p>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ $booking->customer_phone ?: '—' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $booking->service?->name ?: '—' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $booking->staff?->name ?: 'غير محدد' }}</td>
                                    <td class="px-2 py-2 text-xs text-slate-600">
                                        <p>{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F') }}</p>
                                        <p>{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }} - {{ $booking->ends_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}</p>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ max(1, (int) $booking->starts_at?->diffInMinutes($booking->ends_at)) }} دقيقة</td>
                                    <td class="px-2 py-2">
                                        @php($status = (string) $booking->appointment_status)
                                        @include('workspace.appointments.partials.status-badge', [
                                            'label' => $statusLabels[$status] ?? $status,
                                            'tone' => match ($status) {
                                                'completed', 'confirmed' => 'emerald',
                                                'cancelled', 'no_show' => 'rose',
                                                'checked_in', 'in_progress' => 'blue',
                                                default => 'amber',
                                            }
                                        ])
                                    </td>
                                    <td class="px-2 py-2">
                                        @php($paymentStatus = (string) $booking->payment_status)
                                        @include('workspace.appointments.partials.status-badge', [
                                            'label' => $paymentStatusLabels[$paymentStatus] ?? $paymentStatus,
                                            'tone' => match ($paymentStatus) {
                                                'paid' => 'emerald',
                                                'failed', 'refunded' => 'rose',
                                                'pending', 'partially_paid' => 'amber',
                                                default => 'slate',
                                            }
                                        ])
                                    </td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ $sourceLabels[$booking->source_channel] ?? $booking->source_channel }}</td>
                                    <td class="px-2 py-2 text-xs text-slate-500">{{ $booking->updated_at?->timezone($timezone)->locale('ar')->translatedFormat('j F - g:i A') }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">تفاصيل</a>
                                            @if($booking->customer_id)
                                                <a href="{{ route('workspace.appointments.customers.profile', $booking->customer_id) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">العميل</a>
                                            @endif
                                            @if($canManageBookings)
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Confirm</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="checked_in">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Check-in</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">In Progress</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="completed">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Complete</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="no_show">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">No-show</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button class="rounded-md border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel</button>
                                                </form>
                                                <a href="{{ route('workspace.appointments.bookings.show', $booking) }}#reschedule-form" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Reschedule</a>
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.send-reminder', $booking) }}">
                                                    @csrf
                                                    <input type="hidden" name="channel" value="in_app">
                                                    <input type="hidden" name="minutes_before" value="5">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Send Reminder</button>
                                                </form>
                                            @endif
                                            @if($canManageBilling && $booking->payment_link)
                                                <a href="{{ $booking->payment_link }}" target="_blank" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">الدفع</a>
                                            @elseif($canManageBilling)
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.payment-link', $booking) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Send Payment Link</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-10 text-center">
                                        <p class="text-sm font-semibold text-slate-700">لا توجد حجوزات مطابقة</p>
                                        <p class="mt-1 text-xs text-slate-500">جرّب تغيير الفلاتر أو أنشئ حجزًا جديدًا.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $bookings->links() }}</div>
            </section>

            @if($canManageBookings)
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold text-slate-900">إنشاء حجز جديد</h2>
                <form method="POST" action="{{ route('workspace.appointments.bookings.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الخدمة</label>
                        <select name="service_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                            <option value="">اختر الخدمة</option>
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->duration_minutes }} دقيقة)</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الموظف</label>
                        <select name="staff_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">غير محدد</option>
                            @foreach($allStaff as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الموارد (اختياري)</label>
                        <select name="resource_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($allResources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">عميل محفوظ (اختياري)</label>
                        <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">بدون ربط</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} • {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="customer_name" placeholder="اسم العميل" class="rounded-lg border-slate-300 text-sm" required>
                        <input type="text" name="customer_phone" placeholder="رقم الجوال" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="email" name="customer_email" placeholder="البريد الإلكتروني" class="rounded-lg border-slate-300 text-sm">
                        <input type="number" name="customer_age" min="1" max="120" placeholder="العمر" class="rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">وقت البداية ({{ $timezone }})</label>
                        <input type="datetime-local" name="starts_at" class="w-full rounded-lg border-slate-300 text-sm" required>
                    </div>
                    <p class="text-xs text-slate-500">سيتم حساب وقت النهاية تلقائيًا حسب مدة الخدمة.</p>

                    <div class="grid grid-cols-2 gap-2">
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            <option value="scheduled">مجدول</option>
                            <option value="confirmed">مؤكد</option>
                        </select>
                        <select name="source" class="rounded-lg border-slate-300 text-sm">
                            @foreach($sourceLabels as $source => $label)
                                <option value="{{ $source }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات داخلية"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء الحجز</button>
                </form>
            </section>
            @endif
        </div>
    </div>
@endsection
