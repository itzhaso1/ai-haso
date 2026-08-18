<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

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
            ->subject('طلب جديد '.$this->order->order_number)
            ->line('تم إنشاء طلب جديد بقيمة '.$this->order->total_amount.' '.$this->order->currency)
            ->action('عرض الطلبات', url('/workspace/orders'))
            ->line('AI-HASO');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_created',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => $this->order->total_amount,
            'currency' => $this->order->currency,
            'workspace_id' => $this->order->workspace_id,
        ];
    }
}
