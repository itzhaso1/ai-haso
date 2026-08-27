<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Conversation;
use App\Models\Appointment\AppointmentBooking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerProfileController extends AppointmentsBaseController
{
    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeAppointments($request, 'appointments.view');

        $upcomingBookings = AppointmentBooking::query()
            ->where('customer_id', $customer->id)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $pastBookings = AppointmentBooking::query()
            ->where('customer_id', $customer->id)
            ->where('starts_at', '<', now())
            ->orderByDesc('starts_at')
            ->limit(15)
            ->get();

        $invoices = FinanceInvoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $payments = FinanceInvoicePayment::query()
            ->whereHas('invoice', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest('id')
            ->limit(20)
            ->get();

        $conversations = Conversation::query()
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->where('customer_id', $customer->id)
            ->latest('last_message_at')
            ->limit(20)
            ->get();

        $emailQuery = EmailMessage::query()->latest('id')->limit(20);
        if ($customer->email) {
            $email = mb_strtolower($customer->email);
            $emailQuery->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(sender) LIKE ?', ['%'.$email.'%'])
                    ->orWhereRaw('LOWER(recipient) LIKE ?', ['%'.$email.'%']);
            });
        } else {
            $emailQuery->whereRaw('1 = 0');
        }

        return view('workspace.appointments.customer-profile', [
            'customer' => $customer,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
            'invoices' => $invoices,
            'payments' => $payments,
            'conversations' => $conversations,
            'emails' => $emailQuery->get(),
        ]);
    }
}
