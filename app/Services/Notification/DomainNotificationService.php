<?php

namespace App\Services\Notification;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
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
}
