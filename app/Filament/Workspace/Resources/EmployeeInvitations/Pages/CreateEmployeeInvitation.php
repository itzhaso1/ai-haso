<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Pages;

use App\Filament\Workspace\Resources\EmployeeInvitations\EmployeeInvitationResource;
use App\Notifications\EmployeeInvitationNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Notification;

class CreateEmployeeInvitation extends CreateRecord
{
    protected static string $resource = EmployeeInvitationResource::class;

    protected function afterCreate(): void
    {
        Notification::route('mail', $this->record->email)
            ->notify(new EmployeeInvitationNotification($this->record));

        FilamentNotification::make()
            ->title('تم إرسال دعوة الموظف')
            ->success()
            ->send();
    }
}
