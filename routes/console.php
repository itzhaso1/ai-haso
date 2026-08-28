<?php

use App\Services\Appointments\AppointmentReminderService;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:reminders:prepare', function (AppointmentReminderService $service) {
    $created = $service->scheduleUpcomingReminders(14);
    $this->info("تم تجهيز {$created} تذكير للمواعيد القادمة.");
})->purpose('Prepare appointment reminders for upcoming bookings');

Artisan::command('appointments:reminders:dispatch', function (AppointmentReminderService $service) {
    $processed = $service->dispatchDueReminders(200);
    $this->info("تمت معالجة {$processed} تذكير مستحق.");
})->purpose('Dispatch due appointment reminders');

Artisan::command('finance:invoices:refresh-payment-status', function (InvoiceService $service) {
    $updated = $service->refreshIssuedPaymentStatuses();
    $this->info("تم تحديث {$updated} فاتورة لحالة الدفع الفعلية.");
})->purpose('Recalculate issued invoices payment status and overdue state');

Artisan::command('finance:invoices:status-audit', function () {
    $rows = DB::table('finance_invoices')
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->orderBy('status')
        ->get();

    if ($rows->isEmpty()) {
        $this->warn('لا توجد فواتير حالية لتحليل الحالات.');

        return;
    }

    $this->line('Legacy status distribution:');
    foreach ($rows as $row) {
        $this->line(sprintf('- %s: %d', $row->status, $row->total));
    }
})->purpose('Show current finance_invoices legacy status counts before migration');

Artisan::command('finance:invoices:integrity-report', function () {
    $invoiceCount = DB::table('finance_invoices')->count();
    $paymentCount = DB::table('finance_invoice_payments')->count();
    $total = (float) DB::table('finance_invoices')->sum('total');
    $paid = (float) DB::table('finance_invoices')->sum('amount_paid');
    $due = (float) DB::table('finance_invoices')->sum('amount_due');

    $this->line('Invoice integrity metrics:');
    $this->line(sprintf('- invoices_count: %d', $invoiceCount));
    $this->line(sprintf('- payments_count: %d', $paymentCount));
    $this->line(sprintf('- totals_sum: %.2f', $total));
    $this->line(sprintf('- amount_paid_sum: %.2f', $paid));
    $this->line(sprintf('- amount_due_sum: %.2f', $due));
})->purpose('Output baseline invoice counters and totals for migration integrity checks');

Schedule::command('appointments:reminders:prepare')->everyFiveMinutes();
Schedule::command('appointments:reminders:dispatch')->everyMinute();
Schedule::command('finance:invoices:refresh-payment-status')->hourly();
