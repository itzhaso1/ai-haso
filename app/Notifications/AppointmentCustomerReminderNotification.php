<?php

namespace App\Notifications;

use App\Models\Appointment\AppointmentBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentCustomerReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AppointmentBooking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تذكير بموعدك القادم')
            ->line('هذا تذكير بموعدك القادم معنا.')
            ->line('رقم الموعد: '.$this->booking->booking_number)
            ->line('الخدمة: '.($this->booking->service?->name ?? '—'))
            ->line('الوقت: '.$this->booking->starts_at?->toDateTimeString())
            ->line('يمكنك استخدام رابط العميل لتأكيد الحضور أو طلب التعديل عند الحاجة.');
    }
}
