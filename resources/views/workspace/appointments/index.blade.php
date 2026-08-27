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
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        خدمة نشطة
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="requires_confirmation" value="1" class="rounded border-slate-300">
                        تتطلب تأكيد قبل التنفيذ
                    </label>
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
                                <div class="flex items-center gap-4 text-xs">
                                    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($service->is_active) class="rounded border-slate-300">نشط</label>
                                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_confirmation" value="1" @checked($service->requires_confirmation) class="rounded border-slate-300">يتطلب تأكيد</label>
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

                <form method="GET" action="{{ route('workspace.appointments.dashboard') }}" class="mb-3 grid gap-2 md:grid-cols-5">
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الحالات</option>
                        @foreach(['scheduled','confirmed','completed','cancelled','no_show'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
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
                                <th class="px-2 py-2 text-right">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bookings as $booking)
                                <tr>
                                    <td class="px-2 py-2 font-semibold">{{ $booking->booking_number }}</td>
                                    <td class="px-2 py-2">{{ $booking->service?->name }}</td>
                                    <td class="px-2 py-2">
                                        <p>{{ $booking->customer_name }}</p>
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
                                                @foreach(['scheduled','confirmed','completed','cancelled','no_show'] as $status)
                                                    <option value="{{ $status }}" @selected($booking->status === $status)>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                            <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">حفظ</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-2 py-8 text-center text-slate-500">لا توجد مواعيد مطابقة.</td></tr>
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
                            <option value="phone">phone</option>
                            <option value="walk_in">walk_in</option>
                            <option value="website">website</option>
                            <option value="whatsapp">whatsapp</option>
                        </select>
                    </div>
                    <textarea name="notes" rows="2" placeholder="ملاحظات" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء الموعد</button>
                </form>
            </article>
        </div>
    </div>
@endsection
