@extends('layouts.financial', ['pageTitle' => 'موظفو المالية'])

@section('content')
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-slate-900">موظفو المالية</h2>
        <p class="text-sm text-slate-500">هذه الصفحة تبني طبقة Finance Employees فوق نفس موظفي الـWorkspace دون تكرار بيانات المستخدمين.</p>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold">إضافة أو تعيين موظف مالية</h3>
            <form method="POST" action="{{ route('workspace.finance.employees.store') }}" class="grid gap-2 md:grid-cols-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الموظف</label>
                    <select name="user_id" required class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">اختر الموظف</option>
                        @foreach($workspaceEmployees as $employee)
                            <option value="{{ $employee->user_id }}" @selected((string) old('user_id') === (string) $employee->user_id)>
                                {{ $employee->user?->name }} ({{ $employee->membership_role }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الدور المالي</label>
                    <select name="finance_role" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">بدون تحديد</option>
                        @foreach($roles as $roleValue => $roleLabel)
                            <option value="{{ $roleValue }}" @selected(old('finance_role') === $roleValue)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                    @error('finance_role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">حفظ</button>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الراتب الأساسي</label>
                    <input name="basic_salary" type="number" step="0.01" min="0" value="{{ old('basic_salary', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">بدل السكن</label>
                    <input name="housing_allowance" type="number" step="0.01" min="0" value="{{ old('housing_allowance', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">بدل النقل</label>
                    <input name="transport_allowance" type="number" step="0.01" min="0" value="{{ old('transport_allowance', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">بدلات أخرى</label>
                    <input name="other_allowances" type="number" step="0.01" min="0" value="{{ old('other_allowances', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">خصومات افتراضية</label>
                    <input name="default_deductions" type="number" step="0.01" min="0" value="{{ old('default_deductions', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الحالة</label>
                    <select name="is_active" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="1" @selected((string) old('is_active', '1') === '1')>نشط</option>
                        <option value="0" @selected((string) old('is_active') === '0')>غير نشط</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظات</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="مثال: مسؤول عن التسويات الشهرية">{{ old('notes') }}</textarea>
                </div>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold">قائمة موظفي المالية</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-2 py-2 text-right">الموظف</th>
                            <th class="px-2 py-2 text-right">الدور المالي</th>
                            <th class="px-2 py-2 text-right">الراتب الأساسي</th>
                            <th class="px-2 py-2 text-right">البدلات</th>
                            <th class="px-2 py-2 text-right">الخصومات</th>
                            <th class="px-2 py-2 text-right">الحالة</th>
                            <th class="px-2 py-2 text-right">تحديث سريع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($profiles as $profile)
                            <tr>
                                <td class="px-2 py-2 font-semibold text-slate-900">{{ $profile->user?->name }}</td>
                                <td class="px-2 py-2">{{ $roles[$profile->finance_role] ?? ($profile->finance_role ?: '—') }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $profile->basic_salary, 2) }}</td>
                                <td class="px-2 py-2">
                                    {{ number_format((float) $profile->housing_allowance + (float) $profile->transport_allowance + (float) $profile->other_allowances, 2) }}
                                </td>
                                <td class="px-2 py-2">{{ number_format((float) $profile->default_deductions, 2) }}</td>
                                <td class="px-2 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $profile->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $profile->is_active ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('workspace.finance.employees.update', $profile) }}" class="grid gap-1 sm:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="user_id" value="{{ $profile->user_id }}">
                                        <select name="finance_role" class="rounded-md border-slate-300 text-xs">
                                            <option value="">—</option>
                                            @foreach($roles as $roleValue => $roleLabel)
                                                <option value="{{ $roleValue }}" @selected($profile->finance_role === $roleValue)>{{ $roleLabel }}</option>
                                            @endforeach
                                        </select>
                                        <select name="is_active" class="rounded-md border-slate-300 text-xs">
                                            <option value="1" @selected($profile->is_active)>نشط</option>
                                            <option value="0" @selected(! $profile->is_active)>غير نشط</option>
                                        </select>
                                        <input name="basic_salary" type="number" step="0.01" min="0" value="{{ (float) $profile->basic_salary }}" class="rounded-md border-slate-300 text-xs">
                                        <button class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white">حفظ</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-2 py-8 text-center text-slate-500">لا يوجد موظفو مالية حتى الآن.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $profiles->links() }}</div>
        </article>
    </div>
@endsection
