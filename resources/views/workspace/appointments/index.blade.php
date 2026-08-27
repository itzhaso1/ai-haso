@extends('layouts.appointments', ['pageTitle' => 'إدارة المواعيد'])

@section('content')
    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">مواعيد اليوم (Scheduled)</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((int) $todayStats['scheduled']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">مؤكدة</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((int) $todayStats['confirmed']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">مكتملة</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((int) $todayStats['completed']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">ملغاة</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format((int) $todayStats['cancelled']) }}</p>
            </article>
        </div>

        <article
            x-data="{
                mode: 'week',
                date: '{{ now()->toDateString() }}',
                loading: false,
                events: [],
                async load() {
                    this.loading = true;
                    const params = new URLSearchParams({ view: this.mode, date: this.date });
                    const response = await fetch(`{{ route('workspace.appointments.calendar.events') }}?${params.toString()}`);
                    const payload = await response.json();
                    this.events = payload.data || [];
                    this.loading = false;
                }
            }"
            x-init="load()"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold">تقويم المواعيد (Day / Week / Month)</h3>
                <div class="flex items-center gap-2">
                    <input type="date" x-model="date" @change="load()" class="rounded-lg border-slate-300 text-xs">
                    <div class="inline-flex rounded-lg border border-slate-200 p-1 text-xs">
                        <button type="button" @click="mode='day'; load()" :class="mode==='day' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Day</button>
                        <button type="button" @click="mode='week'; load()" :class="mode==='week' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Week</button>
                        <button type="button" @click="mode='month'; load()" :class="mode==='month' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Month</button>
                    </div>
                </div>
            </div>
            <div class="mt-3 space-y-2">
                <p x-show="loading" class="text-xs text-slate-500">جاري تحميل بيانات التقويم...</p>
                <template x-for="event in events" :key="event.id">
                    <div class="rounded-lg border border-slate-200 p-3 text-xs">
                        <p class="font-semibold" x-text="event.title"></p>
                        <p class="text-slate-500" x-text="`${event.start} → ${event.end}`"></p>
                        <p class="text-slate-600" x-text="`الموظف: ${event.staff || 'غير محدد'} | حالة الموعد: ${event.appointment_status} | حالة الدفع: ${event.payment_status}`"></p>
                    </div>
                </template>
                <p x-show="!loading && events.length === 0" class="text-xs text-slate-500">لا توجد مواعيد ضمن الفترة المختارة.</p>
            </div>
        </article>

        <div class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">إعدادات نشاط المواعيد</h3>
                <form method="POST" action="{{ route('workspace.appointments.settings.update') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">نوع النشاط</label>
                        <select name="business_type" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach(['pharmacy' => 'صيدلية', 'clinic' => 'عيادة', 'hospital' => 'مستشفى', 'salon' => 'مركز تجميل', 'general' => 'عام', 'other' => 'أخرى'] as $value => $label)
                                <option value="{{ $value }}" @selected(($setting?->business_type ?? 'general') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">المسمى الظاهر</label>
                        <input type="text" name="business_label" value="{{ old('business_label', $setting?->business_label) }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">بداية اليوم</label>
                            <input type="time" name="start_hour" value="{{ old('start_hour', $setting?->start_hour ? substr((string) $setting->start_hour, 0, 5) : '08:00') }}" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">نهاية اليوم</label>
                            <input type="time" name="end_hour" value="{{ old('end_hour', $setting?->end_hour ? substr((string) $setting->end_hour, 0, 5) : '22:00') }}" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">مدة الفتحة بالدقائق</label>
                            <input type="number" min="5" max="240" name="slot_interval_minutes" value="{{ old('slot_interval_minutes', $setting?->slot_interval_minutes ?? 30) }}" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Timezone</label>
                            <input type="text" name="timezone" value="{{ old('timezone', $setting?->timezone ?? 'Asia/Riyadh') }}" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="allow_walk_in" value="1" @checked(old('allow_walk_in', $setting?->allow_walk_in ?? true)) class="rounded border-slate-300">
                        السماح بالمراجعين بدون موعد مسبق (Walk-in)
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">وضع أتمتة AI</label>
                        <select name="automation_mode" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach(['AUTO' => 'AUTO (تأكيد تلقائي وفق القواعد)', 'APPROVAL' => 'APPROVAL (بحاجة موافقة)', 'MANUAL' => 'MANUAL (تجميع بيانات فقط)'] as $mode => $label)
                                <option value="{{ $mode }}" @selected(($setting?->automation_mode ?? 'APPROVAL') === $mode)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="auto_confirm_after_payment" value="1" @checked(old('auto_confirm_after_payment', $setting?->auto_confirm_after_payment ?? true)) class="rounded border-slate-300">
                        تأكيد الموعد تلقائيًا بعد نجاح الدفع
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">تذكيرات قبل الموعد (بالدقائق، مفصولة بفاصلة)</label>
                        <input type="text" name="reminder_offsets" value="{{ old('reminder_offsets', implode(',', $setting?->reminder_offsets ?? [1440,120])) }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="1440,120">
                    </div>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">حفظ الإعدادات</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">إضافة خدمة موعد</h3>
                <form method="POST" action="{{ route('workspace.appointments.services.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="name" placeholder="مثال: كشف طبي / صرف وصفة" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <textarea name="description" rows="2" placeholder="وصف مختصر" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="number" name="duration_minutes" min="5" max="600" value="30" class="rounded-lg border-slate-300 text-sm" required>
                        <input type="number" name="price" step="0.01" min="0" value="0" class="rounded-lg border-slate-300 text-sm" required>
                        <input type="text" name="color" placeholder="#0f172a" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الطاقم المسموح له بهذه الخدمة</label>
                        <select name="staff_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($allStaff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        خدمة نشطة
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="requires_confirmation" value="1" class="rounded border-slate-300">
                        تتطلب تأكيد قبل التنفيذ
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="approval_required" value="1" class="rounded border-slate-300">
                        تتطلب موافقة بشرية على الطلب
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="requires_payment" value="1" class="rounded border-slate-300">
                        تتطلب دفعًا قبل أو أثناء الموعد
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="payment_mode" class="rounded-lg border-slate-300 text-sm">
                            <option value="postpaid">دفع لاحق</option>
                            <option value="full">دفع كامل</option>
                            <option value="deposit">عربون</option>
                        </select>
                        <input type="number" name="deposit_amount" step="0.01" min="0" placeholder="قيمة العربون" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إضافة الخدمة</button>
                </form>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">إضافة عضو طاقم</h3>
                <form method="POST" action="{{ route('workspace.appointments.staff.store') }}" class="space-y-3">
                    @csrf
                    <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون ربط بحساب مستخدم</option>
                        @foreach($workspaceUsers as $workspaceUser)
                            <option value="{{ $workspaceUser->user_id }}">{{ $workspaceUser->user?->name }} ({{ $workspaceUser->membership_role }})</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" placeholder="اسم الموظف/الطبيب/الصيدلي" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="role" placeholder="الدور" class="rounded-lg border-slate-300 text-sm">
                        <input type="text" name="phone" placeholder="الجوال" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <input type="text" name="color" placeholder="#1f2937" class="w-full rounded-lg border-slate-300 text-sm">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الخدمات التي يقدمها</label>
                        <select name="service_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        عضو نشط
                    </label>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إضافة</button>
                </form>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">الخدمات الحالية</h3>
                <div class="space-y-2">
                    @forelse($services as $service)
                        <details class="rounded-lg border border-slate-200 p-2">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">
                                {{ $service->name }} — {{ $service->duration_minutes }} دقيقة — {{ number_format((float) $service->price, 2) }}
                            </summary>
                            <form method="POST" action="{{ route('workspace.appointments.services.update', $service) }}" class="mt-2 space-y-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $service->name }}" class="w-full rounded-lg border-slate-300 text-sm">
                                <textarea name="description" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ $service->description }}</textarea>
                                <div class="grid grid-cols-3 gap-2">
                                    <input type="number" min="5" max="600" name="duration_minutes" value="{{ $service->duration_minutes }}" class="rounded-lg border-slate-300 text-sm">
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $service->price }}" class="rounded-lg border-slate-300 text-sm">
                                    <input type="text" name="color" value="{{ $service->color }}" class="rounded-lg border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">الطاقم المسموح له بهذه الخدمة</label>
                                    <select name="staff_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                        @foreach($allStaff as $person)
                                            <option value="{{ $person->id }}" @selected($service->staffMembers->contains('id', $person->id))>{{ $person->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-center gap-4 text-xs">
                                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($service->is_active) class="rounded border-slate-300">نشط</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_confirmation" value="1" @checked($service->requires_confirmation) class="rounded border-slate-300">يتطلب تأكيد</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="approval_required" value="1" @checked($service->approval_required) class="rounded border-slate-300">يتطلب موافقة</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_payment" value="1" @checked($service->requires_payment) class="rounded border-slate-300">يتطلب دفع</label>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="payment_mode" class="rounded-lg border-slate-300 text-sm">
                                        @foreach(['postpaid' => 'دفع لاحق', 'full' => 'دفع كامل', 'deposit' => 'عربون'] as $mode => $label)
                                            <option value="{{ $mode }}" @selected($service->payment_mode === $mode)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0" name="deposit_amount" value="{{ $service->deposit_amount }}" class="rounded-lg border-slate-300 text-sm" placeholder="عربون">
                                </div>
                                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث الخدمة</button>
                            </form>
                        </details>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد خدمات بعد.</p>
                    @endforelse
                </div>
                <div class="mt-3">{{ $services->withQueryString()->links() }}</div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">الطاقم الحالي</h3>
                <div class="space-y-2">
                    @forelse($staff as $person)
                        <details class="rounded-lg border border-slate-200 p-2">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">
                                {{ $person->name }} — {{ $person->role ?: 'بدون دور' }}
                            </summary>
                            <form method="POST" action="{{ route('workspace.appointments.staff.update', $person) }}" class="mt-2 space-y-2">
                                @csrf
                                @method('PUT')
                                <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">بدون ربط</option>
                                    @foreach($workspaceUsers as $workspaceUser)
                                        <option value="{{ $workspaceUser->user_id }}" @selected((int) $person->user_id === (int) $workspaceUser->user_id)>{{ $workspaceUser->user?->name }}</option>
                                    @endforeach
                                </select>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" name="name" value="{{ $person->name }}" class="rounded-lg border-slate-300 text-sm">
                                    <input type="text" name="role" value="{{ $person->role }}" class="rounded-lg border-slate-300 text-sm">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" name="phone" value="{{ $person->phone }}" class="rounded-lg border-slate-300 text-sm">
                                    <input type="text" name="color" value="{{ $person->color }}" class="rounded-lg border-slate-300 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">الخدمات التي يقدمها</label>
                                    <select name="service_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                        @foreach($allServices as $service)
                                            <option value="{{ $service->id }}" @selected($person->services->contains('id', $service->id))>{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" @checked($person->is_active) class="rounded border-slate-300">نشط
                                </label>
                                <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث الطاقم</button>
                            </form>
                        </details>
                    @empty
                        <p class="text-sm text-slate-500">لا يوجد طاقم بعد.</p>
                    @endforelse
                </div>
                <div class="mt-3">{{ $staff->withQueryString()->links() }}</div>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <article class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold">الحجوزات</h3>
                </div>

                <form method="GET" action="{{ route('workspace.appointments.dashboard') }}" class="mb-3 grid gap-2 md:grid-cols-6">
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الحالات</option>
                        @foreach(['scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="payment_status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل حالات الدفع</option>
                        @foreach(['unpaid','pending','paid','failed','refunded','partially_paid'] as $pStatus)
                            <option value="{{ $pStatus }}" @selected(($filters['payment_status'] ?? '') === $pStatus)>{{ $pStatus }}</option>
                        @endforeach
                    </select>
                    <select name="staff_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الطاقم</option>
                        @foreach($allStaff as $person)
                            <option value="{{ $person->id }}" @selected((int) ($filters['staff_id'] ?? 0) === $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="بحث..." class="rounded-lg border-slate-300 text-sm">
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">فلترة</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-2 text-right">رقم الموعد</th>
                                <th class="px-2 py-2 text-right">الخدمة</th>
                                <th class="px-2 py-2 text-right">العميل</th>
                                <th class="px-2 py-2 text-right">الطاقم</th>
                                <th class="px-2 py-2 text-right">الوقت</th>
                                <th class="px-2 py-2 text-right">حالة الموعد</th>
                                <th class="px-2 py-2 text-right">حالة الدفع</th>
                                <th class="px-2 py-2 text-right">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bookings as $booking)
                                <tr>
                                    <td class="px-2 py-2 font-semibold">{{ $booking->booking_number }}</td>
                                    <td class="px-2 py-2">{{ $booking->service?->name }}</td>
                                    <td class="px-2 py-2">
                                        @if($booking->customer_id)
                                            <a href="{{ route('workspace.appointments.customers.profile', $booking->customer_id) }}" class="font-semibold text-slate-800 hover:underline">{{ $booking->customer_name }}</a>
                                        @else
                                            <p>{{ $booking->customer_name }}</p>
                                        @endif
                                        <p class="text-xs text-slate-500">{{ $booking->customer_phone }}</p>
                                    </td>
                                    <td class="px-2 py-2">{{ $booking->staff?->name ?: 'غير محدد' }}</td>
                                    <td class="px-2 py-2 text-xs">
                                        {{ $booking->starts_at?->format('Y-m-d H:i') }}<br>
                                        {{ $booking->ends_at?->format('H:i') }}
                                    </td>
                                    <td class="px-2 py-2">
                                        <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="status" class="rounded-lg border-slate-300 text-xs">
                                                @foreach(['scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show'] as $status)
                                                    <option value="{{ $status }}" @selected($booking->appointment_status === $status)>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                            <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">حفظ</button>
                                        </form>
                                    </td>
                                    <td class="px-2 py-2 text-xs font-semibold">{{ $booking->payment_status }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($booking->payment_link)
                                                <a href="{{ $booking->payment_link }}" target="_blank" rel="noopener" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">رابط الدفع</a>
                                            @else
                                                <form method="POST" action="{{ route('workspace.appointments.bookings.payment-link', $booking) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">إنشاء رابط دفع</button>
                                                </form>
                                            @endif
                                            @if($booking->public_token)
                                                <a href="{{ route('appointments.portal.show', $booking->public_token) }}" target="_blank" class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white">رابط العميل</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-2 py-8 text-center text-slate-500">لا توجد مواعيد مطابقة.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $bookings->links() }}</div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">إنشاء موعد جديد</h3>
                <form method="POST" action="{{ route('workspace.appointments.bookings.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الخدمة</label>
                        <select name="service_id" required class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">اختر الخدمة</option>
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->duration_minutes }} دقيقة)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">الطاقم</label>
                        <select name="staff_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">غير محدد</option>
                            @foreach($allStaff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">عميل محفوظ (اختياري)</label>
                        <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">بدون ربط</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="customer_name" placeholder="اسم العميل" class="rounded-lg border-slate-300 text-sm">
                        <input type="text" name="customer_phone" placeholder="جوال العميل" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="email" name="customer_email" placeholder="بريد العميل (اختياري)" class="rounded-lg border-slate-300 text-sm">
                        <input type="number" name="customer_age" min="1" max="120" placeholder="عمر العميل (اختياري)" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">بداية الموعد</label>
                            <input type="datetime-local" name="starts_at" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">نهاية الموعد</label>
                            <input type="datetime-local" name="ends_at" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="status" class="rounded-lg border-slate-300 text-sm">
                            <option value="scheduled">scheduled</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                        <select name="source" class="rounded-lg border-slate-300 text-sm">
                            <option value="dashboard">dashboard</option>
                            <option value="ai_chat">ai_chat</option>
                            <option value="phone">phone</option>
                            <option value="walk_in">walk_in</option>
                            <option value="website">website</option>
                            <option value="whatsapp">whatsapp</option>
                            <option value="email">email</option>
                            <option value="api">api</option>
                        </select>
                    </div>
                    <textarea name="notes" rows="2" placeholder="ملاحظات" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء الموعد</button>
                </form>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold">طلبات المواعيد (Appointment Requests)</h3>
                </div>
                <form method="GET" action="{{ route('workspace.appointments.dashboard') }}" class="mb-3 grid gap-2 md:grid-cols-5">
                    <input type="date" name="request_date" value="{{ $requestFilters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="request_status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الحالات</option>
                        @foreach(['new','reviewing','awaiting_customer','approved','rejected','expired','cancelled'] as $status)
                            <option value="{{ $status }}" @selected($requestFilters['status'] === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <select name="request_source" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل المصادر</option>
                        @foreach(['ai_chat','whatsapp','website','phone','dashboard','walk_in','email','api'] as $source)
                            <option value="{{ $source }}" @selected($requestFilters['source'] === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="request_search" value="{{ $requestFilters['search'] }}" placeholder="بحث..." class="rounded-lg border-slate-300 text-sm">
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">فلترة الطلبات</button>
                </form>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-2 text-right">العميل</th>
                                <th class="px-2 py-2 text-right">الخدمة المطلوبة</th>
                                <th class="px-2 py-2 text-right">التاريخ المطلوب</th>
                                <th class="px-2 py-2 text-right">المصدر</th>
                                <th class="px-2 py-2 text-right">الحالة</th>
                                <th class="px-2 py-2 text-right">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($appointmentRequests as $appointmentRequest)
                                <tr>
                                    <td class="px-2 py-2">
                                        <p class="font-semibold">{{ $appointmentRequest->customer_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $appointmentRequest->customer_phone ?: $appointmentRequest->customer_email }}</p>
                                    </td>
                                    <td class="px-2 py-2">{{ $appointmentRequest->service?->name ?: '—' }}</td>
                                    <td class="px-2 py-2 text-xs">
                                        {{ $appointmentRequest->requested_date?->format('Y-m-d') ?: 'غير محدد' }}
                                        @if($appointmentRequest->requested_time)
                                            <br>{{ $appointmentRequest->requested_time }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-xs">{{ $appointmentRequest->source }}</td>
                                    <td class="px-2 py-2 text-xs font-semibold">{{ $appointmentRequest->status }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @if(!in_array($appointmentRequest->status, ['approved','rejected','cancelled','expired']))
                                                <form method="POST" action="{{ route('workspace.appointments.requests.approve', $appointmentRequest) }}">
                                                    @csrf
                                                    <input type="hidden" name="service_id" value="{{ $appointmentRequest->requested_service_id }}">
                                                    <button class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white">اعتماد</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.requests.awaiting-customer', $appointmentRequest) }}">
                                                    @csrf
                                                    <input type="hidden" name="message" value="نحتاج معلومات إضافية أو اختيار موعد من المقترحات.">
                                                    <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700">معلومات إضافية</button>
                                                </form>
                                                <form method="POST" action="{{ route('workspace.appointments.requests.reject', $appointmentRequest) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700">رفض</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-2 py-8 text-center text-slate-500">لا توجد طلبات مواعيد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $appointmentRequests->links() }}</div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">إضافة طلب موعد جديد</h3>
                <form method="POST" action="{{ route('workspace.appointments.requests.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="customer_name" class="w-full rounded-lg border-slate-300 text-sm" placeholder="اسم العميل" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="customer_phone" class="rounded-lg border-slate-300 text-sm" placeholder="الجوال">
                        <input type="email" name="customer_email" class="rounded-lg border-slate-300 text-sm" placeholder="البريد الإلكتروني">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="requested_service_id" class="rounded-lg border-slate-300 text-sm">
                            <option value="">الخدمة المطلوبة</option>
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <select name="requested_staff_id" class="rounded-lg border-slate-300 text-sm">
                            <option value="">الموظف المطلوب</option>
                            @foreach($allStaff as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="requested_date" class="rounded-lg border-slate-300 text-sm">
                        <input type="time" name="requested_time" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <select name="source" class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach(['dashboard','phone','walk_in','website','whatsapp','ai_chat','email','api'] as $source)
                            <option value="{{ $source }}">{{ $source }}</option>
                        @endforeach
                    </select>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات العميل"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تسجيل الطلب</button>
                </form>
            </article>
        </div>
    </div>
@endsection
