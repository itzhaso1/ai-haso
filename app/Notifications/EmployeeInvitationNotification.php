<?php

namespace App\Notifications;

use App\Models\EmployeeInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EmployeeInvitation $invitation) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url('/employee-invitations/accept?token='.$this->invitation->token);

        return (new MailMessage)
            ->subject('دعوة انضمام إلى مساحة عمل')
            ->line('تمت دعوتك للانضمام إلى مساحة عمل بدور: '.$this->invitation->role)
            ->action('قبول الدعوة', $acceptUrl)
            ->line('تنتهي الدعوة في: '.$this->invitation->expires_at?->toDateTimeString());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'employee_invitation',
            'invitation_id' => $this->invitation->id,
            'workspace_id' => $this->invitation->workspace_id,
            'role' => $this->invitation->role,
        ];
    }
}
