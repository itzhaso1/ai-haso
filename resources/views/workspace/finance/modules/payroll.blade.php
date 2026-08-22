@extends('layouts.financial', ['pageTitle' => 'الرواتب'])

@section('content')
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-slate-900">الرواتب (مرتبطة بموظفي الـWorkspace الحاليين)</h2>

        <div class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">الموظفون الحاليون</h3>
                <div class="space-y-2">
                    @forelse($employees as $employee)
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $employee->user?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $employee->membership_role }} | {{ $employee->user?->email }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا يوجد موظفون.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold">ملفات الرواتب</h3>
                <div class="space-y-2">
                    @forelse($profiles as $profile)
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <p class="font-semibold">{{ $profile->user?->name }}</p>
                            <p class="text-xs text-slate-500">
                                Basic: {{ number_format((float) $profile->basic_salary, 2) }}
                                | Housing: {{ number_format((float) $profile->housing_allowance, 2) }}
                                | Transport: {{ number_format((float) $profile->transport_allowance, 2) }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد ملفات رواتب بعد.</p>
                    @endforelse
                </div>
                <div class="mt-3">{{ $profiles->links() }}</div>
            </article>
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold">مسيرات الرواتب</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-2 py-2 text-right">الفترة</th>
                            <th class="px-2 py-2 text-right">الحالة</th>
                            <th class="px-2 py-2 text-right">Gross</th>
                            <th class="px-2 py-2 text-right">Deductions</th>
                            <th class="px-2 py-2 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($runs as $run)
                            <tr>
                                <td class="px-2 py-2">{{ $run->period_month }}</td>
                                <td class="px-2 py-2">{{ $run->status }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $run->total_gross, 2) }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $run->total_deductions, 2) }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $run->total_net, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-2 py-6 text-center text-slate-500">لا توجد مسيرات رواتب.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>
@endsection
