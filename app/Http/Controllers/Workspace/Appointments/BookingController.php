<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentReminder;
use App\Services\Appointments\AppointmentBillingService;
use App\Services\Appointments\AppointmentService;
use App\Services\Notification\DomainNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BookingController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly DomainNotificationService $domainNotificationService,
    ) {}

    public function createPaymentLink(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.billing.manage');
        try {
            $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء رابط الدفع لهذا الموعد.');
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $this->authorizeAppointments($request, 'appointments.calendar.view');
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $workspace = $this->currentWorkspace();
        $filters = $validated + ['timezone' => $this->appointmentService->workspaceTimezone($workspace->id)];
        if ($this->isStaffScoped($request)) {
            $filters['staff_user_id'] = (int) $request->user()?->id;
        }

        $events = $this->appointmentService->calendarEvents($filters);

        return response()->json(['data' => $events]);
    }

    public function sendReminder(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');

        if (in_array($booking->appointment_status, ['cancelled', 'completed', 'no_show'], true)) {
            return back()->with('error', 'لا يمكن إرسال تذكير لموعد مغلق أو ملغي.');
        }

        AppointmentReminder::withoutGlobalScopes()->create([
            'workspace_id' => $booking->workspace_id,
            'booking_id' => $booking->id,
            'channel' => 'in_app',
            'status' => 'sent',
            'send_at' => now(),
            'sent_at' => now(),
            'metadata' => [
                'manual' => true,
                'triggered_by' => (int) $request->user()?->id,
            ],
        ]);

        $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
            $booking,
            'تذكير بموعد قادم',
            'تم إرسال تذكير يدوي للعميل بخصوص الموعد القادم.'
        );

        return back()->with('success', 'تم إرسال التذكير بنجاح.');
    }
}
