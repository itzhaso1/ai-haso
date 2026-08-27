<?php

namespace App\Notifications;

use App\Models\Appointment\AppointmentBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AppointmentBooking $booking,
        public readonly string $title,
        public readonly string $message,
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
            ->subject($this->title)
            ->line($this->message)
            ->line('رقم الموعد: '.$this->booking->booking_number)
            ->line('حالة الموعد: '.$this->booking->appointment_status)
            ->line('حالة الدفع: '.$this->booking->payment_status)
            ->action('فتح لوحة المواعيد', url('/workspace/appointments'))
            ->line('HASEM Appointments');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_lifecycle',
            'title' => $this->title,
            'message' => $this->message,
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'workspace_id' => $this->booking->workspace_id,
            'appointment_status' => $this->booking->appointment_status,
            'payment_status' => $this->booking->payment_status,
        ];
    }
}
