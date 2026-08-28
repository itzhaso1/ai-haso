@extends('layouts.appointments', ['pageTitle' => 'Settings'])

@section('content')
    @php
        $hours = is_array($businessHours ?? null) ? $businessHours : [];
        $rules = is_array($bookingRules ?? null) ? $bookingRules : [];
        $cancelRules = is_array($cancellationRules ?? null) ? $cancellationRules : [];
    @endphp

    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-base font-bold text-slate-900">Business & Automation Settings</h2>

            <form method="POST" action="{{ route('workspace.appointments.settings.update') }}" class="space-y-4">
                @csrf
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">نوع النشاط</label>
                        <select name="business_type" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach(['pharmacy' => 'صيدلية', 'clinic' => 'عيادة', 'hospital' => 'مستشفى', 'salon' => 'صالون', 'general' => 'عام', 'other' => 'أخرى'] as $value => $label)
                                <option value="{{ $value }}" @selected(($setting?->business_type ?? 'general') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">اسم النشاط الظاهر</label>
                        <input type="text" name="business_label" value="{{ old('business_label', $setting?->business_label) }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Timezone</label>
                        <input type="text" name="timezone" value="{{ old('timezone', $setting?->timezone ?? 'Asia/Riyadh') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Slot Interval (Minutes)</label>
                        <input type="number" min="5" max="240" name="slot_interval_minutes" value="{{ old('slot_interval_minutes', $setting?->slot_interval_minutes ?? 30) }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">بداية اليوم</label>
                        <input type="time" name="start_hour" value="{{ old('start_hour', $setting?->start_hour ? substr((string) $setting->start_hour, 0, 5) : '08:00') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">نهاية اليوم</label>
                        <input type="time" name="end_hour" value="{{ old('end_hour', $setting?->end_hour ? substr((string) $setting->end_hour, 0, 5) : '22:00') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Automation Mode</label>
                        <select name="automation_mode" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach(['AUTO' => 'AUTO', 'APPROVAL' => 'APPROVAL', 'MANUAL' => 'MANUAL'] as $mode => $label)
                                <option value="{{ $mode }}" @selected(($setting?->automation_mode ?? 'APPROVAL') === $mode)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Reminder Offsets (minutes)</label>
                        <input type="text" name="reminder_offsets" value="{{ old('reminder_offsets', implode(',', $setting?->reminder_offsets ?? [1440, 120])) }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="1440,120">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="allow_walk_in" value="1" @checked(old('allow_walk_in', $setting?->allow_walk_in ?? true)) class="rounded border-slate-300">
                        السماح بالحجز المباشر (Walk-in)
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 p-3 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="auto_confirm_after_payment" value="1" @checked(old('auto_confirm_after_payment', $setting?->auto_confirm_after_payment ?? true)) class="rounded border-slate-300">
                        تأكيد الموعد تلقائيًا بعد الدفع
                    </label>
                </div>

                <div class="rounded-xl border border-slate-200 p-3">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Business Hours</h3>
                    <div class="space-y-3">
                        @foreach($weekDays as $dayKey => $dayLabel)
                            @php
                                $day = $hours[$dayKey] ?? [];
                                $ranges = is_array($day['ranges'] ?? null) ? $day['ranges'] : [];
                                $r1 = $ranges[0] ?? ['start' => '', 'end' => ''];
                                $r2 = $ranges[1] ?? ['start' => '', 'end' => ''];
                            @endphp
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold text-slate-700">{{ $dayLabel }}</p>
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="business_hours[{{ $dayKey }}][closed]" value="1" @checked((bool) ($day['closed'] ?? false)) class="rounded border-slate-300">
                                        يوم مغلق
                                    </label>
                                </div>
                                <div class="grid gap-2 md:grid-cols-4">
                                    <input type="time" name="business_hours[{{ $dayKey }}][ranges][0][start]" value="{{ old("business_hours.$dayKey.ranges.0.start", $r1['start']) }}" class="rounded-lg border-slate-300 text-xs" placeholder="09:00">
                                    <input type="time" name="business_hours[{{ $dayKey }}][ranges][0][end]" value="{{ old("business_hours.$dayKey.ranges.0.end", $r1['end']) }}" class="rounded-lg border-slate-300 text-xs" placeholder="13:00">
                                    <input type="time" name="business_hours[{{ $dayKey }}][ranges][1][start]" value="{{ old("business_hours.$dayKey.ranges.1.start", $r2['start']) }}" class="rounded-lg border-slate-300 text-xs" placeholder="16:00">
                                    <input type="time" name="business_hours[{{ $dayKey }}][ranges][1][end]" value="{{ old("business_hours.$dayKey.ranges.1.end", $r2['end']) }}" class="rounded-lg border-slate-300 text-xs" placeholder="21:00">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-3">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">Booking Rules</h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <input type="number" name="booking_rules[min_booking_notice_minutes]" min="0" value="{{ old('booking_rules.min_booking_notice_minutes', $rules['min_booking_notice_minutes'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Minimum notice (minutes)">
                            <input type="number" name="booking_rules[max_advance_booking_days]" min="1" value="{{ old('booking_rules.max_advance_booking_days', $rules['max_advance_booking_days'] ?? 180) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Maximum advance (days)">
                            <input type="number" name="booking_rules[slot_interval_minutes]" min="5" value="{{ old('booking_rules.slot_interval_minutes', $rules['slot_interval_minutes'] ?? ($setting?->slot_interval_minutes ?? 30)) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Slot interval">
                            <input type="number" name="booking_rules[buffer_minutes]" min="0" value="{{ old('booking_rules.buffer_minutes', $rules['buffer_minutes'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Buffer (minutes)">
                            <input type="number" name="booking_rules[max_bookings_per_day]" min="0" value="{{ old('booking_rules.max_bookings_per_day', $rules['max_bookings_per_day'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Max bookings per day">
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-3">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">Cancellation & Reschedule Rules</h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <input type="number" name="cancellation_rules[minimum_notice_hours]" min="0" value="{{ old('cancellation_rules.minimum_notice_hours', $cancelRules['minimum_notice_hours'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Minimum notice (hours)">
                            <input type="number" name="cancellation_rules[cancellation_window_hours]" min="0" value="{{ old('cancellation_rules.cancellation_window_hours', $cancelRules['cancellation_window_hours'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Cancellation window (hours)">
                            <input type="number" name="cancellation_rules[reschedule_window_hours]" min="0" value="{{ old('cancellation_rules.reschedule_window_hours', $cancelRules['reschedule_window_hours'] ?? 0) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Reschedule window (hours)">
                            <input type="number" name="cancellation_rules[maximum_reschedules]" min="0" value="{{ old('cancellation_rules.maximum_reschedules', $cancelRules['maximum_reschedules'] ?? 3) }}" class="rounded-lg border-slate-300 text-sm" placeholder="Maximum reschedules">
                        </div>
                    </div>
                </div>

                <button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">حفظ إعدادات المواعيد</button>
            </form>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold text-slate-900">Services</h2>
                <form method="POST" action="{{ route('workspace.appointments.services.store') }}" class="space-y-3 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <input type="text" name="name" placeholder="اسم الخدمة" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <textarea name="description" rows="2" placeholder="وصف" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="number" name="duration_minutes" min="5" max="600" value="30" class="rounded-lg border-slate-300 text-sm" required>
                        <input type="number" name="price" step="0.01" min="0" value="0" class="rounded-lg border-slate-300 text-sm" required>
                        <input type="text" name="color" placeholder="#0f172a" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <select name="staff_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($allStaff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <select name="payment_mode" class="rounded-lg border-slate-300 text-sm">
                            <option value="postpaid">Pay Later</option>
                            <option value="full">Full Payment</option>
                            <option value="deposit">Deposit</option>
                        </select>
                        <input type="number" name="deposit_amount" step="0.01" min="0" placeholder="قيمة العربون" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">نشطة</label>
                        <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="requires_confirmation" value="1" class="rounded border-slate-300">Requires Confirmation</label>
                        <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="approval_required" value="1" class="rounded border-slate-300">Requires Approval</label>
                        <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="requires_payment" value="1" class="rounded border-slate-300">Requires Payment</label>
                    </div>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إضافة خدمة</button>
                </form>

                <div class="mt-3 space-y-2">
                    @foreach($services as $service)
                        <details class="rounded-lg border border-slate-200 p-2">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ $service->name }} • {{ $service->duration_minutes }} دقيقة</summary>
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
                                <select name="staff_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                    @foreach($allStaff as $person)
                                        <option value="{{ $person->id }}" @selected($service->staffMembers->contains('id', $person->id))>{{ $person->name }}</option>
                                    @endforeach
                                </select>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <select name="payment_mode" class="rounded-lg border-slate-300 text-sm">
                                        @foreach(['postpaid' => 'Pay Later', 'full' => 'Full Payment', 'deposit' => 'Deposit'] as $mode => $label)
                                            <option value="{{ $mode }}" @selected($service->payment_mode === $mode)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0" name="deposit_amount" value="{{ $service->deposit_amount }}" class="rounded-lg border-slate-300 text-sm">
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($service->is_active) class="rounded border-slate-300">نشطة</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="requires_confirmation" value="1" @checked($service->requires_confirmation) class="rounded border-slate-300">Requires Confirmation</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="approval_required" value="1" @checked($service->approval_required) class="rounded border-slate-300">Requires Approval</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="requires_payment" value="1" @checked($service->requires_payment) class="rounded border-slate-300">Requires Payment</label>
                                </div>
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">تحديث الخدمة</button>
                            </form>
                        </details>
                    @endforeach
                </div>
                <div class="mt-3">{{ $services->links() }}</div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-bold text-slate-900">Staff & Resources</h2>
                <form method="POST" action="{{ route('workspace.appointments.staff.store') }}" class="space-y-3 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون ربط مستخدم</option>
                        @foreach($workspaceUsers as $workspaceUser)
                            <option value="{{ $workspaceUser->user_id }}">{{ $workspaceUser->user?->name }} ({{ $workspaceUser->membership_role }})</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" placeholder="اسم الموظف" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="role" placeholder="الدور" class="rounded-lg border-slate-300 text-sm">
                        <input type="text" name="phone" placeholder="الجوال" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <input type="text" name="color" placeholder="#1f2937" class="w-full rounded-lg border-slate-300 text-sm">
                    <select name="working_days[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($weekDays as $dayKey => $dayLabel)
                            <option value="{{ $dayKey }}">{{ $dayLabel }}</option>
                        @endforeach
                    </select>
                    <textarea name="working_hours_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder='ساعات العمل JSON مثال: {"sun":[{"start":"09:00","end":"13:00"},{"start":"16:00","end":"21:00"}]}'></textarea>
                    <textarea name="vacation_periods_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder='الإجازات JSON مثال: [{"from":"2026-09-01","to":"2026-09-10"}]'></textarea>
                    <textarea name="staff_permissions_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder='صلاحيات إضافية JSON (اختياري)'></textarea>
                    <select name="service_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                        @foreach($allServices as $service)
                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">موظف نشط</label>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إضافة موظف</button>
                </form>

                <div class="mt-3 space-y-2">
                    @foreach($staff as $person)
                        <details class="rounded-lg border border-slate-200 p-2">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ $person->name }} • {{ $person->role ?: 'بدون دور' }}</summary>
                            <form method="POST" action="{{ route('workspace.appointments.staff.update', $person) }}" class="mt-2 space-y-2">
                                @csrf
                                @method('PUT')
                                <select name="user_id" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">بدون ربط</option>
                                    @foreach($workspaceUsers as $workspaceUser)
                                        <option value="{{ $workspaceUser->user_id }}" @selected((int) $workspaceUser->user_id === (int) $person->user_id)>{{ $workspaceUser->user?->name }}</option>
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
                                <select name="working_days[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                    @foreach($weekDays as $dayKey => $dayLabel)
                                        <option value="{{ $dayKey }}" @selected(in_array($dayKey, is_array($person->working_days) ? $person->working_days : [], true))>{{ $dayLabel }}</option>
                                    @endforeach
                                </select>
                                <textarea name="working_hours_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs">{{ json_encode($person->working_hours, JSON_UNESCAPED_UNICODE) }}</textarea>
                                <textarea name="vacation_periods_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs">{{ json_encode($person->vacation_periods, JSON_UNESCAPED_UNICODE) }}</textarea>
                                <textarea name="staff_permissions_json" rows="2" class="w-full rounded-lg border-slate-300 text-xs">{{ json_encode($person->staff_permissions, JSON_UNESCAPED_UNICODE) }}</textarea>
                                <select name="service_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                                    @foreach($allServices as $service)
                                        <option value="{{ $service->id }}" @selected($person->services->contains('id', $service->id))>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($person->is_active) class="rounded border-slate-300">نشط</label>
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">تحديث</button>
                            </form>
                        </details>
                    @endforeach
                </div>
                <div class="mt-3">{{ $staff->links() }}</div>

                <hr class="my-4 border-slate-200">

                <h3 class="mb-2 text-sm font-bold text-slate-900">Resources</h3>
                <form method="POST" action="{{ route('workspace.appointments.resources.store') }}" class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-3">
                    @csrf
                    <input type="text" name="name" placeholder="اسم المورد" class="rounded-lg border-slate-300 text-sm sm:col-span-2" required>
                    <select name="resource_type" class="rounded-lg border-slate-300 text-sm">
                        <option value="room">Room</option>
                        <option value="chair">Chair</option>
                        <option value="equipment">Equipment</option>
                        <option value="meeting_room">Meeting Room</option>
                        <option value="other">Other</option>
                    </select>
                    <label class="sm:col-span-3 flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">مورد نشط</label>
                    <button class="sm:col-span-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إضافة مورد</button>
                </form>

                <div class="mt-3 space-y-2">
                    @foreach($resources as $resource)
                        <form method="POST" action="{{ route('workspace.appointments.resources.update', $resource) }}" class="grid gap-2 rounded-lg border border-slate-200 p-2 sm:grid-cols-4">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $resource->name }}" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
                            <select name="resource_type" class="rounded-lg border-slate-300 text-sm">
                                @foreach(['room' => 'Room', 'chair' => 'Chair', 'equipment' => 'Equipment', 'meeting_room' => 'Meeting Room', 'other' => 'Other'] as $type => $label)
                                    <option value="{{ $type }}" @selected($resource->resource_type === $type)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($resource->is_active) class="rounded border-slate-300">نشط</label>
                            <button class="sm:col-span-4 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">تحديث المورد</button>
                        </form>
                    @endforeach
                </div>
                <div class="mt-3">{{ $resources->links() }}</div>
            </section>
        </div>
    </div>
@endsection
