<?php

namespace App\Services\Notification;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AppointmentLifecycleNotification;
use App\Notifications\AppointmentRequestNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\PaymentConfirmedNotification;

class DomainNotificationService
{
    public function notifyOrderCreated(Order $order): void
    {
        $recipients = $order->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new NewOrderNotification($order));
        }
    }

    public function notifyPaymentConfirmed(Payment $payment): void
    {
        $recipients = $payment->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new PaymentConfirmedNotification($payment));
        }
    }

    public function notifyLowStock(User $recipient, string $productName, int $stock): void
    {
        $recipient->notify(new LowStockNotification($productName, $stock));
    }

    public function notifyAppointmentRequestCreated(AppointmentRequest $request): void
    {
        $recipients = $request->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'agent'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new AppointmentRequestNotification($request));
        }
    }

    public function notifyAppointmentBookingStatusChanged(AppointmentBooking $booking, string $title, string $message): void
    {
        $recipients = $booking->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'agent'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new AppointmentLifecycleNotification($booking, $title, $message));
        }
    }
}
