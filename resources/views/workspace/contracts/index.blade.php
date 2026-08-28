<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">العقود</h2>
                <p class="mt-1 text-xs text-slate-500">إدارة العقود المفتوحة وإجراءات التفعيل/الإغلاق.</p>
            </div>
            <a href="{{ route('workspace.contracts.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">إنشاء عقد</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('workspace.partials.nav')
            @include('partials.flash')

            @php
                $contractsCollection = $contracts->getCollection();
                $openCount = $contractsCollection->where('status', 'open')->count();
                $draftCount = $contractsCollection->where('status', 'draft')->count();
                $closedCount = $contractsCollection->where('status', 'closed')->count();
            @endphp

            <div class="mb-4 grid gap-3 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">عقود مفتوحة (في الصفحة الحالية)</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $openCount }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">عقود مسودة</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $draftCount }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">عقود مغلقة</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $closedCount }}</p>
                </article>
            </div>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" class="grid gap-2 md:grid-cols-5">
                    <input name="search" value="{{ $filters['search'] ?? '' }}" class="rounded-lg border-slate-300 text-sm md:col-span-3" placeholder="بحث برقم العقد أو العنوان أو العميل">
                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الحالات</option>
                        @foreach(['draft' => 'مسودة', 'open' => 'مفتوح', 'closed' => 'مغلق', 'cancelled' => 'ملغي'] as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">تطبيق</button>
                </form>
            </article>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-right">رقم العقد</th>
                            <th class="px-3 py-2 text-right">العنوان</th>
                            <th class="px-3 py-2 text-right">العميل</th>
                            <th class="px-3 py-2 text-right">الحالة</th>
                            <th class="px-3 py-2 text-right">القيمة</th>
                            <th class="px-3 py-2 text-right">الفترة</th>
                            <th class="px-3 py-2 text-left">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($contracts as $contract)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-slate-900">{{ $contract->contract_number }}</td>
                                <td class="px-3 py-3">{{ $contract->title }}</td>
                                <td class="px-3 py-3">{{ $contract->customer?->name ?: '—' }}</td>
                                <td class="px-3 py-3">
                                    @php
                                        $statusLabel = [
                                            'draft' => 'مسودة',
                                            'open' => 'مفتوح',
                                            'closed' => 'مغلق',
                                            'cancelled' => 'ملغي',
                                        ][$contract->status] ?? $contract->status;
                                    @endphp
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-3 py-3">{{ number_format((float) $contract->value, 2) }} {{ $contract->currency }}</td>
                                <td class="px-3 py-3 text-xs text-slate-600">
                                    {{ optional($contract->start_date)->format('Y-m-d') ?: '—' }} → {{ optional($contract->end_date)->format('Y-m-d') ?: '—' }}
                                </td>
                                <td class="px-3 py-3 text-left">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('workspace.contracts.show', $contract) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">عرض</a>
                                        <a href="{{ route('workspace.contracts.edit', $contract) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">تعديل</a>
                                        <a href="{{ route('workspace.contracts.pdf', $contract) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-slate-500">لا توجد عقود حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $contracts->links() }}</div>
        </div>
    </div>
</x-app-layout>
