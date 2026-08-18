<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تم تأكيد عملية الدفع')
            ->line('تم تأكيد دفعة للطلب '.$this->payment->order->order_number)
            ->line('المبلغ: '.$this->payment->amount.' '.$this->payment->currency)
            ->action('عرض المدفوعات', url('/workspace/payments'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_confirmed',
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'workspace_id' => $this->payment->workspace_id,
        ];
    }
}
