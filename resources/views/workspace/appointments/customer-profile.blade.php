@extends('layouts.appointments', ['pageTitle' => 'ملف العميل'])

@section('content')
    @php
        $statusLabels = [
            'scheduled' => 'مجدول',
            'confirmed' => 'مؤكد',
            'checked_in' => 'تم تسجيل الحضور',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'no_show' => 'لم يحضر',
        ];
        $requestLabels = [
            'new' => 'جديد',
            'reviewing' => 'قيد المراجعة',
            'awaiting_customer' => 'بانتظار العميل',
            'approved' => 'تمت الموافقة',
            'rejected' => 'مرفوض',
            'expired' => 'منتهي',
            'cancelled' => 'ملغي',
        ];
    @endphp
    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-lg font-bold">{{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $customer->phone }} @if($customer->email) — {{ $customer->email }} @endif</p>
            @if($customer->notes)
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $customer->notes }}</p>
            @endif
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المواعيد القادمة</h2>
                <div class="space-y-2 text-sm">
                    @forelse($upcomingBookings as $booking)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ $booking->booking_number }} — {{ $statusLabels[$booking->appointment_status] ?? $booking->appointment_status }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مواعيد قادمة.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">سجل المواعيد</h2>
                <div class="space-y-2 text-sm">
                    @forelse($pastBookings as $booking)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ $booking->booking_number }} — {{ $statusLabels[$booking->appointment_status] ?? $booking->appointment_status }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا يوجد سجل سابق.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">طلبات المواعيد</h2>
                <div class="space-y-2 text-sm">
                    @forelse($appointmentRequests as $request)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">طلب #{{ $request->id }} — {{ $requestLabels[$request->status] ?? $request->status }}</p>
                            <p class="text-xs text-slate-500">{{ $request->created_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد طلبات مواعيد.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">الفواتير</h2>
                <div class="space-y-2 text-sm">
                    @forelse($invoices as $invoice)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ $invoice->invoice_number }} — {{ $invoice->status }}</p>
                            <p class="text-xs text-slate-500">الإجمالي: {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد فواتير.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المدفوعات</h2>
                <div class="space-y-2 text-sm">
                    @forelse($payments as $payment)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">دفعة #{{ $payment->id }} — {{ number_format((float) $payment->amount, 2) }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->payment_date?->format('Y-m-d') }} — {{ $payment->method }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مدفوعات.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المحادثات</h2>
                <div class="space-y-2 text-sm">
                    @forelse($conversations as $conversation)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">قناة: {{ $conversation->channel }}</p>
                            <p class="text-xs text-slate-500">آخر رسالة: {{ optional($conversation->messages->first())->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد محادثات مرتبطة.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">البريد الإلكتروني</h2>
                <div class="space-y-2 text-sm">
                    @forelse($emails as $email)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ $email->subject ?: '(بدون عنوان)' }}</p>
                            <p class="text-xs text-slate-500">{{ $email->sender }} → {{ $email->recipient }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد رسائل بريد مرتبطة.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
