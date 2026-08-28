<?php

use App\Services\Appointments\AppointmentReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('appointments:reminders:prepare')->everyFiveMinutes();
Schedule::command('appointments:reminders:dispatch')->everyMinute();
