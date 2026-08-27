<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Services\Appointments\AppointmentBillingService;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
    ) {}

    public function createPaymentLink(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, (int) $request->user()?->id);

        return back()->with('success', 'تم إنشاء رابط الدفع لهذا الموعد.');
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
        ]);

        $events = $this->appointmentService->calendarEvents($validated);

        return response()->json(['data' => $events]);
    }
}
