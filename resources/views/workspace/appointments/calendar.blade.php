@extends('layouts.appointments', ['pageTitle' => 'Calendar'])

@section('content')
    <div
        x-data="appointmentsCalendar({
            endpoint: '{{ route('workspace.appointments.calendar.events') }}',
            initialDate: '{{ $defaultDate }}'
        })"
        x-init="init()"
        class="space-y-4"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Calendar</h2>
                    <p class="text-xs text-slate-500">جميع المواعيد تعرض بتوقيت النشاط: {{ $timezone }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="date" x-model="date" @change="load()" class="rounded-lg border-slate-300 text-sm">
                    <div class="inline-flex rounded-lg border border-slate-200 p-1 text-xs">
                        <button type="button" @click="mode='day'; load()" :class="mode==='day' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Day</button>
                        <button type="button" @click="mode='week'; load()" :class="mode==='week' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Week</button>
                        <button type="button" @click="mode='month'; load()" :class="mode==='month' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Month</button>
                    </div>
                </div>
            </div>
        </div>

        <template x-if="loading">
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">جاري تحميل المواعيد...</div>
        </template>

        <template x-if="!loading && mode === 'day'">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">مواعيد اليوم</h3>
                <div class="space-y-2">
                    <template x-for="event in dayEvents()" :key="event.id">
                        <a :href="bookingUrl(event.id)" class="block rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900" x-text="event.customer"></p>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700" x-text="event.time_label"></span>
                            </div>
                            <p class="text-xs text-slate-600" x-text="`${event.service || 'خدمة'} • ${event.staff || 'بدون موظف'}`"></p>
                            <p class="text-xs text-slate-500" x-text="statusLabel(event.appointment_status) + ' • ' + paymentLabel(event.payment_status)"></p>
                        </a>
                    </template>
                    <p x-show="dayEvents().length === 0" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">لا توجد حجوزات في هذا اليوم.</p>
                </div>
            </div>
        </template>

        <template x-if="!loading && mode === 'week'">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                <template x-for="day in weekDays()" :key="day.key">
                    <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-800" x-text="day.label"></h3>
                        <div class="mt-2 space-y-2">
                            <template x-for="event in eventsByDay(day.key)" :key="event.id">
                                <a :href="bookingUrl(event.id)" class="block rounded-lg border border-slate-200 p-2 transition hover:border-slate-300 hover:bg-slate-50">
                                    <p class="text-xs font-semibold text-slate-900" x-text="event.customer"></p>
                                    <p class="text-[11px] text-slate-500" x-text="event.time_label"></p>
                                    <p class="text-[11px] text-slate-600" x-text="event.service || 'خدمة'"></p>
                                </a>
                            </template>
                            <p x-show="eventsByDay(day.key).length === 0" class="text-[11px] text-slate-400">لا مواعيد</p>
                        </div>
                    </article>
                </template>
            </div>
        </template>

        <template x-if="!loading && mode === 'month'">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-2 grid grid-cols-7 gap-2 text-center text-xs font-bold text-slate-500">
                    <span>الأحد</span><span>الاثنين</span><span>الثلاثاء</span><span>الأربعاء</span><span>الخميس</span><span>الجمعة</span><span>السبت</span>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                    <template x-for="day in monthDays()" :key="day.key">
                        <article class="min-h-32 rounded-xl border border-slate-200 p-2">
                            <p class="text-xs font-bold text-slate-700" x-text="day.label"></p>
                            <div class="mt-2 space-y-1">
                                <template x-for="event in eventsByDay(day.key).slice(0, 3)" :key="event.id">
                                    <a :href="bookingUrl(event.id)" class="block truncate rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-700" :title="event.customer + ' ' + event.time_label" x-text="event.customer + ' • ' + event.time_label"></a>
                                </template>
                                <p x-show="eventsByDay(day.key).length > 3" class="text-[11px] text-slate-500" x-text="'+' + (eventsByDay(day.key).length - 3) + ' مواعيد'"></p>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <script>
        function appointmentsCalendar(config) {
            return {
                endpoint: config.endpoint,
                date: config.initialDate,
                mode: 'week',
                loading: false,
                events: [],
                init() {
                    this.load();
                },
                async load() {
                    this.loading = true;
                    const params = new URLSearchParams({ view: this.mode, date: this.date });
                    const response = await fetch(`${this.endpoint}?${params.toString()}`);
                    const payload = await response.json();
                    this.events = Array.isArray(payload.data) ? payload.data : [];
                    this.loading = false;
                },
                bookingUrl(id) {
                    return `{{ url('/workspace/appointments/bookings') }}/${id}`;
                },
                dayEvents() {
                    return this.events
                        .filter((event) => event.date_key === this.date)
                        .sort((a, b) => (a.start_ts || 0) - (b.start_ts || 0));
                },
                eventsByDay(dateKey) {
                    return this.events
                        .filter((event) => event.date_key === dateKey)
                        .sort((a, b) => (a.start_ts || 0) - (b.start_ts || 0));
                },
                weekDays() {
                    const selected = new Date(this.date + 'T12:00:00');
                    const day = selected.getDay();
                    const start = new Date(selected);
                    start.setDate(selected.getDate() - day);
                    const labels = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                    return labels.map((label, index) => {
                        const d = new Date(start);
                        d.setDate(start.getDate() + index);
                        return {
                            key: this.toDateKey(d),
                            label: `${label} ${d.getDate()}`,
                        };
                    });
                },
                monthDays() {
                    const selected = new Date(this.date + 'T12:00:00');
                    const year = selected.getFullYear();
                    const month = selected.getMonth();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const days = [];
                    for (let i = 1; i <= daysInMonth; i++) {
                        const day = new Date(year, month, i, 12, 0, 0);
                        days.push({
                            key: this.toDateKey(day),
                            label: i,
                        });
                    }
                    return days;
                },
                toDateKey(dateObj) {
                    const y = dateObj.getFullYear();
                    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const d = String(dateObj.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                },
                statusLabel(status) {
                    return {
                        scheduled: 'مجدول',
                        confirmed: 'مؤكد',
                        checked_in: 'تم تسجيل الحضور',
                        in_progress: 'قيد التنفيذ',
                        completed: 'مكتمل',
                        cancelled: 'ملغي',
                        no_show: 'لم يحضر',
                    }[status] || status;
                },
                paymentLabel(status) {
                    return {
                        unpaid: 'غير مدفوع',
                        pending: 'قيد الانتظار',
                        paid: 'مدفوع',
                        failed: 'فشل الدفع',
                        refunded: 'مسترجع',
                        partially_paid: 'جزئي',
                    }[status] || status;
                }
            };
        }
    </script>
@endsection
