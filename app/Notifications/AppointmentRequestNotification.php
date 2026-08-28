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

    public function __construct(
        public readonly AppointmentRequest $appointmentRequest,
        public readonly ?string $title = null,
        public readonly ?string $message = null,
    ) {}

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
            ->subject($this->title ?: 'تحديث طلب موعد')
            ->line($this->message ?: 'تم إنشاء/تحديث طلب موعد للعميل: '.$this->appointmentRequest->customer_name)
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
            'type' => $this->title ? 'appointment_request_update' : 'appointment_request_created',
            'title' => $this->title ?: 'تحديث طلب موعد',
            'message' => $this->message ?: 'تم تحديث حالة طلب الموعد',
            'request_id' => $this->appointmentRequest->id,
            'workspace_id' => $this->appointmentRequest->workspace_id,
            'customer_name' => $this->appointmentRequest->customer_name,
            'source' => $this->appointmentRequest->source,
            'status' => $this->appointmentRequest->status,
        ];
    }
}
