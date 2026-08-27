<?php

namespace App\Notifications;

use App\Models\Appointment\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AppointmentRequest $appointmentRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('طلب موعد جديد')
            ->line('تم إنشاء طلب موعد جديد باسم: '.$this->appointmentRequest->customer_name)
            ->line('القناة: '.$this->appointmentRequest->source)
            ->action('فتح لوحة المواعيد', url('/workspace/appointments'))
            ->line('HASEM Appointments');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_request_created',
            'request_id' => $this->appointmentRequest->id,
            'workspace_id' => $this->appointmentRequest->workspace_id,
            'customer_name' => $this->appointmentRequest->customer_name,
            'source' => $this->appointmentRequest->source,
            'status' => $this->appointmentRequest->status,
        ];
    }
}
