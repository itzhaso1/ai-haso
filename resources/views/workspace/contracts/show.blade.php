@extends('layouts.financial', ['pageTitle' => 'تفاصيل العقد'])

@section('content')
    @php
        $routePrefix = $routePrefix ?? 'workspace.finance.contracts';
        $statusLabels = [
            'draft' => 'مسودة',
            'open' => 'مفتوح',
            'closed' => 'مغلق',
            'cancelled' => 'ملغي',
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">العقد {{ $contract->contract_number }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $contract->title }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route($routePrefix.'.edit', $contract) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">تعديل</a>
                <a href="{{ route($routePrefix.'.pdf', $contract) }}" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">تحميل PDF</a>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">بيانات العقد</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $statusLabels[$contract->status] ?? $contract->status }}</span>
                </div>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">العميل</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $contract->customer?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">القيمة</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ number_format((float) $contract->value, 2) }} {{ $contract->currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">تاريخ البداية</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($contract->start_date)->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">تاريخ النهاية</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ optional($contract->end_date)->format('Y-m-d') ?: '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-1 text-xs font-semibold text-slate-600">الشروط</h4>
                        <div class="min-h-24 whitespace-pre-line rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $contract->terms ?: '—' }}</div>
                    </div>
                    <div>
                        <h4 class="mb-1 text-xs font-semibold text-slate-600">الملاحظات</h4>
                        <div class="min-h-24 whitespace-pre-line rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $contract->notes ?: '—' }}</div>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" x-data="{ sendEmail: false }">
                <h3 class="mb-3 text-sm font-bold text-slate-900">إجراءات العقد</h3>

                @if($contract->status === 'draft')
                    <form method="POST" action="{{ route($routePrefix.'.activate', $contract) }}" class="space-y-3">
                        @csrf
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="send_email" value="1" x-model="sendEmail" class="rounded border-slate-300 text-slate-900">
                            إرسال العقد بالبريد عند التفعيل
                        </label>

                        <div x-cloak x-show="sendEmail" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">حساب البريد</label>
                                <select name="email_account_id" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">اختر الحساب</option>
                                    @foreach($emailAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('email_account_id') === (string) $account->id)>
                                            {{ $account->name }} ({{ $account->email }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('email_account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">المستلم</label>
                                <input name="recipient" value="{{ old('recipient', $contract->customer?->email) }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="client@example.com">
                                @error('recipient')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">CC (اختياري)</label>
                                <input name="cc" value="{{ old('cc') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="cc1@example.com, cc2@example.com">
                                @error('cc')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Subject</label>
                                <input name="subject" value="{{ old('subject', 'تفعيل العقد '.$contract->contract_number) }}" class="w-full rounded-lg border-slate-300 text-sm">
                                @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Message</label>
                                <textarea name="message" rows="3" class="w-full rounded-lg border-slate-300 text-sm">{{ old('message', 'تم تفعيل العقد وإرفاق نسخة PDF للاعتماد.') }}</textarea>
                                @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">تفعيل العقد</button>
                    </form>
                @elseif($contract->status === 'open')
                    <div class="space-y-2">
                        <form method="POST" action="{{ route($routePrefix.'.close', $contract) }}" onsubmit="return confirm('إغلاق العقد؟')">
                            @csrf
                            <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">إغلاق العقد</button>
                        </form>
                        <form method="POST" action="{{ route($routePrefix.'.cancel', $contract) }}" onsubmit="return confirm('إلغاء العقد؟')">
                            @csrf
                            <button class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">إلغاء العقد</button>
                        </form>
                    </div>
                @else
                    <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">لا توجد إجراءات متاحة للحالة الحالية.</p>
                @endif
            </article>
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-slate-900">بنود العقد</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-2 py-2 text-right">البند</th>
                            <th class="px-2 py-2 text-right">الوصف</th>
                            <th class="px-2 py-2 text-right">الكمية</th>
                            <th class="px-2 py-2 text-right">سعر الوحدة</th>
                            <th class="px-2 py-2 text-right">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($contract->items as $item)
                            <tr>
                                <td class="px-2 py-2 font-semibold">{{ $item->title }}</td>
                                <td class="px-2 py-2 text-slate-600">{{ $item->description ?: '—' }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $item->quantity, 3) }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-2 py-2">{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-2 py-6 text-center text-slate-500">لا توجد بنود بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        @include('workspace.contracts.partials.attachments-panel', [
            'contract' => $contract,
            'routePrefix' => $routePrefix,
            'allowDelete' => true,
        ])
    </div>
@endsection
