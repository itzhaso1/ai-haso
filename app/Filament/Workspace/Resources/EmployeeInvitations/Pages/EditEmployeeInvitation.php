<?php

namespace App\Filament\Workspace\Resources\EmployeeInvitations\Pages;

use App\Filament\Workspace\Resources\EmployeeInvitations\EmployeeInvitationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeInvitation extends EditRecord
{
    protected static string $resource = EmployeeInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
